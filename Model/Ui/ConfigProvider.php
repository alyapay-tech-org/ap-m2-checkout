<?php
/**
 * Checkout config provider - no sensitive data
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Ui;

use AlyaPay\Payment\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigProvider implements ConfigProviderInterface
{
    private const XML_PATH_ACTIVE = 'payment/alyapay/active';
    private const XML_PATH_TITLE = 'payment/alyapay/title';
    private const XML_PATH_WIDGET_CHECKOUT_ENABLED = 'payment/alyapay/widget_checkout_enabled';
    private const XML_PATH_WIDGET_THEME = 'payment/alyapay/widget_theme';
    private const XML_PATH_WIDGET_CURRENCY = 'payment/alyapay/widget_currency';
    private const XML_PATH_WIDGET_VARIANT = 'payment/alyapay/widget_variant';
    private const XML_PATH_WIDGET_DETAIL = 'payment/alyapay/widget_detail';
    private const XML_PATH_WIDGET_LOGO_POSITION = 'payment/alyapay/widget_logo_position';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Config
     */
    private $alyapayConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param Config $alyapayConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        Config $alyapayConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->alyapayConfig = $alyapayConfig;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        $isActive = (bool) $this->scopeConfig->getValue(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE);
        if (!$isActive) {
            return [];
        }

        $widgetEnabled = (bool) $this->scopeConfig->getValue(self::XML_PATH_WIDGET_CHECKOUT_ENABLED, ScopeInterface::SCOPE_STORE);
        $widgetTheme = $this->scopeConfig->getValue(self::XML_PATH_WIDGET_THEME, ScopeInterface::SCOPE_STORE) ?: 'light';
        $widgetVariant = $this->scopeConfig->getValue(self::XML_PATH_WIDGET_VARIANT, ScopeInterface::SCOPE_STORE) ?: 'default';
        $widgetDetail = $this->scopeConfig->getValue(self::XML_PATH_WIDGET_DETAIL, ScopeInterface::SCOPE_STORE) ?: 'modal';
        $widgetLogoPosition = $this->scopeConfig->getValue(self::XML_PATH_WIDGET_LOGO_POSITION, ScopeInterface::SCOPE_STORE) ?: 'right';
        $widgetCurrency = $this->scopeConfig->getValue(self::XML_PATH_WIDGET_CURRENCY, ScopeInterface::SCOPE_STORE);
        if (empty(trim((string) $widgetCurrency))) {
            try {
                $widgetCurrency = $this->storeManager->getStore()->getBaseCurrencyCode();
            } catch (\Exception $e) {
                $widgetCurrency = 'MAD';
            }
        }
        $widgetLang = $this->alyapayConfig->getWidgetLang();

        return [
            'payment' => [
                'alyapay' => [
                    'active' => $isActive,
                    'title' => $this->scopeConfig->getValue(self::XML_PATH_TITLE, ScopeInterface::SCOPE_STORE) ?: 'AlyaPay',
                    'defaultRedirectUrl' => $this->urlBuilder->getUrl('alyapay/redirect'),
                    'widget' => [
                        'enabled' => $widgetEnabled,
                        'theme' => $widgetTheme,
                        'variant' => $widgetVariant,
                        'detail' => $widgetDetail,
                        'logo_position' => $widgetLogoPosition,
                        'currency' => trim($widgetCurrency),
                        'lang' => $widgetLang,
                    ],
                ],
            ],
        ];
    }
}
