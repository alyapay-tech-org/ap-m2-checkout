<?php
/**
 * Webhook processor interface
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Api;

interface WebhookProcessorInterface
{
    /**
     * Process webhook payload
     *
     * @param string $payload Raw JSON payload
     * @return bool Success
     */
    public function process(string $payload): bool;
}
