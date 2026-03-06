<?php
/**
 * AlyaPay Session-Intent API Service (1-Step Flow)
 * POST /api/v1/public/session-intents
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Api;

use AlyaPay\Payment\Model\Api\Http\Client;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class SessionIntentService
{
    private const API_PATH = '/api/v1/public/session-intents';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Client $client
     * @param LoggerInterface $logger
     */
    public function __construct(Client $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Create session and intent in one call (1-step flow for Magento)
     *
     * @param Order $order
     * @return array{payment_intent_id: string, checkout_token: string, checkout_url: string, expires_in: int}
     */
    public function createSessionIntent(Order $order): array
    {
        $payload = [
            'currency' => $order->getOrderCurrencyCode() ?: 'MAD',
            'total' => (float) $order->getGrandTotal(),
            'items' => $this->buildItems($order),
            'vendorReference' => $order->getIncrementId(),
        ];

        $this->logger->info('AlyaPay createSessionIntent payload', ['payload' => $payload]);

        return $this->client->postWithApiKey(self::API_PATH, $payload, (int) $order->getStoreId());
    }

    /**
     * @param Order $order
     * @return array
     */
    private function buildItems(Order $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'id' => substr((string) ($item->getSku() ?: $item->getItemId()), 0, 64),
                'name' => substr((string) $item->getName(), 0, 255),
                'price' => (float) $item->getPriceInclTax(),
                'quantity' => (int) max(1, $item->getQtyOrdered()),
            ];
        }

        $shippingInclTax = (float) $order->getShippingAmount() + (float) $order->getShippingTaxAmount();
        if ($shippingInclTax > 0) {
            $items[] = [
                'id' => 'shipping',
                'name' => substr((string) ($order->getShippingDescription() ?: 'Shipping'), 0, 255),
                'price' => $shippingInclTax,
                'quantity' => 1,
            ];
        }

        if (empty($items)) {
            $items[] = [
                'id' => 'order_' . $order->getIncrementId(),
                'name' => 'Order #' . $order->getIncrementId(),
                'price' => (float) $order->getGrandTotal(),
                'quantity' => 1,
            ];
        }

        return $items;
    }
}
