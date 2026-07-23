<?php
/**
 * Unit tests for Cancel controller
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Test\Unit\Controller\Result;

use AlyaPay\Payment\Controller\Result\Cancel;
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

class CancelTest extends TestCase
{
    /** @var CheckoutSession|MockObject */
    private $checkoutSession;

    /** @var RedirectFactory|MockObject */
    private $resultRedirectFactory;

    /** @var OrderHelper|MockObject */
    private $orderHelper;

    /** @var UrlInterface|MockObject */
    private $urlBuilder;

    /** @var VendorTransactionService|MockObject */
    private $vendorTransactionService;

    /** @var ManagerInterface|MockObject */
    private $messageManager;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var Redirect|MockObject */
    private $redirect;

    private Cancel $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checkoutSession = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->addMethods(['getLastRealOrderId'])
            ->getMock();
        $this->resultRedirectFactory = $this->createMock(RedirectFactory::class);
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->vendorTransactionService = $this->createMock(VendorTransactionService::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->redirect = $this->createMock(Redirect::class);
        $this->redirect->method('setUrl')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($this->redirect);

        $this->controller = new Cancel(
            $this->checkoutSession,
            $this->resultRedirectFactory,
            $this->orderHelper,
            $this->urlBuilder,
            $this->vendorTransactionService,
            $this->messageManager,
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

    public function testConfirmedCancelledTransactionCancelsOrder(): void
    {
        $this->checkoutSession->method('getLastRealOrderId')->willReturn('000000001');
        $order = $this->createOrderMock();
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'CANCELED']);

        $this->orderHelper->expects($this->once())
            ->method('cancelOrder')
            ->with($order, 'Customer cancelled payment');

        $this->controller->execute();
    }

    public function testPendingTransactionIsNotCancelled(): void
    {
        $this->checkoutSession->method('getLastRealOrderId')->willReturn('000000001');
        $order = $this->createOrderMock();
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'PENDING']);

        $this->orderHelper->expects($this->never())->method('cancelOrder');
        $this->messageManager->expects($this->once())->method('addNoticeMessage');

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

    public function testNoOrderInSessionDoesNothing(): void
    {
        $this->checkoutSession->method('getLastRealOrderId')->willReturn(null);

        $this->vendorTransactionService->expects($this->never())->method('getByVendorReference');
        $this->orderHelper->expects($this->never())->method('cancelOrder');

        $this->controller->execute();
    }
}
