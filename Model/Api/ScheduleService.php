<?php
/**
 * AlyaPay Transaction Schedules API Service
 * GET /api/v1/public/transactions/{transactionId}/schedules
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Api;

use AlyaPay\Payment\Model\Api\Http\Client;

class ScheduleService
{
    private const API_PATH = '/api/v1/public/transactions/%s/schedules';

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
     * Get transaction schedules (installments)
     *
     * @param string $transactionId AlyaPay transaction ID
     * @param int|null $storeId
     * @return array{transactionId?: string, vendorReference?: string, clientPhone?: string, total?: float, installments?: array}
     */
    public function getSchedules(string $transactionId, ?int $storeId = null): array
    {
        $path = sprintf(self::API_PATH, $transactionId);
        return $this->client->getWithApiKey($path, $storeId);
    }
}
