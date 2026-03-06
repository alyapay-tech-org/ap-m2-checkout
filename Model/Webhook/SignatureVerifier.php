<?php
/**
 * Verifies AlyaPay webhook signature (HMAC-SHA256)
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Webhook;

use AlyaPay\Payment\Model\Config;
use Psr\Log\LoggerInterface;

class SignatureVerifier
{
    private const HEADER_SIGNATURE = 'X-Alya-Signature';
    private const HEADER_TIMESTAMP = 'X-Alya-Timestamp';
    private const MAX_AGE_SECONDS = 300;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Verify webhook signature.
     * If webhook_secret is not configured, returns true (skip verification).
     *
     * @param string $payload Raw request body
     * @param string|null $signatureHeader Value of X-Alya-Signature
     * @param string|null $timestampHeader Value of X-Alya-Timestamp
     * @param int|null $storeId
     * @return bool
     */
    public function verify(
        string $payload,
        ?string $signatureHeader,
        ?string $timestampHeader,
        ?int $storeId = null
    ): bool {
        $secret = $this->config->getWebhookSecret($storeId);
        if (empty($secret)) {
            return true;
        }

        if (empty($signatureHeader)) {
            $this->logger->warning('AlyaPay webhook: missing signature (webhook_secret is set)');
            return false;
        }

        $expected = $this->computeSignature($payload, $timestampHeader, $secret);
        if ($expected === null) {
            return false;
        }

        $signature = $this->extractSignature($signatureHeader);
        if (!hash_equals($expected, $signature)) {
            $this->logger->warning('AlyaPay webhook: signature mismatch');
            return false;
        }

        return true;
    }

    /**
     * Extract signature from header (X-Alya-Signature: sha256={hex_signature})
     */
    private function extractSignature(string $header): string
    {
        if (strpos($header, 'sha256=') === 0) {
            return substr($header, 7);
        }
        return $header;
    }

    /**
     * Compute expected HMAC-SHA256 signature
     * Supports: HMAC(secret, body) or HMAC(secret, timestamp.body) when timestamp present
     */
    private function computeSignature(string $payload, ?string $timestamp, string $secret): ?string
    {
        if (!empty($timestamp)) {
            if (!$this->isTimestampValid($timestamp)) {
                $this->logger->warning('AlyaPay webhook: timestamp too old or invalid', ['timestamp' => $timestamp]);
                return null;
            }
            $signedPayload = $timestamp . '.' . $payload;
        } else {
            $signedPayload = $payload;
        }

        $hash = hash_hmac('sha256', $signedPayload, $secret, true);
        return bin2hex($hash);
    }

    private function isTimestampValid(string $timestamp): bool
    {
        $ts = is_numeric($timestamp)
            ? (int) $timestamp
            : (int) strtotime($timestamp);
        if ($ts <= 0) {
            return false;
        }
        $age = time() - $ts;
        return $age >= 0 && $age <= self::MAX_AGE_SECONDS;
    }
}
