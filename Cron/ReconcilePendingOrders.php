<?php
/**
 * Reconciles AlyaPay orders stuck in pending_payment — covers the case where
 * neither the webhook (blocked by merchant firewall/WAF) nor the browser
 * redirect (customer closed the tab) ever confirmed the transaction outcome.
 * Polls AlyaPay directly by vendor reference (the order increment id), which
 * needs no transaction_id captured earlier in the flow.
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Cron;

use AlyaPay\Payment\Helper\Order as OrderHelper;
use AlyaPay\Payment\Model\Api\VendorTransactionService;
use AlyaPay\Payment\Model\Config;
use AlyaPay\Payment\Model\Error\Context;
use AlyaPay\Payment\Model\Error\Handler as ErrorHandler;
use AlyaPay\Payment\Model\PaymentMethod;
use Magento\Sales\Model\Order as SalesOrder;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Psr\Log\LoggerInterface;

class ReconcilePendingOrders
{
    private const APPROVED_STATUSES = ['APPROVED', 'COMPLETED'];
    private const FAILED_STATUSES = ['CANCELED', 'EXPIRED', 'DECLINED', 'FAILED'];

    /** SQL prefilter floor — narrows the scanned set before per-store expiry is checked */
    private const MIN_AGE_MINUTES = 20;

    /** Wait this long past the configured transaction expiry before polling */
    private const GRACE_MINUTES = 5;

    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var VendorTransactionService
     */
    private $vendorTransactionService;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ErrorHandler
     */
    private $errorHandler;

    /**
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param OrderHelper $orderHelper
     * @param VendorTransactionService $vendorTransactionService
     * @param Config $config
     * @param LoggerInterface $logger
     * @param ErrorHandler $errorHandler
     */
    public function __construct(
        OrderCollectionFactory $orderCollectionFactory,
        OrderHelper $orderHelper,
        VendorTransactionService $vendorTransactionService,
        Config $config,
        LoggerInterface $logger,
        ErrorHandler $errorHandler
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderHelper = $orderHelper;
        $this->vendorTransactionService = $vendorTransactionService;
        $this->config = $config;
        $this->logger = $logger;
        $this->errorHandler = $errorHandler;
    }

    /**
     * Cron entry point
     */
    public function execute(): void
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('state', SalesOrder::STATE_PENDING_PAYMENT);
        $collection->join(
            ['alyapay_payment' => $collection->getTable('sales_order_payment')],
            'alyapay_payment.parent_id = main_table.entity_id',
            ['method']
        );
        $collection->addFieldToFilter('alyapay_payment.method', PaymentMethod::CODE);
        $collection->addFieldToFilter(
            'created_at',
            ['lteq' => date('Y-m-d H:i:s', strtotime('-' . self::MIN_AGE_MINUTES . ' minutes'))]
        );

        foreach ($collection as $order) {
            try {
                $this->reconcileOrder($order);
            } catch (\Throwable $e) {
                $this->logger->error('AlyaPay cron: unexpected error reconciling order', [
                    'increment_id' => $order->getIncrementId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param SalesOrder $order
     */
    private function reconcileOrder(SalesOrder $order): void
    {
        $storeId = (int) $order->getStoreId();

        if (!$this->config->isActive($storeId) || !$this->config->getApiKey($storeId)) {
            return;
        }

        $expiryMinutes = $this->config->getTransactionExpiry($storeId);
        $thresholdMinutes = $expiryMinutes > 0 ? $expiryMinutes + self::GRACE_MINUTES : self::MIN_AGE_MINUTES;

        // AlyaPay's own expiry clock starts when the customer was actually redirected to
        // checkout (session-intent call), not when the Magento order row was created —
        // those are normally seconds apart but redirected_at is the accurate anchor.
        // Falls back to created_at for the rare case where the order is pending_payment
        // but the redirect never happened (e.g. session-intent call itself failed before
        // saving the timestamp — though that path currently cancels the order anyway).
        $redirectedAt = $order->getPayment()->getAdditionalInformation(PaymentMethod::REDIRECTED_AT);
        $anchor = $redirectedAt !== null ? (int) $redirectedAt : strtotime((string) $order->getCreatedAt());
        if ($anchor === false || (time() - $anchor) < ($thresholdMinutes * 60)) {
            return;
        }

        $vendorReference = (string) $order->getIncrementId();

        try {
            $response = $this->vendorTransactionService->getByVendorReference($vendorReference, $storeId);
        } catch (\Throwable $e) {
            $result = $this->errorHandler->handle($e, Context::STATUS_CHECK);
            $this->logger->warning('AlyaPay cron: vendor lookup failed', [
                'increment_id' => $vendorReference,
                'error' => $result->getLogMessage(),
            ]);
            return;
        }

        $status = strtoupper((string) ($response['status'] ?? ''));
        $transactionId = (string) ($response['id'] ?? '');

        if ($status === '') {
            return;
        }

        if (in_array($status, self::APPROVED_STATUSES, true)) {
            $targetStatus = $this->config->getApprovedStatus($storeId);
            $comment = (string) __(
                'AlyaPay payment approved (cron reconciliation). Transaction ID: %1',
                $transactionId
            );
            $this->orderHelper->approveAndCaptureOrder($order, $transactionId, $targetStatus, $comment);
            $this->logger->info('AlyaPay cron: order approved via reconciliation', [
                'increment_id' => $vendorReference,
                'transaction_id' => $transactionId,
            ]);
            return;
        }

        if (in_array($status, self::FAILED_STATUSES, true)) {
            $targetStatus = $status === 'EXPIRED'
                ? $this->config->getExpiredStatus($storeId)
                : $this->config->getCanceledStatus($storeId);
            $comment = (string) __(
                'AlyaPay: Transaction %1 (cron reconciliation). Transaction ID: %2',
                strtolower($status),
                $transactionId
            );
            $this->orderHelper->applyWebhookStatus($order, $targetStatus, $comment);
            $this->logger->info('AlyaPay cron: order closed via reconciliation', [
                'increment_id' => $vendorReference,
                'status' => $status,
            ]);
            return;
        }

        // PENDING/PROCESSING — transaction still in progress on AlyaPay's side, leave order as-is.
    }
}
