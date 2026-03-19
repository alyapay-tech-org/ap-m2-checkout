<?php
declare(strict_types=1);

namespace AlyaPay\Payment\Block\Checkout;

use AlyaPay\Payment\Helper\Order as OrderHelper;
use AlyaPay\Payment\Model\Api\ScheduleService;
use AlyaPay\Payment\Model\Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class SuccessInfo extends Template
{
    public const PAYMENT_METHOD_ALYAPAY = 'alyapay';

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @var ScheduleService
     */
    private $scheduleService;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderHelper $orderHelper,
        ScheduleService $scheduleService,
        Config $config,
        LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->orderHelper = $orderHelper;
        $this->scheduleService = $scheduleService;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Only render block when order was placed with AlyaPay
     */
    protected function _toHtml()
    {
        $order = $this->getOrder();
        if (!$order || !$order->getPayment() || $order->getPayment()->getMethod() !== self::PAYMENT_METHOD_ALYAPAY) {
            return '';
        }
        return parent::_toHtml();
    }

    public function getOrder(): ?OrderInterface
    {
        $incrementId = $this->checkoutSession->getLastRealOrderId();
        return $incrementId ? $this->orderHelper->getOrderByIncrementId($incrementId) : null;
    }

    public function getFormattedTotal(OrderInterface $order): string
    {
        $total = (float) $order->getGrandTotal();
        $currencyCode = $order->getOrderCurrencyCode() ?: 'MAD';
        return number_format($total, 2, ',', ' ') . ' ' . $currencyCode;
    }

    /**
     * Get installment schedule from AlyaPay API, or null on failure
     *
     * @return array{transactionId?: string, vendorReference?: string, total?: float, installments?: array}|null
     */
    public function getTransactionSchedules(): ?array
    {
        $order = $this->getOrder();
        if (!$order || !$order->getPayment()) {
            return null;
        }
        $transactionId = $order->getPayment()->getLastTransId();
        if (!$transactionId) {
            return null;
        }
        try {
            return $this->scheduleService->getSchedules($transactionId, (int) $order->getStoreId());
        } catch (\Throwable $e) {
            $this->logger->warning('AlyaPay schedules API failed on success page', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getOrderId(): string
    {
        $order = $this->getOrder();
        return $order ? (string) $order->getRealOrderId() : '';
    }

    public function getOrderDate(): string
    {
        $order = $this->getOrder();
        if (!$order || !$order->getCreatedAt()) {
            return '';
        }
        try {
            $dt = new \DateTimeImmutable($order->getCreatedAt());
            return $dt->format('d/m/Y');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function getOrderTotal(): string
    {
        $order = $this->getOrder();
        return $order ? $this->getFormattedTotal($order) : '';
    }

    public function getInstallmentAmount(): string
    {
        $installments = $this->getInstallments();
        if (!empty($installments)) {
            $first = reset($installments);
            return $first['amount'] ?? '';
        }
        $order = $this->getOrder();
        $count = $this->getInstallmentCount();
        if (!$order || $count <= 0) {
            return '';
        }
        $total = (float) $order->getGrandTotal();
        $currencyCode = $order->getOrderCurrencyCode() ?: 'MAD';
        return number_format($total / $count, 2, ',', ' ') . ' ' . $currencyCode;
    }

    public function getInstallmentCount(): int
    {
        $installments = $this->getInstallments();
        return !empty($installments) ? count($installments) : 4;
    }

    /**
     * Get installments for timeline: ['label'=>'...', 'date'=>'...', 'amount'=>'...', 'status'=>'paid|upcoming|scheduled']
     *
     * @return array<int, array{label: string, date: string, amount: string, status: string}>
     */
    public function getInstallments(): array
    {
        $schedules = $this->getTransactionSchedules();
        $order = $this->getOrder();
        $currencyCode = $order ? ($order->getOrderCurrencyCode() ?: 'MAD') : 'MAD';

        if (empty($schedules['installments'])) {
            return [];
        }

        $locale = $this->getLocale();
        $storeLocale = 'en_US';
        try {
            $order = $this->getOrder();
            if ($order && $order->getStore()) {
                $storeLocale = $order->getStore()->getLocaleCode() ?: 'en_US';
            }
        } catch (\Throwable $e) {
        }

        $installments = $schedules['installments'];
        usort($installments, function ($a, $b) {
            $dateA = $a['dueDate'] ?? '';
            $dateB = $b['dueDate'] ?? '';
            if (!$dateA || !$dateB) {
                return 0;
            }
            try {
                $tsA = (new \DateTimeImmutable($dateA))->getTimestamp();
                $tsB = (new \DateTimeImmutable($dateB))->getTimestamp();
                return $tsA <=> $tsB;
            } catch (\Throwable $e) {
                return 0;
            }
        });

        $result = [];
        $idx = 0;
        foreach ($installments as $row) {
            $status = strtoupper((string) ($row['status'] ?? 'SCHEDULED'));
            $statusKey = 'scheduled';
            if ($status === 'PAID' || $status === 'COMPLETED') {
                $statusKey = 'paid';
            } elseif ($status === 'PENDING' || $status === 'UPCOMING' || $status === 'DUE') {
                $statusKey = 'upcoming';
            }

            $dueDateRaw = $row['dueDate'] ?? '';
            $label = '—';
            if ($dueDateRaw) {
                try {
                    $dt = new \DateTimeImmutable($dueDateRaw);
                    $formatter = new \IntlDateFormatter(
                        $storeLocale,
                        \IntlDateFormatter::NONE,
                        \IntlDateFormatter::NONE,
                        null,
                        null,
                        'MMM d, yyyy'
                    );
                    $label = $formatter->format($dt->getTimestamp());
                    if ($label === false) {
                        $label = $dt->format('M j, Y');
                    }
                } catch (\Throwable $e) {
                    $label = $dueDateRaw;
                }
            }

            $amount = number_format((float) ($row['amount'] ?? 0), 2, ',', ' ') . ' ' . $currencyCode;

            $result[] = ['label' => $label, 'date' => '', 'amount' => $amount, 'status' => $statusKey];
            $idx++;
        }
        return $result;
    }

    public function getViewOrderUrl(): string
    {
        $order = $this->getOrder();
        if (!$order || !$order->getCustomerId()) {
            return '#';
        }
        return $this->_urlBuilder->getUrl('sales/order/view', ['order_id' => $order->getId()]);
    }

    public function getContinueShoppingUrl(): string
    {
        return $this->_urlBuilder->getBaseUrl();
    }

    public function getLocale(): string
    {
        $order = $this->getOrder();
        $storeId = $order ? (int) $order->getStoreId() : null;
        return $this->config->getWidgetLang($storeId);
    }
}
