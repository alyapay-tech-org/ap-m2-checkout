<?php
/**
 * Unit tests for SessionIntentService
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Test\Unit\Model\Api;

use AlyaPay\Payment\Model\Api\Http\Client;
use AlyaPay\Payment\Model\Api\SessionIntentService;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SessionIntentServiceTest extends TestCase
{
    private Client&MockObject $client;

    private LoggerInterface&MockObject $logger;

    private SessionIntentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createMock(Client::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new SessionIntentService($this->client, $this->logger);
    }

    public function testCreateSessionIntentCallsClientWithCorrectPath(): void
    {
        $order = $this->createOrderMock('000000001', 100.0, 'MAD', 1);
        $order->method('getAllVisibleItems')->willReturn([]);

        $this->client->expects($this->once())
            ->method('postWithApiKey')
            ->with(
                '/api/v1/public/session-intents',
                $this->callback(function ($payload) {
                    return $payload['currency'] === 'MAD'
                        && $payload['total'] === 100.0
                        && $payload['vendorReference'] === '000000001';
                }),
                1
            )
            ->willReturn(['checkout_url' => 'https://example.com', 'checkout_token' => 'tok']);

        $this->service->createSessionIntent($order);
    }

    public function testCreateSessionIntentPayloadIncludesProductItems(): void
    {
        $item1 = $this->createOrderItemMock('SKU001', 1, 'Product 1', 50.0);
        $item2 = $this->createOrderItemMock('SKU002', 2, 'Product 2', 25.0);

        $order = $this->createOrderMock('000000002', 100.0, 'MAD', 1);
        $order->method('getAllVisibleItems')->willReturn([$item1, $item2]);
        $order->method('getShippingAmount')->willReturn(0.0);
        $order->method('getShippingTaxAmount')->willReturn(0.0);

        $capturedPayload = null;
        $this->client->method('postWithApiKey')->willReturnCallback(function ($path, $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;
            return ['checkout_url' => 'x', 'checkout_token' => 'y'];
        });

        $this->service->createSessionIntent($order);

        $this->assertCount(2, $capturedPayload['items']);
        $this->assertEquals('SKU001', $capturedPayload['items'][0]['id']);
        $this->assertEquals('Product 1', $capturedPayload['items'][0]['name']);
        $this->assertEquals(50.0, $capturedPayload['items'][0]['price']);
        $this->assertEquals(1, $capturedPayload['items'][0]['quantity']);
        $this->assertEquals('SKU002', $capturedPayload['items'][1]['id']);
        $this->assertEquals(2, $capturedPayload['items'][1]['quantity']);
    }

    public function testCreateSessionIntentPayloadIncludesShippingWhenAmountGreaterThanZero(): void
    {
        $order = $this->createOrderMock('000000003', 110.0, 'MAD', 1);
        $order->method('getAllVisibleItems')->willReturn([]);
        $order->method('getShippingAmount')->willReturn(10.0);
        $order->method('getShippingTaxAmount')->willReturn(0.0);
        $order->method('getShippingDescription')->willReturn('Flat Rate - Fixed');

        $capturedPayload = null;
        $this->client->method('postWithApiKey')->willReturnCallback(function ($path, $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;
            return ['checkout_url' => 'x', 'checkout_token' => 'y'];
        });

        $this->service->createSessionIntent($order);

        $this->assertCount(1, $capturedPayload['items']); // shipping only (no products)
        $shippingItem = null;
        foreach ($capturedPayload['items'] as $item) {
            if ($item['id'] === 'shipping') {
                $shippingItem = $item;
                break;
            }
        }
        $this->assertNotNull($shippingItem);
        $this->assertEquals('Flat Rate - Fixed', $shippingItem['name']);
        $this->assertEquals(10.0, $shippingItem['price']);
        $this->assertEquals(1, $shippingItem['quantity']);
    }

    public function testCreateSessionIntentNoShippingWhenZero(): void
    {
        $item = $this->createOrderItemMock('SKU1', 1, 'Product', 100.0);
        $order = $this->createOrderMock('000000004', 100.0, 'MAD', 1);
        $order->method('getAllVisibleItems')->willReturn([$item]);
        $order->method('getShippingAmount')->willReturn(0.0);
        $order->method('getShippingTaxAmount')->willReturn(0.0);

        $capturedPayload = null;
        $this->client->method('postWithApiKey')->willReturnCallback(function ($path, $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;
            return ['checkout_url' => 'x', 'checkout_token' => 'y'];
        });

        $this->service->createSessionIntent($order);

        $hasShipping = false;
        foreach ($capturedPayload['items'] as $item) {
            if ($item['id'] === 'shipping') {
                $hasShipping = true;
                break;
            }
        }
        $this->assertFalse($hasShipping);
    }

    public function testCreateSessionIntentEmptyOrderUsesFallbackItem(): void
    {
        $order = $this->createOrderMock('000000005', 75.5, 'MAD', 1);
        $order->method('getAllVisibleItems')->willReturn([]);
        $order->method('getShippingAmount')->willReturn(0.0);
        $order->method('getShippingTaxAmount')->willReturn(0.0);

        $capturedPayload = null;
        $this->client->method('postWithApiKey')->willReturnCallback(function ($path, $payload) use (&$capturedPayload) {
            $capturedPayload = $payload;
            return ['checkout_url' => 'x', 'checkout_token' => 'y'];
        });

        $this->service->createSessionIntent($order);

        $this->assertCount(1, $capturedPayload['items']);
        $this->assertEquals('order_000000005', $capturedPayload['items'][0]['id']);
        $this->assertStringContainsString('000000005', $capturedPayload['items'][0]['name']);
        $this->assertEquals(75.5, $capturedPayload['items'][0]['price']);
        $this->assertEquals(1, $capturedPayload['items'][0]['quantity']);
    }

    private function createOrderMock(string $incrementId, float $grandTotal, string $currency, int $storeId): Order&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getIncrementId')->willReturn($incrementId);
        $order->method('getGrandTotal')->willReturn($grandTotal);
        $order->method('getOrderCurrencyCode')->willReturn($currency);
        $order->method('getStoreId')->willReturn($storeId);
        return $order;
    }

    private function createOrderItemMock(string $sku, int $qty, string $name, float $price): MockObject
    {
        $item = $this->createMock(\Magento\Sales\Model\Order\Item::class);
        $item->method('getSku')->willReturn($sku);
        $item->method('getItemId')->willReturn(1);
        $item->method('getName')->willReturn($name);
        $item->method('getPriceInclTax')->willReturn($price);
        $item->method('getQtyOrdered')->willReturn($qty);
        return $item;
    }
}
