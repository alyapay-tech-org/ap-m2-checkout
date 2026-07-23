<?php
/**
 * Unit tests for Success controller
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Test\Unit\Controller\Result;

use AlyaPay\Payment\Controller\Result\Success;
use AlyaPay\Payment\Helper\Order as OrderHelper;
use AlyaPay\Payment\Model\Api\VendorTransactionService;
use AlyaPay\Payment\Model\Config;
use AlyaPay\Payment\Model\Error\Handler as ErrorHandler;
use AlyaPay\Payment\Model\Error\Result as ErrorResult;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order as SalesOrder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SuccessTest extends TestCase
{
    /** @var CheckoutSession|MockObject */
    private $checkoutSession;

    /** @var VendorTransactionService|MockObject */
    private $vendorTransactionService;

    /** @var RedirectFactory|MockObject */
    private $resultRedirectFactory;

    /** @var ManagerInterface|MockObject */
    private $messageManager;

    /** @var OrderHelper|MockObject */
    private $orderHelper;

    /** @var Config|MockObject */
    private $config;

    /** @var UrlInterface|MockObject */
    private $urlBuilder;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var ErrorHandler|MockObject */
    private $errorHandler;

    /** @var Redirect|MockObject */
    private $redirect;

    private Success $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checkoutSession = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->addMethods(['getLastRealOrderId'])
            ->getMock();
        $this->vendorTransactionService = $this->createMock(VendorTransactionService::class);
        $this->resultRedirectFactory = $this->createMock(RedirectFactory::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->orderHelper = $this->createMock(OrderHelper::class);
        $this->config = $this->createMock(Config::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->errorHandler = $this->createMock(ErrorHandler::class);

        $this->redirect = $this->createMock(Redirect::class);
        $this->redirect->method('setUrl')->willReturnSelf();
        $this->redirect->method('setPath')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($this->redirect);

        $this->controller = new Success(
            $this->checkoutSession,
            $this->vendorTransactionService,
            $this->resultRedirectFactory,
            $this->messageManager,
            $this->orderHelper,
            $this->config,
            $this->urlBuilder,
            $this->logger,
            $this->errorHandler
        );
    }

    private function createOrderMock(): SalesOrder
    {
        $order = $this->createMock(SalesOrder::class);
        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getStoreId')->willReturn(1);
        return $order;
    }

    public function testApprovedTransactionApprovesAndCapturesOrder(): void
    {
        $this->checkoutSession->method('getLastRealOrderId')->willReturn('000000001');
        $order = $this->createOrderMock();
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);
        $this->config->method('getApprovedStatus')->with(1)->willReturn('processing');

        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'APPROVED', 'id' => 'txn_123']);

        $this->orderHelper->expects($this->once())
            ->method('approveAndCaptureOrder')
            ->with($order, 'txn_123', 'processing', $this->stringContains('txn_123'));

        $this->urlBuilder->expects($this->once())->method('getUrl')->with('checkout/onepage/success');

        $this->controller->execute();
    }

    public function testTerminalFailedTransactionCancelsOrder(): void
    {
        $this->checkoutSession->method('getLastRealOrderId')->willReturn('000000001');
        $order = $this->createOrderMock();
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'EXPIRED', 'id' => 'txn_1']);

        $this->orderHelper->expects($this->once())
            ->method('cancelOrder')
            ->with($order, 'Payment failed: EXPIRED');
        $this->orderHelper->expects($this->once())->method('restoreQuote');
        $this->orderHelper->expects($this->never())->method('approveAndCaptureOrder');

        $this->controller->execute();
    }

    public function testPendingTransactionDoesNotCancelOrApprove(): void
    {
        // Exact bug scenario: even a "FAILURE"-style return must not cancel while
        // AlyaPay itself still reports PENDING.
        $this->checkoutSession->method('getLastRealOrderId')->willReturn('000000001');
        $order = $this->createOrderMock();
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $this->vendorTransactionService->method('getByVendorReference')
            ->willReturn(['status' => 'PENDING']);

        $this->orderHelper->expects($this->never())->method('cancelOrder');
        $this->orderHelper->expects($this->never())->method('approveAndCaptureOrder');
        $this->messageManager->expects($this->once())->method('addNoticeMessage');

        $this->controller->execute();
    }

    public function testLookupFailureLeavesOrderPending(): void
    {
        $this->checkoutSession->method('getLastRealOrderId')->willReturn('000000001');
        $order = $this->createOrderMock();
        $this->orderHelper->method('getOrderByIncrementId')->willReturn($order);

        $this->vendorTransactionService->method('getByVendorReference')
            ->willThrowException(new \Exception('network error'));

        $errorResult = $this->createMock(ErrorResult::class);
        $errorResult->method('getLogMessage')->willReturn('network error');
        $this->errorHandler->method('handle')->willReturn($errorResult);

        $this->orderHelper->expects($this->never())->method('cancelOrder');
        $this->orderHelper->expects($this->never())->method('approveAndCaptureOrder');
        $this->messageManager->expects($this->once())->method('addNoticeMessage');

        $this->controller->execute();
    }

    public function testNoOrderInSessionShowsError(): void
    {
        $this->checkoutSession->method('getLastRealOrderId')->willReturn(null);

        $this->vendorTransactionService->expects($this->never())->method('getByVendorReference');
        $this->messageManager->expects($this->once())->method('addErrorMessage');

        $this->controller->execute();
    }
}
