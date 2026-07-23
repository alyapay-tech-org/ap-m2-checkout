<?php
/**
 * Sync webhook URL and transaction expiry to AlyaPay when payment config is saved
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Observer;

use AlyaPay\Payment\Model\Api\PartnerConfigService;
use AlyaPay\Payment\Model\Config;
use AlyaPay\Payment\Model\Error\Context;
use AlyaPay\Payment\Model\Error\Handler;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

class AdminSystemConfigChangedPayment implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var PartnerConfigService
     */
    private $partnerConfigService;

    /**
     * @var Handler
     */
    private $errorHandler;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @param Config $config
     * @param PartnerConfigService $partnerConfigService
     * @param Handler $errorHandler
     * @param ManagerInterface $messageManager
     * @param WriterInterface $configWriter
     */
    public function __construct(
        Config $config,
        PartnerConfigService $partnerConfigService,
        Handler $errorHandler,
        ManagerInterface $messageManager,
        WriterInterface $configWriter
    ) {
        $this->config = $config;
        $this->partnerConfigService = $partnerConfigService;
        $this->errorHandler = $errorHandler;
        $this->messageManager = $messageManager;
        $this->configWriter = $configWriter;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        if (!$this->config->isActive() || !$this->config->getApiKey()) {
            return;
        }

        $payload = [
            'webhookUrl' => $this->config->getWebhookUrl(),
            'webhookEnabled' => !empty(trim($this->config->getWebhookUrl())),
            'transactionExpiry' => $this->config->getTransactionExpiry(),
            'generateNewSecret' => false,
        ];

        try {
            $this->partnerConfigService->updateConfig($payload);
        } catch (\Throwable $e) {
            $result = $this->errorHandler->handle($e, Context::PARTNER_CONFIG);
            $this->messageManager->addErrorMessage($result->getUserMessage());
            return;
        }

        $this->syncAmountLimitsFromBackend();
    }

    /**
     * Pull amount_min/amount_max/transaction_expiry from AlyaPay — these are set by AlyaPay
     * per merchant API key, not admin-editable (fields are read-only in system.xml). Mirrors
     * the AlyaPay PrestaShop module's fetchAndSyncPartnerConfig().
     */
    private function syncAmountLimitsFromBackend(): void
    {
        try {
            $response = $this->partnerConfigService->getConfig();
        } catch (\Throwable $e) {
            $result = $this->errorHandler->handle($e, Context::PARTNER_CONFIG);
            $this->messageManager->addErrorMessage($result->getUserMessage());
            return;
        }

        if (isset($response['minAmount'])) {
            $this->configWriter->save(
                'payment/alyapay/amount_min',
                (string) $response['minAmount'],
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );
        }
        if (isset($response['maxAmount'])) {
            $this->configWriter->save(
                'payment/alyapay/amount_max',
                (string) $response['maxAmount'],
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );
        }
        if (array_key_exists('transactionExpiry', $response)) {
            $this->configWriter->save(
                'payment/alyapay/transaction_expiry',
                $response['transactionExpiry'] !== null ? (string) $response['transactionExpiry'] : '30',
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );
        }
    }
}
