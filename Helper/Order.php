<?php
/**
 * Order helper
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Helper;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Message\ManagerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\TransactionFactory;

class Order extends AbstractHelper
{
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderResource
     */
    private $orderResource;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var State
     */
    private $appState;

    /**
     * @param Context $context
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderResource $orderResource
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param CheckoutSession $checkoutSession
     * @param ManagerInterface $messageManager
     * @param State $appState
     */
    public function __construct(
        Context $context,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepositoryInterface $orderRepository,
        OrderResource $orderResource,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        CheckoutSession $checkoutSession,
        ManagerInterface $messageManager,
        State $appState
    ) {
        parent::__construct($context);
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->orderResource = $orderResource;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->appState = $appState;
    }

    /**
     * Get order by entity ID
     *
     * @param int|string $orderId
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    public function getOrderById($orderId): ?\Magento\Sales\Api\Data\OrderInterface
    {
        try {
            return $this->orderRepository->get((int) $orderId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Get order by increment ID
     *
     * @param string $incrementId
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    public function getOrderByIncrementId(string $incrementId): ?\Magento\Sales\Api\Data\OrderInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId, 'eq')
            ->create();
        $orders = $this->orderRepository->getList($searchCriteria);

        foreach ($orders->getItems() as $order) {
            return $order;
        }
        return null;
    }

    /**
     * Cancel order
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param string $comment
     * @return bool
     */
    public function cancelOrder(\Magento\Sales\Api\Data\OrderInterface $order, string $comment = ''): bool
    {
        $comment = $comment ?: 'AlyaPay: Order cancelled';
        /** @var \Magento\Sales\Model\Order $order */
        if ($order->getId() && $order->getState() !== \Magento\Sales\Model\Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->cancel()->save();
            if ($this->appState->getAreaCode() === Area::AREA_FRONTEND) {
                $this->restoreQuote();
            }
            return true;
        }
        return false;
    }

    /**
     * Apply status from webhook config - uses Magento status→state mapping
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param string $targetStatus Status from config (e.g. canceled, closed)
     * @param string $comment
     * @return bool
     */
    public function applyWebhookStatus(
        \Magento\Sales\Api\Data\OrderInterface $order,
        string $targetStatus,
        string $comment
    ): bool {
        /** @var \Magento\Sales\Model\Order $order */
        if (!$order->getId()) {
            return false;
        }

        $targetStatus = $targetStatus ?: 'canceled';
        $state = $this->getStateForStatus($targetStatus);

        if ($state === \Magento\Sales\Model\Order::STATE_CANCELED) {
            return $this->cancelOrder($order, $comment);
        }

        if ($state) {
            $order->setState($state);
            $order->setStatus($targetStatus);
            $order->addCommentToStatusHistory($comment, $targetStatus);
            $order->save();
            if ($this->appState->getAreaCode() === Area::AREA_FRONTEND) {
                $this->restoreQuote();
            }
            return true;
        }

        return $this->cancelOrder($order, $comment);
    }

    /**
     * Get order state for a status (from sales_order_status_state)
     *
     * @param string $status
     * @return string|null
     */
    private function getStateForStatus(string $status): ?string
    {
        $connection = $this->orderResource->getConnection();
        $table = $this->orderResource->getTable('sales_order_status_state');
        $state = $connection->fetchOne(
            $connection->select()
                ->from($table, 'state')
                ->where('status = ?', $status)
        );
        return $state !== false ? (string) $state : null;
    }

    /**
     * Approve and capture order (invoice + capture, apply status from config)
     * Idempotent: skips if order already has invoices.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param string $transactionId AlyaPay transaction ID
     * @param string $targetStatus Status from config (e.g. processing)
     * @param string $comment
     * @return bool
     */
    public function approveAndCaptureOrder(
        \Magento\Sales\Api\Data\OrderInterface $order,
        string $transactionId,
        string $targetStatus,
        string $comment
    ): bool {
        /** @var \Magento\Sales\Model\Order $order */
        if (!$order->getId()) {
            return false;
        }
        if ($order->getState() === \Magento\Sales\Model\Order::STATE_CANCELED) {
            return false;
        }
        if ($order->hasInvoices()) {
            return true;
        }
        if (!$order->canInvoice()) {
            return false;
        }

        $payment = $order->getPayment();
        $payment->setLastTransId($transactionId);
        $payment->setTransactionId($transactionId);

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
        $invoice->setTransactionId($transactionId);
        $invoice->register();

        $payment->addTransaction(
            \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE,
            $invoice,
            true
        );

        $state = $this->getStateForStatus($targetStatus) ?: \Magento\Sales\Model\Order::STATE_PROCESSING;
        $order->setState($state);
        $order->setStatus($targetStatus);
        $order->addCommentToStatusHistory($comment, $targetStatus);

        $this->transactionFactory->create()
            ->addObject($invoice)
            ->addObject($payment)
            ->addObject($order)
            ->save();

        return true;
    }

    /**
     * Restore quote after cancel
     */
    public function restoreQuote(): void
    {
        try {
            $this->checkoutSession->restoreQuote();
        } catch (\Exception $e) {
            $this->_logger->error('Could not restore quote: ' . $e->getMessage());
        }
    }

    /**
     * Get order store ID
     *
     * @param string $incrementId
     * @return int|null
     */
    public function getOrderStoreId(string $incrementId): ?int
    {
        $order = $this->getOrderByIncrementId($incrementId);
        return $order ? (int) $order->getStoreId() : null;
    }

    /**
     * Authorize order after successful payment verification
     *
     * @param string $incrementId
     * @param string $transactionId
     * @return bool
     */
    public function authorizeOrder(string $incrementId, string $transactionId): bool
    {
        $order = $this->getOrderByIncrementId($incrementId);
        if (!$order || $order->getState() === \Magento\Sales\Model\Order::STATE_CANCELED) {
            return false;
        }

        if ($order->getState() === \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT ||
            $order->getState() === \Magento\Sales\Model\Order::STATE_NEW) {
            $payment = $order->getPayment();
            $payment->setLastTransId($transactionId);
            $payment->setTransactionId($transactionId);
            $payment->setIsTransactionClosed(0);

            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $order->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING));

            $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH, $order, true);

            $order->addCommentToStatusHistory(__('AlyaPay payment approved. Transaction ID: %1', $transactionId));
            $order->save();
            return true;
        }

        return false;
    }
}
