<?php
/**
 * Credit promo widget for product page (BNPL simulation below price)
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Block\Product;

use AlyaPay\Payment\Model\Config;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class CreditPromotion extends Template
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @param Context $context
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $config,
        StoreManagerInterface $storeManager,
        Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->registry = $registry;
    }

    /**
     * @return Product|null
     */
    public function getProduct(): ?Product
    {
        return $this->registry->registry('current_product');
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        if (!$this->config->isActive()
            || !$this->config->isCreditPromoProductEnabled()
            || !$this->getProduct()
        ) {
            return '';
        }
        if (!$this->isProductAmountInRange()) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * Whether product price (or range for configurable) overlaps [min, max]
     */
    public function isProductAmountInRange(): bool
    {
        $product = $this->getProduct();
        if (!$product) {
            return false;
        }
        [$minPrice, $maxPrice] = $this->getProductPriceRange($product);
        $min = $this->config->getAmountMin();
        $max = $this->config->getAmountMax();
        return $maxPrice >= $min && $minPrice <= $max;
    }

    /**
     * Get [min, max] price for product. For simple, both equal. For configurable, from children.
     *
     * @return float[]
     */
    private function getProductPriceRange(Product $product): array
    {
        try {
            $priceInfo = $product->getPriceInfo();
            if (!$priceInfo) {
                return [0.0, 0.0];
            }
            $finalPrice = $priceInfo->getPrice('final_price');
            $amount = $finalPrice->getAmount();
            $value = (float) $amount->getValue();

            if ($product->getTypeId() === 'configurable') {
                $maxValue = $value;
                try {
                    $typeInstance = $product->getTypeInstance();
                    if (method_exists($typeInstance, 'getUsedProducts')) {
                        $children = $typeInstance->getUsedProducts($product);
                        foreach ($children as $child) {
                            if ($child->getPriceInfo()) {
                                $childAmount = $child->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
                                $maxValue = max($maxValue, (float) $childAmount);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $maxValue = $value;
                }
                return [$value, $maxValue];
            }

            return [$value, $value];
        } catch (\Throwable $e) {
            return [0.0, 0.0];
        }
    }

    /**
     * Product final price (incl. tax) for widget
     */
    public function getWidgetPrice(): string
    {
        $product = $this->getProduct();
        if (!$product || !$product->getPriceInfo()) {
            return '0.00';
        }
        try {
            $price = $product->getPriceInfo()->getPrice('final_price');
            $amount = $price->getAmount();
            $value = $amount->getValue();
            return number_format((float) $value, 2, '.', '');
        } catch (\Throwable $e) {
            return '0.00';
        }
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
        return $this->config->getWidgetLang();
    }
}
