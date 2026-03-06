<?php
/**
 * AlyaPay Transaction Status API Service
 * GET /api/v1/public/transactions/{transactionId}/status
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Api;

use AlyaPay\Payment\Model\Api\Http\Client;

class StatusService
{
    private const API_PATH = '/api/v1/public/transactions/%s/status';

    /**
     * @var Client
     */
    private $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Get transaction status
     *
     * @param string $transactionId
     * @param int|null $storeId
     * @return array{status: string, transaction_id: string, ...}
     */
    public function getStatus(string $transactionId, ?int $storeId = null): array
    {
        $path = sprintf(self::API_PATH, $transactionId);
        return $this->client->getWithApiKey($path, $storeId);
    }
}
