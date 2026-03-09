<?php
/**
 * Unit tests for WebhookProcessor
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Test\Unit\Model;

use AlyaPay\Payment\Helper\Order;
use AlyaPay\Payment\Model\Config;
use AlyaPay\Payment\Model\WebhookProcessor;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order as SalesOrder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WebhookProcessorTest extends TestCase
{
    /** @var Order|MockObject */
    private $orderHelper;

    /** @var Config|MockObject */
    private $config;

    /** @var Json|MockObject */
    private $json;

    /** @var LoggerInterface|MockObject */
    private $logger;

    private WebhookProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderHelper = $this->createMock(Order::class);
        $this->config = $this->createMock(Config::class);
        $this->json = $this->createMock(Json::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->processor = new WebhookProcessor($this->orderHelper, $this->config, $this->json, $this->logger);
    }

    public function testProcessApprovedEventCallsApproveAndCaptureOrder(): void
    {
        $payload = '{"event":"transaction.approved","data":{"id":"txn_123","vendorReference":"000000001"}}';
        $this->json->method('unserialize')->with($payload)->willReturn([
            'event' => 'transaction.approved',
            'data' => ['id' => 'txn_123', 'vendorReference' => '000000001'],
        ]);

        $order = $this->createOrderMock(SalesOrder::STATE_PENDING_PAYMENT, false);
        $this->orderHelper->method('getOrderByIncrementId')->with('000000001')->willReturn($order);
        $this->config->method('getApprovedStatus')->with(1)->willReturn('processing');

        $this->orderHelper->expects($this->once())
            ->method('approveAndCaptureOrder')
            ->with(
                $order,
                'txn_123',
                'processing',
                $this->stringContains('txn_123')
            );

        $this->assertTrue($this->processor->process($payload));
    }

    public function testProcessApprovedOrderAlreadyCanceledReturnsTrueWithoutApproving(): void
    {
        $payload = '{"event":"transaction.approved","data":{"id":"txn_1","vendorReference":"000000001"}}';
        $this->json->method('unserialize')->willReturn([
            'event' => 'transaction.approved',
            'data' => ['id' => 'txn_1', 'vendorReference' => '000000001'],
        ]);

        $order = $this->createOrderMock(SalesOrder::STATE_CANCELED, false);
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $this->orderHelper->expects($this->never())->method('approveAndCaptureOrder');
        $this->assertTrue($this->processor->process($payload));
    }

    public function testProcessApprovedOrderHasInvoicesReturnsTrueWithoutApproving(): void
    {
        $payload = '{"event":"transaction.approved","data":{"id":"txn_1","vendorReference":"000000001"}}';
        $this->json->method('unserialize')->willReturn([
            'event' => 'transaction.approved',
            'data' => ['id' => 'txn_1', 'vendorReference' => '000000001'],
        ]);

        $order = $this->createOrderMock(SalesOrder::STATE_PENDING_PAYMENT, true);
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $this->orderHelper->expects($this->never())->method('approveAndCaptureOrder');
        $this->assertTrue($this->processor->process($payload));
    }

    public function testProcessCancelledEventCallsApplyWebhookStatus(): void
    {
        $payload = '{"event":"transaction.cancelled","data":{"id":"txn_2","vendorReference":"000000002"}}';
        $this->json->method('unserialize')->willReturn([
            'event' => 'transaction.cancelled',
            'data' => ['id' => 'txn_2', 'vendorReference' => '000000002'],
        ]);

        $order = $this->createOrderMock(SalesOrder::STATE_PENDING_PAYMENT, false);
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);
        $this->config->method('getCanceledStatus')->with(1)->willReturn('canceled');

        $this->orderHelper->expects($this->once())
            ->method('applyWebhookStatus')
            ->with($order, 'canceled', $this->stringContains('cancelled'));

        $this->assertTrue($this->processor->process($payload));
    }

    public function testProcessExpiredEventCallsApplyWebhookStatus(): void
    {
        $payload = '{"event":"transaction.expired","data":{"id":"txn_3","vendorReference":"000000003"}}';
        $this->json->method('unserialize')->willReturn([
            'event' => 'transaction.expired',
            'data' => ['id' => 'txn_3', 'vendorReference' => '000000003'],
        ]);

        $order = $this->createOrderMock(SalesOrder::STATE_PENDING_PAYMENT, false);
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);
        $this->config->method('getExpiredStatus')->with(1)->willReturn('canceled');

        $this->orderHelper->expects($this->once())
            ->method('applyWebhookStatus')
            ->with($order, 'canceled', $this->stringContains('expired'));

        $this->assertTrue($this->processor->process($payload));
    }

    public function testProcessUnknownEventReturnsTrue(): void
    {
        $payload = '{"event":"transaction.unknown","data":{"vendorReference":"000000001"}}';
        $this->json->method('unserialize')->willReturn([
            'event' => 'transaction.unknown',
            'data' => ['vendorReference' => '000000001'],
        ]);

        $this->logger->expects($this->once())->method('info')->with('AlyaPay webhook: ignoring event', $this->anything());
        $this->orderHelper->expects($this->never())->method('approveAndCaptureOrder');
        $this->orderHelper->expects($this->never())->method('applyWebhookStatus');

        $this->assertTrue($this->processor->process($payload));
    }

    public function testProcessInvalidPayloadReturnsFalse(): void
    {
        $payload = 'not valid json';
        $this->json->method('unserialize')->willThrowException(new \InvalidArgumentException('Invalid JSON'));

        $this->logger->expects($this->once())->method('error')->with($this->stringContains('webhook error'));
        $this->assertFalse($this->processor->process($payload));
    }

    public function testProcessPayloadNotArrayReturnsFalse(): void
    {
        $payload = '{"event":"test"}';
        $this->json->method('unserialize')->willReturn('string instead of array');

        $this->logger->expects($this->once())->method('error')->with('AlyaPay webhook: invalid payload structure');
        $this->assertFalse($this->processor->process($payload));
    }

    public function testProcessDataNotArrayReturnsFalse(): void
    {
        $payload = '{"event":"transaction.approved","data":"invalid"}';
        $this->json->method('unserialize')->willReturn([
            'event' => 'transaction.approved',
            'data' => 'invalid',
        ]);

        $this->logger->expects($this->once())->method('error')->with('AlyaPay webhook: missing data');
        $this->assertFalse($this->processor->process($payload));
    }

    public function testProcessOrderNotFoundReturnsTrue(): void
    {
        $payload = '{"event":"transaction.approved","data":{"id":"txn_1","vendorReference":"999999999"}}';
        $this->json->method('unserialize')->willReturn([
            'event' => 'transaction.approved',
            'data' => ['id' => 'txn_1', 'vendorReference' => '999999999'],
        ]);

        $this->orderHelper->method('getOrderByIncrementId')->willReturn(null);
        $this->logger->expects($this->once())->method('warning')->with('AlyaPay webhook: order not found', $this->anything());
        $this->orderHelper->expects($this->never())->method('approveAndCaptureOrder');

        $this->assertTrue($this->processor->process($payload));
    }

    public function testProcessResolvesOrderByOrderReference(): void
    {
        $payload = '{"event":"transaction.approved","data":{"id":"txn_1","orderReference":"000000001"}}';
        $this->json->method('unserialize')->willReturn([
            'event' => 'transaction.approved',
            'data' => ['id' => 'txn_1', 'orderReference' => '000000001'],
        ]);

        $order = $this->createOrderMock(SalesOrder::STATE_PENDING_PAYMENT, false);
        $this->orderHelper->method('getOrderByIncrementId')->with('000000001')->willReturn($order);
        $this->config->method('getApprovedStatus')->willReturn('processing');

        $this->orderHelper->expects($this->once())->method('approveAndCaptureOrder');
        $this->assertTrue($this->processor->process($payload));
    }

    /**
     * @return SalesOrder|MockObject
     */
    private function createOrderMock(string $state, bool $hasInvoices)
    {
        $order = $this->createMock(SalesOrder::class);
        $order->method('getState')->willReturn($state);
        $order->method('hasInvoices')->willReturn($hasInvoices);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('000000001');
        return $order;
    }
}
