<?php
/**
 * AlyaPay Payment Method
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\UrlInterface;
use Magento\Payment\Block\Form;

class PaymentMethod extends AbstractMethod
{
    public const CODE = 'alyapay';

    public const PAYMENT_INTENT_ID = 'alyapay_payment_intent_id';
    public const CHECKOUT_TOKEN = 'alyapay_checkout_token';
    public const TRANSACTION_ID = 'alyapay_transaction_id';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canUseInternal = false;

    /**
     * @var string
     */
    protected $_formBlockType = Form::class;

    /**
     * @var string
     */
    protected $_infoBlockType = \AlyaPay\Payment\Block\Info::class;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param UrlInterface $urlBuilder
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        UrlInterface $urlBuilder,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @inheritdoc
     */
    public function getInstructions(): string
    {
        return (string) __('Pay with AlyaPay');
    }

    /**
     * @inheritdoc
     */
    public function initialize($paymentAction, $stateObject)
    {
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        $stateObject->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);
    }

    /**
     * @inheritdoc
     */
    public function getOrderPlaceRedirectUrl(): string
    {
        return $this->urlBuilder->getUrl('alyapay/redirect');
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null): bool
    {
        if (!parent::isAvailable($quote)) {
            return false;
        }
        if ($quote !== null) {
            $total = (float) $quote->getGrandTotal();
            if (!$this->isAmountInRange($total, (int) $quote->getStoreId())) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if amount is within configured min/max
     */
    private function isAmountInRange(float $amount, int $storeId): bool
    {
        $min = (float) ($this->getConfigData('amount_min', $storeId) ?: 500);
        $max = (float) ($this->getConfigData('amount_max', $storeId) ?: 15000);
        return $amount >= $min && $amount <= $max;
    }
}
