<?php
/**
 * Unit tests for ReconcilePendingOrders cron job
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Test\Unit\Cron;

use AlyaPay\Payment\Cron\ReconcilePendingOrders;
use AlyaPay\Payment\Helper\Order as OrderHelper;
use AlyaPay\Payment\Model\Api\VendorTransactionService;
use AlyaPay\Payment\Model\Config;
use AlyaPay\Payment\Model\Error\Handler as ErrorHandler;
use AlyaPay\Payment\Model\Error\Result as ErrorResult;
use AlyaPay\Payment\Model\PaymentMethod;
use Magento\Sales\Model\Order as SalesOrder;
use Magento\Sales\Model\Order\Payment as SalesOrderPayment;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Minimal stand-in for Magento\Sales\Model\ResourceModel\Order\Collection — implements
 * only what ReconcilePendingOrders::execute() actually calls (addFieldToFilter/join/
 * getTable/foreach), avoiding the overhead of mocking the real AbstractDb collection.
 */
class FakeOrderCollection implements \IteratorAggregate
{
    /** @var SalesOrder[] */
    private array $orders;

    public function __construct(array $orders)
    {
        $this->orders = $orders;
    }

    public function addFieldToFilter(...$args): self
    {
        return $this;
    }

    public function join(...$args): self
    {
        return $this;
    }

    public function getTable(string $name): string
    {
        return $name;
    }

    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->orders);
    }
}

class ReconcilePendingOrdersTest extends TestCase
{
    /** @var OrderCollectionFactory|MockObject */
    private $orderCollectionFactory;

    /** @var OrderHelper|MockObject */
    private $orderHelper;

    /** @var VendorTransactionService|MockObject */
    private $vendorTransactionService;

    /** @var Config|MockObject */
    private $config;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var ErrorHandler|MockObject */
    private $errorHandler;

    private ReconcilePendingOrders $cron;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderCollectionFactory = $this->createMock(OrderCollectionFactory::class);
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->vendorTransactionService = $this->createMock(VendorTransactionService::class);
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->errorHandler = $this->createMock(ErrorHandler::class);

        $this->config->method('isActive')->willReturn(true);
        $this->config->method('getApiKey')->willReturn('sk_live_xxx');
        $this->config->method('getTransactionExpiry')->willReturn(30);

        $this->cron = new ReconcilePendingOrders(
            $this->orderCollectionFactory,
            $this->orderHelper,
            $this->vendorTransactionService,
            $this->config,
            $this->logger,
            $this->errorHandler
        );
    }

    /**
     * @param int $redirectedMinutesAgo How long ago the order was redirected to AlyaPay
     */
    private function createOrderMock(int $redirectedMinutesAgo): SalesOrder
    {
        $payment = $this->createMock(SalesOrderPayment::class);
        $payment->method('getAdditionalInformation')
            ->with(PaymentMethod::REDIRECTED_AT)
            ->willReturn((string) (time() - $redirectedMinutesAgo * 60));

        $order = $this->createMock(SalesOrder::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getPayment')->willReturn($payment);
        return $order;
    }

    private function withCollection(array $orders): void
    {
        $this->orderCollectionFactory->method('create')->willReturn(new FakeOrderCollection($orders));
    }

    public function testApprovedOrderPastThresholdGetsCaptured(): void
    {
        $order = $this->createOrderMock(40); // past 30 min expiry + 5 min grace
        $this->withCollection([$order]);

        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'APPROVED', 'id' => 'txn_1']);
        $this->config->method('getApprovedStatus')->willReturn('processing');

        $this->orderHelper->expects($this->once())
            ->method('approveAndCaptureOrder')
            ->with($order, 'txn_1', 'processing', $this->stringContains('txn_1'));

        $this->cron->execute();
    }

    public function testExpiredOrderPastThresholdGetsClosed(): void
    {
        $order = $this->createOrderMock(40);
        $this->withCollection([$order]);

        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'EXPIRED', 'id' => 'txn_2']);
        $this->config->method('getExpiredStatus')->willReturn('canceled');

        $this->orderHelper->expects($this->once())
            ->method('applyWebhookStatus')
            ->with($order, 'canceled', $this->stringContains('expired'));
        $this->orderHelper->expects($this->never())->method('approveAndCaptureOrder');

        $this->cron->execute();
    }

    public function testStillPendingOrderIsLeftUntouched(): void
    {
        $order = $this->createOrderMock(40);
        $this->withCollection([$order]);

        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'PENDING']);

        $this->orderHelper->expects($this->never())->method('approveAndCaptureOrder');
        $this->orderHelper->expects($this->never())->method('applyWebhookStatus');

        $this->cron->execute();
    }

    public function testOrderNotYetPastThresholdIsSkippedWithoutApiCall(): void
    {
        // Only 10 minutes since redirect — expiry(30) + grace(5) not reached yet.
        $order = $this->createOrderMock(10);
        $this->withCollection([$order]);

        $this->vendorTransactionService->expects($this->never())->method('getByVendorReference');

        $this->cron->execute();
    }

    public function testInactiveStoreIsSkippedWithoutApiCall(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('isActive')->willReturn(false);
        $this->cron = new ReconcilePendingOrders(
            $this->orderCollectionFactory,
            $this->orderHelper,
            $this->vendorTransactionService,
            $this->config,
            $this->logger,
            $this->errorHandler
        );

        $order = $this->createOrderMock(40);
        $this->withCollection([$order]);

        $this->vendorTransactionService->expects($this->never())->method('getByVendorReference');

        $this->cron->execute();
    }

    public function testVendorLookupFailureForOneOrderDoesNotStopTheBatch(): void
    {
        $failingOrder = $this->createOrderMock(40);
        $okOrder = $this->createOrderMock(40);
        $this->withCollection([$failingOrder, $okOrder]);

        $callCount = 0;
        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    throw new \Exception('network error');
                }
                return ['status' => 'APPROVED', 'id' => 'txn_ok'];
            });

        $errorResult = $this->createMock(ErrorResult::class);
        $errorResult->method('getLogMessage')->willReturn('network error');
        $this->errorHandler->method('handle')->willReturn($errorResult);
        $this->config->method('getApprovedStatus')->willReturn('processing');

        $this->orderHelper->expects($this->once())->method('approveAndCaptureOrder');

        $this->cron->execute();
    }
}
