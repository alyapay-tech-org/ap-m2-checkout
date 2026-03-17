<?php
declare(strict_types=1);

namespace AlyaPay\Payment\Block\Checkout;

use AlyaPay\Payment\Helper\Order as OrderHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;

class SuccessInfo extends Template
{
    public const PAYMENT_METHOD_ALYAPAY = 'alyapay';

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderHelper $orderHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->orderHelper = $orderHelper;
    }

    /**
     * Only render block when order was placed with AlyaPay
     */
    protected function _toHtml()
    {
        $order = $this->getOrder();
        if (!$order || !$order->getPayment() || $order->getPayment()->getMethod() !== self::PAYMENT_METHOD_ALYAPAY) {
            return '';
        }
        return parent::_toHtml();
    }

    public function getOrder(): ?OrderInterface
    {
        $incrementId = $this->checkoutSession->getLastRealOrderId();
        return $incrementId ? $this->orderHelper->getOrderByIncrementId($incrementId) : null;
    }

    public function getFormattedTotal(OrderInterface $order): string
    {
        $total = (float) $order->getGrandTotal();
        $currencyCode = $order->getOrderCurrencyCode() ?: 'MAD';
        return number_format($total, 2, ',', ' ') . ' ' . $currencyCode;
    }
}
