<?php
/**
 * Success return controller - handles callback from AlyaPay (status + transaction_id in URL)
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Controller\Result;

use AlyaPay\Payment\Model\Api\StatusService;
use AlyaPay\Payment\Model\Config;
use AlyaPay\Payment\Model\Error\Context;
use AlyaPay\Payment\Model\Error\Handler as ErrorHandler;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

class Success implements HttpGetActionInterface
{
    private const APPROVED_STATUSES = ['APPROVED', 'COMPLETED'];

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var StatusService
     */
    private $statusService;

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
     * @var RequestInterface
     */
    private $request;

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
     * @param StatusService $statusService
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     * @param \AlyaPay\Payment\Helper\Order $orderHelper
     * @param Config $config
     * @param UrlInterface $urlBuilder
     * @param RequestInterface $request
     * @param LoggerInterface $logger
     * @param ErrorHandler $errorHandler
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        StatusService $statusService,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        \AlyaPay\Payment\Helper\Order $orderHelper,
        Config $config,
        UrlInterface $urlBuilder,
        RequestInterface $request,
        LoggerInterface $logger,
        ErrorHandler $errorHandler
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->statusService = $statusService;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->orderHelper = $orderHelper;
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
        $this->request = $request;
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

        $urlStatus = strtoupper((string) ($this->request->getParam('status') ?? ''));
        $transactionId = $this->request->getParam('transaction_id') ?? $this->request->getParam('transactionId');
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

        if ($urlStatus === 'FAILURE') {
            $this->messageManager->addErrorMessage(__('Payment failed. Please try again or choose another payment method.'));
            $this->orderHelper->cancelOrder($order, 'Payment failed (URL status: FAILURE)');
            $this->orderHelper->restoreQuote();
            return $redirect;
        }

        if ($urlStatus === 'SUCCESS' && $transactionId) {
            try {
                $statusResponse = $this->statusService->getStatus($transactionId, (int) $order->getStoreId());
                $apiStatus = strtoupper($statusResponse['status'] ?? '');

                if (in_array($apiStatus, self::APPROVED_STATUSES)) {
                    $storeId = (int) $order->getStoreId();
                    $comment = (string) __('AlyaPay payment approved (fallback). Transaction ID: %1', $transactionId);
                    $targetStatus = $this->config->getApprovedStatus($storeId);
                    $this->orderHelper->approveAndCaptureOrder($order, (string) $transactionId, $targetStatus, $comment);
                    $this->logger->info('AlyaPay payment approved (Success fallback)', [
                        'increment_id' => $order->getIncrementId(),
                        'transaction_id' => $transactionId,
                        'api_status' => $apiStatus,
                    ]);
                    $redirect->setUrl($this->urlBuilder->getUrl('checkout/onepage/success'));
                    return $redirect;
                }

                $failedStatuses = ['CANCELED', 'EXPIRED', 'DECLINED', 'FAILED'];
                if (in_array($apiStatus, $failedStatuses)) {
                    $this->messageManager->addErrorMessage(
                        __('Payment was not successful (status: %1).', $apiStatus)
                    );
                    $this->orderHelper->cancelOrder($order, 'Payment failed: ' . $apiStatus);
                    $this->orderHelper->restoreQuote();
                    return $redirect;
                }

                $this->messageManager->addNoticeMessage(
                    __('Payment status: %1. Your order is being processed.', $apiStatus)
                );
                $redirect->setUrl($this->urlBuilder->getUrl('checkout/onepage/success'));
            } catch (\Throwable $e) {
                $result = $this->errorHandler->handle($e, Context::STATUS_CHECK);
                $this->messageManager->addErrorMessage($result->getUserMessage());
                $this->orderHelper->cancelOrder($order, 'Status verification failed: ' . $e->getMessage());
                $this->orderHelper->restoreQuote();
            }
            return $redirect;
        }

        if ($urlStatus === 'CANCELED' || $urlStatus === 'EXPIRED') {
            $this->messageManager->addWarningMessage(
                __('Payment was %1. Webhooks will update order status if needed.', strtolower($urlStatus))
            );
            $this->orderHelper->restoreQuote();
            $redirect->setPath('checkout');
            return $redirect;
        }

        return $redirect;
    }
}
