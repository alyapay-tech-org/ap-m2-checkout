<?php
/**
 * Checkout config provider - no sensitive data
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Ui;

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
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
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
        $widgetCurrency = $this->scopeConfig->getValue(self::XML_PATH_WIDGET_CURRENCY, ScopeInterface::SCOPE_STORE);
        if (empty(trim((string) $widgetCurrency))) {
            try {
                $widgetCurrency = $this->storeManager->getStore()->getBaseCurrencyCode();
            } catch (\Exception $e) {
                $widgetCurrency = 'MAD';
            }
        }

        return [
            'payment' => [
                'alyapay' => [
                    'active' => $isActive,
                    'title' => $this->scopeConfig->getValue(self::XML_PATH_TITLE, ScopeInterface::SCOPE_STORE) ?: 'AlyaPay',
                    'defaultRedirectUrl' => $this->urlBuilder->getUrl('alyapay/redirect'),
                    'widget' => [
                        'enabled' => $widgetEnabled,
                        'theme' => $widgetTheme,
                        'currency' => trim($widgetCurrency),
                    ],
                ],
            ],
        ];
    }
}
