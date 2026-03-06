<?php
/**
 * Credit promo widget for cart (BNPL simulation below total)
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Block\Cart;

use AlyaPay\Payment\Model\Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class CreditPromotion extends Template
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Template\Context $context
     * @param Config $config
     * @param CheckoutSession $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Config $config,
        CheckoutSession $checkoutSession,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        if (!$this->config->isActive() || !$this->config->isCreditPromoCartEnabled()) {
            return '';
        }
        $quote = $this->checkoutSession->getQuote();
        if (!$quote || $quote->getItemsCount() === 0) {
            return '';
        }
        if (!$this->config->isAmountInRange((float) $quote->getGrandTotal())) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * Cart grand total for widget
     */
    public function getWidgetPrice(): string
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote) {
            return '0.00';
        }
        $total = $quote->getGrandTotal();
        return number_format((float) ($total ?? 0), 2, '.', '');
    }

    public function getWidgetCurrency(): string
    {
        return $this->config->getWidgetCurrencyEffective();
    }

    public function getWidgetTheme(): string
    {
        return $this->config->getWidgetTheme();
    }

    public function getWidgetVariant(): string
    {
        return $this->config->getWidgetVariant();
    }

    public function getWidgetDetail(): string
    {
        return $this->config->getWidgetDetail();
    }

    public function getWidgetLogoPosition(): string
    {
        return $this->config->getWidgetLogoPosition();
    }

    public function getWidgetLang(): string
    {
        try {
            $locale = $this->storeManager->getStore()->getLocaleCode() ?: 'en_US';
            if (str_starts_with($locale, 'fr')) {
                return 'fr';
            }
            if (str_starts_with($locale, 'ar')) {
                return 'ar';
            }
        } catch (\Throwable $e) {
        }
        return 'en';
    }
}
