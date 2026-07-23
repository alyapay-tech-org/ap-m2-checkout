<?php
/**
 * Unit tests for Failure controller
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Test\Unit\Controller\Result;

use AlyaPay\Payment\Controller\Result\Failure;
use AlyaPay\Payment\Helper\Order as OrderHelper;
use AlyaPay\Payment\Model\Api\VendorTransactionService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order as SalesOrder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FailureTest extends TestCase
{
    /** @var CheckoutSession|MockObject */
    private $checkoutSession;

    /** @var RedirectFactory|MockObject */
    private $resultRedirectFactory;

    /** @var ManagerInterface|MockObject */
    private $messageManager;

    /** @var OrderHelper|MockObject */
    private $orderHelper;

    /** @var UrlInterface|MockObject */
    private $urlBuilder;

    /** @var VendorTransactionService|MockObject */
    private $vendorTransactionService;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var Redirect|MockObject */
    private $redirect;

    private Failure $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checkoutSession = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->addMethods(['getLastRealOrderId'])
            ->getMock();
        $this->resultRedirectFactory = $this->createMock(RedirectFactory::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->vendorTransactionService = $this->createMock(VendorTransactionService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->redirect = $this->createMock(Redirect::class);
        $this->redirect->method('setUrl')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($this->redirect);

        $this->controller = new Failure(
            $this->checkoutSession,
            $this->resultRedirectFactory,
            $this->messageManager,
            $this->orderHelper,
            $this->urlBuilder,
            $this->vendorTransactionService,
            $this->logger
        );
    }

    private function createOrderMock(): SalesOrder
    {
        $order = $this->createMock(SalesOrder::class);
        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getStoreId')->willReturn(1);
        return $order;
    }

    public function testApprovedTransactionIsNotCancelled(): void
    {
        $this->checkoutSession->method('getLastRealOrderId')->willReturn('000000001');
        $order = $this->createOrderMock();
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'APPROVED']);

        $this->orderHelper->expects($this->never())->method('cancelOrder');
        $this->urlBuilder->expects($this->once())->method('getUrl')->with('checkout/onepage/success');

        $this->controller->execute();
    }

    public function testConfirmedFailedTransactionCancelsOrder(): void
    {
        $this->checkoutSession->method('getLastRealOrderId')->willReturn('000000001');
        $order = $this->createOrderMock();
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'DECLINED']);

        $this->orderHelper->expects($this->once())
            ->method('cancelOrder')
            ->with($order, 'Payment failed');
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->controller->execute();
    }

    public function testStillPendingTransactionIsNotCancelled(): void
    {
        $this->checkoutSession->method('getLastRealOrderId')->willReturn('000000001');
        $order = $this->createOrderMock();
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);

        // This is the exact bug scenario: Magento's redirect says "failure" but AlyaPay
        // still reports PENDING — must not cancel.
        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'PENDING']);

        $this->orderHelper->expects($this->never())->method('cancelOrder');
        // Customer already saw a failure on AlyaPay's checkout — tell them plainly,
        // don't say "we're confirming" even though the order itself stays untouched.
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->controller->execute();
    }

    public function testLookupFailureDoesNotCancelOrder(): void
    {
        $this->checkoutSession->method('getLastRealOrderId')->willReturn('000000001');
        $order = $this->createOrderMock();
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $this->vendorTransactionService->method('getByVendorReference')
            ->willThrowException(new \Exception('network error'));

        $this->orderHelper->expects($this->never())->method('cancelOrder');
        $this->logger->expects($this->once())->method('warning');

        $this->controller->execute();
    }
}
