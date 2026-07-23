<?php
/**
 * AlyaPay Transaction Lookup by Vendor Reference
 * GET /api/v1/public/partner/transactions/vendor/{vendorReference}
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Api;

use AlyaPay\Payment\Model\Api\Http\Client;

class VendorTransactionService
{
    private const API_PATH = '/api/v1/public/partner/transactions/vendor/%s';

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
     * Look up a transaction by the vendor reference (Magento order increment id).
     * Requires API key auth, unlike the public transaction-status endpoint.
     *
     * @param string $vendorReference
     * @param int|null $storeId
     * @return array{status: string, id: string, vendorReference: string, ...}
     */
    public function getByVendorReference(string $vendorReference, ?int $storeId = null): array
    {
        $path = sprintf(self::API_PATH, rawurlencode($vendorReference));
        return $this->client->getWithApiKey($path, $storeId);
    }
}
