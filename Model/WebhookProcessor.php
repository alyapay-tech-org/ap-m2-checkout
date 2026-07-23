<?php
/**
 * Webhook processor - handles transaction.cancelled, transaction.expired
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model;

use AlyaPay\Payment\Api\WebhookProcessorInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class WebhookProcessor implements WebhookProcessorInterface
{
    private const EVENT_APPROVED = 'transaction.approved';
    private const EVENT_CANCELLED = 'transaction.cancelled';
    private const EVENT_EXPIRED = 'transaction.expired';

    /**
     * @var \AlyaPay\Payment\Helper\Order
     */
    private $orderHelper;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param \AlyaPay\Payment\Helper\Order $orderHelper
     * @param Config $config
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        \AlyaPay\Payment\Helper\Order $orderHelper,
        Config $config,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->orderHelper = $orderHelper;
        $this->config = $config;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function process(string $payload): bool
    {
        try {
            $data = $this->json->unserialize($payload);

            if (!is_array($data)) {
                $this->logger->error('AlyaPay webhook: invalid payload structure');
                return false;
            }

            $event = $data['event'] ?? '';
            $webhookData = $data['data'] ?? [];

            if (!is_array($webhookData)) {
                $this->logger->error('AlyaPay webhook: missing data');
                return false;
            }

            if (!in_array($event, [self::EVENT_APPROVED, self::EVENT_CANCELLED, self::EVENT_EXPIRED])) {
                $this->logger->info('AlyaPay webhook: ignoring event', ['event' => $event]);
                return true;
            }

            $order = $this->resolveOrder($webhookData);
            if (!$order) {
                $this->logger->warning('AlyaPay webhook: order not found', [
                    'transaction_id' => $webhookData['id'] ?? null,
                    'vendorReference' => $webhookData['vendorReference'] ?? null,
                ]);
                return true;
            }

            $storeId = (int) $order->getStoreId();
            $transactionId = (string) ($webhookData['id'] ?? '');

            if ($event === self::EVENT_APPROVED) {
                if ($order->getState() === \Magento\Sales\Model\Order::STATE_CANCELED) {
                    return true;
                }
                if ($order->hasInvoices()) {
                    return true;
                }
                if ($order->getPayment()->getMethod() !== 'alyapay') {
                    $this->logger->info('AlyaPay webhook: ignoring ' . $event . ' — payment method changed', [
                        'increment_id'   => $order->getIncrementId(),
                        'payment_method' => $order->getPayment()->getMethod(),
                    ]);
                    return true;
                }
                $comment = sprintf('AlyaPay: Payment approved (webhook). Transaction ID: %s', $transactionId);
                $targetStatus = $this->config->getApprovedStatus($storeId);
                $this->orderHelper->approveAndCaptureOrder($order, $transactionId, $targetStatus, $comment);
            } else {
                if ($order->getState() === \Magento\Sales\Model\Order::STATE_CANCELED) {
                    return true;
                }
                if ($order->getState() === \Magento\Sales\Model\Order::STATE_CLOSED) {
                    return true;
                }

                // Customer switched payment method — webhook belongs to abandoned AlyaPay attempt, ignore it.
                if ($order->getPayment()->getMethod() !== 'alyapay') {
                    $this->logger->info('AlyaPay webhook: ignoring ' . $event . ' — payment method changed', [
                        'increment_id'   => $order->getIncrementId(),
                        'payment_method' => $order->getPayment()->getMethod(),
                    ]);
                    return true;
                }

                $comment = $event === self::EVENT_EXPIRED
                    ? sprintf('AlyaPay: Transaction expired (webhook). Transaction ID: %s', $transactionId)
                    : sprintf('AlyaPay: Payment cancelled (webhook). Transaction ID: %s', $transactionId);

                if ($event === self::EVENT_EXPIRED) {
                    $targetStatus = $this->config->getExpiredStatus($storeId);
                    $this->orderHelper->applyWebhookStatus($order, $targetStatus, $comment);
                } else {
                    $targetStatus = $this->config->getCanceledStatus($storeId);
                    $this->orderHelper->applyWebhookStatus($order, $targetStatus, $comment);
                }
            }

            $this->logger->info('AlyaPay webhook: order updated', [
                'event' => $event,
                'increment_id' => $order->getIncrementId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('AlyaPay webhook error: ' . $e->getMessage(), ['payload' => $payload]);
            return false;
        }
    }

    /**
     * Resolve order from webhook data
     *
     * @param array $data
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    private function resolveOrder(array $data): ?\Magento\Sales\Api\Data\OrderInterface
    {
        $vendorRef = $data['vendorReference'] ?? $data['orderReference'] ?? null;

        if ($vendorRef !== null && $vendorRef !== '') {
            return $this->orderHelper->getOrderByIncrementId((string) $vendorRef);
        }

        return null;
    }
}
