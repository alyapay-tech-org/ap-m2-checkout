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
     * @param Config $config
     * @param PartnerConfigService $partnerConfigService
     * @param Handler $errorHandler
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        Config $config,
        PartnerConfigService $partnerConfigService,
        Handler $errorHandler,
        ManagerInterface $messageManager
    ) {
        $this->config = $config;
        $this->partnerConfigService = $partnerConfigService;
        $this->errorHandler = $errorHandler;
        $this->messageManager = $messageManager;
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
        }
    }
}
