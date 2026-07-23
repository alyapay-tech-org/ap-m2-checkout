<?php
/**
 * Success return controller - handles callback from AlyaPay (status + transaction_id in URL)
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Controller\Result;

use AlyaPay\Payment\Model\Api\VendorTransactionService;
use AlyaPay\Payment\Model\Config;
use AlyaPay\Payment\Model\Error\Context;
use AlyaPay\Payment\Model\Error\Handler as ErrorHandler;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

class Success implements HttpGetActionInterface
{
    private const APPROVED_STATUSES = ['APPROVED', 'COMPLETED'];
    private const TERMINAL_FAILED_STATUSES = ['CANCELED', 'EXPIRED', 'DECLINED', 'FAILED'];

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var VendorTransactionService
     */
    private $vendorTransactionService;

    /**
     * @var RedirectFactory
     */
    private $resultRedirectFactory;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var \AlyaPay\Payment\Helper\Order
     */
    private $orderHelper;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ErrorHandler
     */
    private $errorHandler;

    /**
     * @param CheckoutSession $checkoutSession
     * @param VendorTransactionService $vendorTransactionService
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     * @param \AlyaPay\Payment\Helper\Order $orderHelper
     * @param Config $config
     * @param UrlInterface $urlBuilder
     * @param LoggerInterface $logger
     * @param ErrorHandler $errorHandler
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        VendorTransactionService $vendorTransactionService,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        \AlyaPay\Payment\Helper\Order $orderHelper,
        Config $config,
        UrlInterface $urlBuilder,
        LoggerInterface $logger,
        ErrorHandler $errorHandler
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->vendorTransactionService = $vendorTransactionService;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->orderHelper = $orderHelper;
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
        $this->errorHandler = $errorHandler;
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('checkout/cart');

        $incrementId = $this->checkoutSession->getLastRealOrderId();
        if (!$incrementId) {
            $this->messageManager->addErrorMessage(__('Invalid return from payment.'));
            return $redirect;
        }

        $order = $this->orderHelper->getOrderByIncrementId($incrementId);
        if (!$order) {
            $this->messageManager->addErrorMessage(__('Order not found.'));
            return $redirect;
        }

        $storeId = (int) $order->getStoreId();

        // The URL's `status`/`transaction_id` params are not signed and not authoritative —
        // AlyaPay's transaction stays PENDING until it actually reaches APPROVED/COMPLETED or
        // CANCELED/EXPIRED. Always verify via the order's own vendor reference (works even
        // without any query params) before deciding what happened.
        try {
            $statusResponse = $this->vendorTransactionService->getByVendorReference(
                (string) $order->getIncrementId(),
                $storeId
            );
            $apiStatus = strtoupper($statusResponse['status'] ?? '');
            $transactionId = (string) ($statusResponse['id'] ?? '');

            if (in_array($apiStatus, self::APPROVED_STATUSES, true)) {
                $comment = (string) __('AlyaPay payment approved (fallback). Transaction ID: %1', $transactionId);
                $targetStatus = $this->config->getApprovedStatus($storeId);
                $this->orderHelper->approveAndCaptureOrder($order, $transactionId, $targetStatus, $comment);
                $this->logger->info('AlyaPay payment approved (Success fallback)', [
                    'increment_id' => $order->getIncrementId(),
                    'transaction_id' => $transactionId,
                    'api_status' => $apiStatus,
                ]);
                $redirect->setUrl($this->urlBuilder->getUrl('checkout/onepage/success'));
                return $redirect;
            }

            if (in_array($apiStatus, self::TERMINAL_FAILED_STATUSES, true)) {
                $this->messageManager->addErrorMessage(
                    __('Payment was not successful (status: %1).', $apiStatus)
                );
                $this->orderHelper->cancelOrder($order, 'Payment failed: ' . $apiStatus);
                $this->orderHelper->restoreQuote();
                return $redirect;
            }

            // PENDING/PROCESSING — not resolved yet. Don't cancel; webhook or cron
            // reconciliation will close it out once AlyaPay reaches a terminal state.
            $this->messageManager->addNoticeMessage(
                __('We are confirming your payment status. Your order will update shortly.')
            );
        } catch (\Throwable $e) {
            // Lookup failed (network/API issue) — not proof the payment failed. Leave the
            // order as pending_payment rather than cancelling on an inconclusive check.
            $result = $this->errorHandler->handle($e, Context::STATUS_CHECK);
            $this->logger->warning('AlyaPay Success: vendor lookup failed, leaving order pending', [
                'increment_id' => $order->getIncrementId(),
                'error' => $result->getLogMessage(),
            ]);
            $this->messageManager->addNoticeMessage(
                __('We are confirming your payment status. Your order will update shortly.')
            );
        }

        return $redirect;
    }
}
