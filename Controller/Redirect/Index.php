<?php
/**
 * Redirect controller - creates session-intent (1-step), redirects to AlyaPay checkout
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Controller\Redirect;

use AlyaPay\Payment\Model\Api\SessionIntentService;
use AlyaPay\Payment\Model\Error\Context;
use AlyaPay\Payment\Model\Error\Handler as ErrorHandler;
use AlyaPay\Payment\Model\PaymentMethod;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;

class Index implements HttpGetActionInterface
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var SessionIntentService
     */
    private $sessionIntentService;

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
     * @var ErrorHandler
     */
    private $errorHandler;

    /**
     * @param CheckoutSession $checkoutSession
     * @param SessionIntentService $sessionIntentService
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     * @param \AlyaPay\Payment\Helper\Order $orderHelper
     * @param UrlInterface $urlBuilder
     * @param ErrorHandler $errorHandler
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        SessionIntentService $sessionIntentService,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        \AlyaPay\Payment\Helper\Order $orderHelper,
        UrlInterface $urlBuilder,
        ErrorHandler $errorHandler
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->sessionIntentService = $sessionIntentService;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->orderHelper = $orderHelper;
        $this->urlBuilder = $urlBuilder;
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
            $this->messageManager->addErrorMessage(__('Unable to find the order.'));
            return $redirect;
        }

        $order = $this->orderHelper->getOrderByIncrementId($incrementId);
        if (!$order || !$order->getId()) {
            $this->messageManager->addErrorMessage(__('Order not found.'));
            return $redirect;
        }

        try {
            $response = $this->sessionIntentService->createSessionIntent($order);

            $checkoutUrl = $response['checkout_url'] ?? null;
            $checkoutToken = $response['checkout_token'] ?? null;
            $paymentIntentId = $response['payment_intent_id'] ?? null;

            if (!$checkoutUrl || !$checkoutToken) {
                throw new \Exception('Invalid session-intent response');
            }

            $successUrl = $this->urlBuilder->getUrl('alyapay/result/success');
            $checkoutUrl .= (strpos($checkoutUrl, '?') !== false ? '&' : '?') . 'redirect_url=' . rawurlencode($successUrl);

            $payment = $order->getPayment();
            $payment->setAdditionalInformation(PaymentMethod::CHECKOUT_TOKEN, $checkoutToken);
            $payment->setAdditionalInformation(PaymentMethod::PAYMENT_INTENT_ID, $paymentIntentId);
            $payment->save();

            return $this->resultRedirectFactory->create()->setUrl($checkoutUrl);
        } catch (\Throwable $e) {
            $result = $this->errorHandler->handle($e, Context::SESSION_INTENT);
            $this->messageManager->addErrorMessage($result->getUserMessage());
            $this->orderHelper->cancelOrder($order, 'AlyaPay redirect failed: ' . $e->getMessage());
            $this->orderHelper->restoreQuote();
        }

        return $redirect;
    }
}
