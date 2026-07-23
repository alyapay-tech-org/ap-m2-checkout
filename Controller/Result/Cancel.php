<?php
/**
 * Cancel return controller
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Controller\Result;

use AlyaPay\Payment\Model\Api\VendorTransactionService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

class Cancel implements HttpGetActionInterface
{
    private const APPROVED_STATUSES = ['APPROVED', 'COMPLETED'];
    private const TERMINAL_FAILED_STATUSES = ['CANCELED', 'EXPIRED', 'DECLINED', 'FAILED'];

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var RedirectFactory
     */
    private $resultRedirectFactory;

    /**
     * @var \AlyaPay\Payment\Helper\Order
     */
    private $orderHelper;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var VendorTransactionService
     */
    private $vendorTransactionService;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CheckoutSession $checkoutSession
     * @param RedirectFactory $resultRedirectFactory
     * @param \AlyaPay\Payment\Helper\Order $orderHelper
     * @param UrlInterface $urlBuilder
     * @param VendorTransactionService $vendorTransactionService
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        RedirectFactory $resultRedirectFactory,
        \AlyaPay\Payment\Helper\Order $orderHelper,
        UrlInterface $urlBuilder,
        VendorTransactionService $vendorTransactionService,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->orderHelper = $orderHelper;
        $this->urlBuilder = $urlBuilder;
        $this->vendorTransactionService = $vendorTransactionService;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $incrementId = $this->checkoutSession->getLastRealOrderId();
        if ($incrementId) {
            $order = $this->orderHelper->getOrderByIncrementId($incrementId);
            if ($order) {
                // Hitting this URL is not proof the transaction is dead — AlyaPay's transaction
                // stays PENDING until it is explicitly CANCELED or EXPIRED; "the customer got
                // sent back here" is not a terminal state. Look up the real status by the
                // order's own vendor reference (no transaction_id needed, works even with no
                // redirect params at all) before deciding:
                //  - APPROVED/COMPLETED: AlyaPay actually took the payment, don't cancel.
                //  - CANCELED/EXPIRED/DECLINED/FAILED: confirmed dead, safe to cancel now.
                //  - PENDING/PROCESSING/lookup failed: not resolved yet — leave pending_payment
                //    and let the webhook or cron reconciliation close it out later.
                $apiStatus = null;
                try {
                    $statusResponse = $this->vendorTransactionService->getByVendorReference(
                        (string) $order->getIncrementId(),
                        (int) $order->getStoreId()
                    );
                    $apiStatus = strtoupper($statusResponse['status'] ?? '');
                } catch (\Throwable $e) {
                    $this->logger->warning('AlyaPay cancel: vendor lookup failed, leaving order pending', [
                        'increment_id' => $order->getIncrementId(),
                        'error' => $e->getMessage(),
                    ]);
                }

                if (in_array($apiStatus, self::APPROVED_STATUSES, true)) {
                    $this->logger->info('AlyaPay cancel-url hit but transaction is approved — not cancelling', [
                        'increment_id' => $order->getIncrementId(),
                    ]);
                    return $this->resultRedirectFactory->create()->setUrl($this->urlBuilder->getUrl('checkout/onepage/success'));
                }

                if (in_array($apiStatus, self::TERMINAL_FAILED_STATUSES, true)) {
                    $this->orderHelper->cancelOrder($order, 'Customer cancelled payment');
                } else {
                    // Not yet confirmed dead on AlyaPay's side (still PENDING) — the order stays
                    // pending_payment and webhook/cron will close it once confirmed. But the
                    // customer already cancelled on AlyaPay's checkout, so tell them that plainly
                    // instead of a vague "we're checking" message.
                    $this->logger->info('AlyaPay cancel-url hit, transaction still pending — order left untouched', [
                        'increment_id' => $order->getIncrementId(),
                        'api_status' => $apiStatus,
                    ]);
                }
                $this->messageManager->addNoticeMessage(__('You canceled your order.'));
            }
        }

        return $this->resultRedirectFactory->create()->setUrl($this->urlBuilder->getUrl('checkout'));
    }
}
