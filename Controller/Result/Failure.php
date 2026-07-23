<?php
/**
 * Failure return controller
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

class Failure implements HttpGetActionInterface
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
     * @var ManagerInterface
     */
    private $messageManager;

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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CheckoutSession $checkoutSession
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     * @param \AlyaPay\Payment\Helper\Order $orderHelper
     * @param UrlInterface $urlBuilder
     * @param VendorTransactionService $vendorTransactionService
     * @param LoggerInterface $logger
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        \AlyaPay\Payment\Helper\Order $orderHelper,
        UrlInterface $urlBuilder,
        VendorTransactionService $vendorTransactionService,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->orderHelper = $orderHelper;
        $this->urlBuilder = $urlBuilder;
        $this->vendorTransactionService = $vendorTransactionService;
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
                // "Failure" here is a Magento-side redirect outcome, not a real AlyaPay
                // transaction state — AlyaPay only ever reaches CANCELED or EXPIRED as a
                // terminal negative state; until then it's still PENDING and could still be
                // completed. Verify via vendor reference (no transaction_id needed) before
                // deciding whether to cancel.
                $apiStatus = null;
                try {
                    $statusResponse = $this->vendorTransactionService->getByVendorReference(
                        (string) $order->getIncrementId(),
                        (int) $order->getStoreId()
                    );
                    $apiStatus = strtoupper($statusResponse['status'] ?? '');
                } catch (\Throwable $e) {
                    $this->logger->warning('AlyaPay failure: vendor lookup failed, leaving order pending', [
                        'increment_id' => $order->getIncrementId(),
                        'error' => $e->getMessage(),
                    ]);
                }

                if (in_array($apiStatus, self::APPROVED_STATUSES, true)) {
                    $this->logger->info('AlyaPay failure-url hit but transaction is approved — not cancelling', [
                        'increment_id' => $order->getIncrementId(),
                    ]);
                    return $this->resultRedirectFactory->create()->setUrl($this->urlBuilder->getUrl('checkout/onepage/success'));
                }

                if (in_array($apiStatus, self::TERMINAL_FAILED_STATUSES, true)) {
                    $this->orderHelper->cancelOrder($order, 'Payment failed');
                } else {
                    // Not yet confirmed dead on AlyaPay's side (still PENDING) — the order stays
                    // pending_payment and webhook/cron will close it once confirmed. But the
                    // customer already saw a failure on AlyaPay's checkout, so tell them that
                    // plainly instead of a vague "we're checking" message.
                    $this->logger->info('AlyaPay failure-url hit, transaction still pending — order left untouched', [
                        'increment_id' => $order->getIncrementId(),
                        'api_status' => $apiStatus,
                    ]);
                }
                $this->messageManager->addErrorMessage(
                    __('Payment failed. Please try again or choose another payment method.')
                );
            }
        }

        return $this->resultRedirectFactory->create()->setUrl($this->urlBuilder->getUrl('checkout'));
    }
}
