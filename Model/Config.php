<?php
/**
 * AlyaPay configuration
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Config
{
    private const XML_PATH_ACTIVE = 'payment/alyapay/active';
    private const XML_PATH_TITLE = 'payment/alyapay/title';
    private const XML_PATH_API_KEY = 'payment/alyapay/api_key';
    private const XML_PATH_API_BASE_URL = 'payment/alyapay/api_base_url';
    private const XML_PATH_WEBHOOK_URL = 'payment/alyapay/webhook_url';
    private const XML_PATH_WEBHOOK_SECRET = 'payment/alyapay/webhook_secret';
    private const XML_PATH_TRANSACTION_EXPIRY = 'payment/alyapay/transaction_expiry';
    private const XML_PATH_AMOUNT_MIN = 'payment/alyapay/amount_min';
    private const XML_PATH_AMOUNT_MAX = 'payment/alyapay/amount_max';
    private const XML_PATH_STATUS_APPROVED = 'payment/alyapay/status_approved';
    private const XML_PATH_STATUS_CANCELED = 'payment/alyapay/status_canceled';
    private const XML_PATH_STATUS_EXPIRED = 'payment/alyapay/status_expired';
    private const XML_PATH_DEBUG = 'payment/alyapay/debug';
    private const XML_PATH_WIDGET_CHECKOUT_ENABLED = 'payment/alyapay/widget_checkout_enabled';
    private const XML_PATH_WIDGET_PRODUCT_ENABLED = 'payment/alyapay/widget_product_enabled';
    private const XML_PATH_WIDGET_CART_ENABLED = 'payment/alyapay/widget_cart_enabled';
    private const XML_PATH_WIDGET_THEME = 'payment/alyapay/widget_theme';
    private const XML_PATH_WIDGET_CURRENCY = 'payment/alyapay/widget_currency';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        EncryptorInterface $encryptor,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isActive(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getApiKey(?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_API_KEY, ScopeInterface::SCOPE_STORE, $storeId);
        if (empty($value)) {
            return null;
        }
        $decrypted = $this->encryptor->decrypt($value);
        return $decrypted !== false ? trim($decrypted) : trim($value);
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getApiBaseUrl(?int $storeId = null): string
    {
        $url = $this->scopeConfig->getValue(self::XML_PATH_API_BASE_URL, ScopeInterface::SCOPE_STORE, $storeId);
        return $url ? rtrim($url, '/') : 'https://sandbox-api.alyapay.com';
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isDebugEnabled(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_PATH_DEBUG, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private const WEBHOOK_PATH = '/alyapay/result/webhook';

    /**
     * Get webhook URL (full URL for AlyaPay to POST to)
     * Merchant enters their website (e.g. https://store.com); we append /alyapay/result/webhook.
     * When empty, uses store base URL + path.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getWebhookUrl(?int $storeId = null): string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_WEBHOOK_URL, ScopeInterface::SCOPE_STORE, $storeId);
        if (!empty(trim((string) $value))) {
            $base = rtrim($value, '/');
            return str_ends_with($base, self::WEBHOOK_PATH)
                ? $base
                : $base . self::WEBHOOK_PATH;
        }
        try {
            $store = $this->storeManager->getStore($storeId);
            return rtrim($store->getBaseUrl(), '/') . self::WEBHOOK_PATH;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Transaction expiry in minutes (0 = no expiry)
     *
     * @param int|null $storeId
     * @return int
     */
    public function getTransactionExpiry(?int $storeId = null): int
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_TRANSACTION_EXPIRY, ScopeInterface::SCOPE_STORE, $storeId);
        if ($value === null || $value === '') {
            return 30;
        }
        return (int) $value;
    }

    /**
     * Minimum order amount for AlyaPay (below this = hidden). Store base currency.
     *
     * @param int|null $storeId
     * @return float
     */
    public function getAmountMin(?int $storeId = null): float
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_AMOUNT_MIN, ScopeInterface::SCOPE_STORE, $storeId);
        return $value !== null && $value !== '' ? (float) $value : 500.0;
    }

    /**
     * Maximum order amount for AlyaPay (above this = hidden). Store base currency.
     *
     * @param int|null $storeId
     * @return float
     */
    public function getAmountMax(?int $storeId = null): float
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_AMOUNT_MAX, ScopeInterface::SCOPE_STORE, $storeId);
        return $value !== null && $value !== '' ? (float) $value : 15000.0;
    }

    /**
     * Check if amount is within AlyaPay min/max range
     *
     * @param float $amount
     * @param int|null $storeId
     * @return bool
     */
    public function isAmountInRange(float $amount, ?int $storeId = null): bool
    {
        $min = $this->getAmountMin($storeId);
        $max = $this->getAmountMax($storeId);
        return $amount >= $min && $amount <= $max;
    }

    /**
     * Get webhook secret for signature verification (decrypted)
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getWebhookSecret(?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_WEBHOOK_SECRET, ScopeInterface::SCOPE_STORE, $storeId);
        if (empty($value)) {
            return null;
        }
        $decrypted = $this->encryptor->decrypt($value);
        return $decrypted !== false ? trim($decrypted) : trim($value);
    }

    /**
     * Get Magento order status for AlyaPay APPROVED
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApprovedStatus(?int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_STATUS_APPROVED, ScopeInterface::SCOPE_STORE, $storeId) ?: 'processing');
    }

    /**
     * Get Magento order status for AlyaPay CANCELED
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCanceledStatus(?int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_STATUS_CANCELED, ScopeInterface::SCOPE_STORE, $storeId) ?: 'canceled');
    }

    /**
     * Get Magento order status for AlyaPay EXPIRED
     *
     * @param int|null $storeId
     * @return string
     */
    public function getExpiredStatus(?int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_STATUS_EXPIRED, ScopeInterface::SCOPE_STORE, $storeId) ?: 'canceled');
    }

    /**
     * Whether checkout widget is enabled (payment method only)
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isWidgetEnabled(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_PATH_WIDGET_CHECKOUT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Whether credit promo widget is enabled on product page
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCreditPromoProductEnabled(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_PATH_WIDGET_PRODUCT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Whether credit promo widget is enabled on cart
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCreditPromoCartEnabled(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_PATH_WIDGET_CART_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Whether any widget (checkout, product, cart) is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAnyWidgetEnabled(?int $storeId = null): bool
    {
        return $this->isWidgetEnabled($storeId)
            || $this->isCreditPromoProductEnabled($storeId)
            || $this->isCreditPromoCartEnabled($storeId);
    }

    /**
     * Widget theme (light, dark, light-plain, dark-plain)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getWidgetTheme(?int $storeId = null): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_PATH_WIDGET_THEME, ScopeInterface::SCOPE_STORE, $storeId) ?: 'light');
    }

    /**
     * Widget currency config. Empty = use store base currency.
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getWidgetCurrency(?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_WIDGET_CURRENCY, ScopeInterface::SCOPE_STORE, $storeId);
        return !empty(trim((string) $value)) ? trim($value) : null;
    }

    /**
     * Effective currency for widget (config or store base)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getWidgetCurrencyEffective(?int $storeId = null): string
    {
        $configured = $this->getWidgetCurrency($storeId);
        if ($configured !== null) {
            return $configured;
        }
        try {
            return (string) $this->storeManager->getStore($storeId)->getBaseCurrencyCode();
        } catch (\Exception $e) {
            return 'MAD';
        }
    }
}
