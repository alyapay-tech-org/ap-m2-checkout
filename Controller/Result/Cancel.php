<?php
/**
 * Cancel return controller
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Controller\Result;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;

class Cancel implements HttpGetActionInterface
{
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
     * @param CheckoutSession $checkoutSession
     * @param RedirectFactory $resultRedirectFactory
     * @param \AlyaPay\Payment\Helper\Order $orderHelper
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        RedirectFactory $resultRedirectFactory,
        \AlyaPay\Payment\Helper\Order $orderHelper,
        UrlInterface $urlBuilder
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->orderHelper = $orderHelper;
        $this->urlBuilder = $urlBuilder;
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
                $this->orderHelper->cancelOrder($order, 'Customer cancelled payment');
            }
        }

        return $this->resultRedirectFactory->create()->setUrl($this->urlBuilder->getUrl('checkout'));
    }
}
