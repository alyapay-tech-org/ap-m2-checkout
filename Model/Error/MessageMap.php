<?php
/**
 * Maps context + exception/status/code to user-friendly messages (translation keys)
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Error;

use AlyaPay\Payment\Exception\AlyaPayApiException;
use Throwable;

class MessageMap
{
    private const AUDIENCE_ADMIN = Result::AUDIENCE_ADMIN;
    private const AUDIENCE_CUSTOMER = Result::AUDIENCE_CUSTOMER;

    /**
     * Context => [HTTP status => [audience, translation_key]]
     */
    private const STATUS_MAP = [
        Context::PARTNER_CONFIG => [
            401 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_api_key'],
            400 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_request'],
            500 => [self::AUDIENCE_ADMIN, 'alyapay.error.config_sync_failed'],
        ],
        Context::SESSION_INTENT => [
            401 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_api_key'],
            400 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_request'],
            405 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_request'],
            415 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_request'],
            500 => [self::AUDIENCE_ADMIN, 'alyapay.error.payment_unavailable'],
        ],
        Context::STATUS_CHECK => [
            401 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_api_key'],
            400 => [self::AUDIENCE_ADMIN, 'alyapay.error.transaction_not_found'],
            500 => [self::AUDIENCE_ADMIN, 'alyapay.error.status_check_failed'],
        ],
    ];

    /**
     * Context => [api code => [audience, translation_key]]
     * Overrides status map when ApiBusinessException has code
     */
    private const CODE_MAP = [
        Context::SESSION_INTENT => [
            4235 => [self::AUDIENCE_ADMIN, 'alyapay.error.invalid_order_data'],
            4239 => [self::AUDIENCE_CUSTOMER, 'alyapay.error.amount_too_low'],
            4242 => [self::AUDIENCE_CUSTOMER, 'alyapay.error.amount_too_high'],
            // BNPL / Credit errors (future use)
            4014 => [self::AUDIENCE_CUSTOMER, 'alyapay.error.customer_blocked'],
            4018 => [self::AUDIENCE_CUSTOMER, 'alyapay.error.balance_exceeded'],
            4019 => [self::AUDIENCE_CUSTOMER, 'alyapay.error.monthly_limit_exceeded'],
        ],
        Context::STATUS_CHECK => [
            4042 => [self::AUDIENCE_ADMIN, 'alyapay.error.transaction_not_found'],
        ],
        Context::PARTNER_CONFIG => [],
    ];

    /**
     * Fallback when no code/status matched
     */
    private const GENERIC_KEY = 'alyapay.error.generic';

    private const MESSAGES = [
        'alyapay.error.invalid_api_key' => 'Invalid API key. Please check your AlyaPay configuration.',
        'alyapay.error.invalid_order_data' => 'Invalid order data. Please check your integration.',
        'alyapay.error.amount_too_low' => 'The order amount is below the minimum for AlyaPay.',
        'alyapay.error.amount_too_high' => 'The order amount exceeds the maximum for AlyaPay.',
        'alyapay.error.validation_failed' => 'Validation failed: %1',
        'alyapay.error.invalid_request' => 'Invalid request. Please check your configuration.',
        'alyapay.error.payment_unavailable' => 'Payment is temporarily unavailable. Please try again later.',
        'alyapay.error.transaction_not_found' => 'Transaction not found.',
        'alyapay.error.status_check_failed' => 'Unable to verify payment status. Please contact support.',
        'alyapay.error.config_sync_failed' => 'Unable to sync configuration to AlyaPay. Please try again later.',
        'alyapay.error.generic' => 'A payment error occurred. Please try again or contact support.',
        'alyapay.error.customer_blocked' => 'Payment is currently unavailable due to overdue payments.',
        'alyapay.error.balance_exceeded' => 'Your outstanding balance exceeds the limit. Please pay existing installments.',
        'alyapay.error.monthly_limit_exceeded' => 'Monthly payment limit reached. Please try again next month.',
    ];

    /**
     * Get user message and audience for exception
     *
     * @param Throwable $e
     * @param string $context
     * @param array $extra ['status' => int, 'code' => int, 'key' => string, 'validationErrors' => []]
     * @return array{0: string, 1: string} [userMessage, audience]
     */
    public function getMessageAndAudience(Throwable $e, string $context, array $extra = []): array
    {
        $status = $extra['status'] ?? null;
        $code = $extra['code'] ?? null;
        $validationErrors = $extra['validationErrors'] ?? [];

        if ($e instanceof AlyaPayApiException) {
            $status = $e->getStatusCode();
            $code = $e->getApiCode();
            $validationErrors = $e->getValidationErrors();
        }

        if ($status === null) {
            $status = $this->extractStatusFromException($e);
        }

        $map = self::STATUS_MAP[$context] ?? [];
        $codeMap = self::CODE_MAP[$context] ?? [];

        if ($code !== null && isset($codeMap[$code])) {
            [$audience, $key] = $codeMap[$code];
            return [$this->translate($key), $audience];
        }

        if (!empty($validationErrors)) {
            $last = end($validationErrors);
            $field = $last['field'] ?? '';
            $msg = $last['message'] ?? '';
            $text = trim($field ? "$field: $msg" : $msg);
            return [
                $this->translate('alyapay.error.validation_failed', [$text]),
                self::AUDIENCE_ADMIN,
            ];
        }

        if ($status !== null && isset($map[$status])) {
            [$audience, $key] = $map[$status];
            return [$this->translate($key), $audience];
        }

        return [$this->translate(self::GENERIC_KEY), self::AUDIENCE_ADMIN];
    }

    /**
     * Get user-facing message (backwards compatible)
     */
    public function getUserMessage(Throwable $e, string $context, ?int $status = null): string
    {
        $extra = $status !== null ? ['status' => $status] : [];
        [$message] = $this->getMessageAndAudience($e, $context, $extra);
        return $message;
    }

    private function translate(string $key, array $params = []): string
    {
        $template = self::MESSAGES[$key] ?? $key;
        if (empty($params)) {
            return (string) __($template);
        }
        return (string) __($template, ...$params);
    }

    private function extractStatusFromException(Throwable $e): ?int
    {
        $message = $e->getMessage();
        if (preg_match('/\b(401|400|403|404|405|415|422|500|502|503)\b/', $message, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
