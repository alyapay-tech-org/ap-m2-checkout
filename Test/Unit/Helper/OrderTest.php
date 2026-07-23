<?php
/**
 * Unit tests for Order helper (lock-protected approveAndCaptureOrder)
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Test\Unit\Helper;

use AlyaPay\Payment\Helper\Order;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order as SalesOrder;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrderTest extends TestCase
{
    /** @var OrderRepositoryInterface|MockObject */
    private $orderRepository;

    /** @var InvoiceService|MockObject */
    private $invoiceService;

    /** @var State|MockObject */
    private $appState;

    /** @var LockManagerInterface|MockObject */
    private $lockManager;

    private Order $helper;

    protected function setUp(): void
    {
        parent::setUp();

        $context = $this->createMock(Context::class);
        $logger = $this->createMock(LoggerInterface::class);
        $context->method('getLogger')->willReturn($logger);

        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->invoiceService = $this->createMock(InvoiceService::class);
        $this->appState = $this->createMock(State::class);
        $this->lockManager = $this->createMock(LockManagerInterface::class);

        $this->helper = new Order(
            $context,
            $this->createMock(SearchCriteriaBuilder::class),
            $this->orderRepository,
            $this->createMock(OrderResource::class),
            $this->invoiceService,
            $this->createMock(TransactionFactory::class),
            $this->createMock(CheckoutSession::class),
            $this->createMock(ManagerInterface::class),
            $this->appState,
            $this->lockManager
        );
    }

    public function testApproveAndCaptureOrderReturnsFalseWhenLockNotAcquired(): void
    {
        $order = $this->createMock(SalesOrder::class);
        $order->method('getId')->willReturn(42);
        $order->method('getIncrementId')->willReturn('000000001');

        $this->lockManager->expects($this->once())
            ->method('lock')
            ->with('alyapay_order_capture_000000001', 10)
            ->willReturn(false);

        // Lock not acquired — must never touch the order or invoice service.
        $this->orderRepository->expects($this->never())->method('get');
        $this->invoiceService->expects($this->never())->method('prepareInvoice');

        $result = $this->helper->approveAndCaptureOrder($order, 'txn_1', 'processing', 'comment');
        $this->assertFalse($result);
    }

    public function testApproveAndCaptureOrderAlwaysReleasesLockEvenOnFailure(): void
    {
        $order = $this->createMock(SalesOrder::class);
        $order->method('getId')->willReturn(42);
        $order->method('getIncrementId')->willReturn('000000001');

        $this->lockManager->method('lock')->willReturn(true);

        // Re-fetch under lock throws — lock must still be released.
        $this->orderRepository->method('get')->willThrowException(new \RuntimeException('db error'));

        $this->lockManager->expects($this->once())
            ->method('unlock')
            ->with('alyapay_order_capture_000000001');

        $this->expectException(\RuntimeException::class);
        $this->helper->approveAndCaptureOrder($order, 'txn_1', 'processing', 'comment');
    }

    public function testApproveAndCaptureOrderSkipsWhenAlreadyInvoicedAfterReFetch(): void
    {
        $order = $this->createMock(SalesOrder::class);
        $order->method('getId')->willReturn(42);
        $order->method('getIncrementId')->willReturn('000000001');

        // Simulates the race: another process (e.g. webhook) invoiced the order while
        // this call was waiting for the lock. The re-fetch under lock must see that.
        $refetched = $this->createMock(SalesOrder::class);
        $refetched->method('getState')->willReturn(\Magento\Sales\Model\Order::STATE_PROCESSING);
        $refetched->method('hasInvoices')->willReturn(true);

        $this->lockManager->method('lock')->willReturn(true);
        $this->orderRepository->method('get')->with(42)->willReturn($refetched);

        $this->invoiceService->expects($this->never())->method('prepareInvoice');
        $this->lockManager->expects($this->once())->method('unlock')->with('alyapay_order_capture_000000001');

        $result = $this->helper->approveAndCaptureOrder($order, 'txn_1', 'processing', 'comment');
        $this->assertTrue($result);
    }
}
