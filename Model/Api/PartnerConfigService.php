<?php
/**
 * AlyaPay Partner Config API
 * GET/PUT /api/v1/public/partner/config
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Api;

use AlyaPay\Payment\Model\Api\Http\Client;
use AlyaPay\Payment\Model\Config;

class PartnerConfigService
{
    private const API_PATH = '/api/v1/public/partner/config';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Client $client
     * @param Config $config
     */
    public function __construct(
        Client $client,
        Config $config
    ) {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Update partner config (webhook, transaction expiry)
     *
     * @param array $data Keys: webhookUrl, webhookEnabled, transactionExpiry, generateNewSecret
     * @param int|null $storeId
     * @return array
     */
    public function updateConfig(array $data, ?int $storeId = null): array
    {
        return $this->client->putWithApiKey(self::API_PATH, $data, $storeId);
    }

    /**
     * Fetch partner config from AlyaPay (source of truth for amount limits, transaction expiry)
     *
     * @param int|null $storeId
     * @return array{minAmount?: float, maxAmount?: float, transactionExpiry?: int, ...}
     */
    public function getConfig(?int $storeId = null): array
    {
        return $this->client->getWithApiKey(self::API_PATH, $storeId);
    }
}
