<?php
/**
 * Unit tests for VendorTransactionService
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Test\Unit\Model\Api;

use AlyaPay\Payment\Model\Api\Http\Client;
use AlyaPay\Payment\Model\Api\VendorTransactionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VendorTransactionServiceTest extends TestCase
{
    /** @var Client|MockObject */
    private $client;

    private VendorTransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(Client::class);
        $this->service = new VendorTransactionService($this->client);
    }

    public function testGetByVendorReferenceCallsCorrectPath(): void
    {
        $this->client->expects($this->once())
            ->method('getWithApiKey')
            ->with('/api/v1/public/partner/transactions/vendor/ORDER-000001', 1)
            ->willReturn(['status' => 'APPROVED', 'id' => 'txn_1']);

        $result = $this->service->getByVendorReference('ORDER-000001', 1);
        $this->assertSame(['status' => 'APPROVED', 'id' => 'txn_1'], $result);
    }

    public function testGetByVendorReferenceUrlEncodesVendorReference(): void
    {
        $this->client->expects($this->once())
            ->method('getWithApiKey')
            ->with('/api/v1/public/partner/transactions/vendor/ORDER%2F001', null)
            ->willReturn([]);

        $this->service->getByVendorReference('ORDER/001');
    }
}
