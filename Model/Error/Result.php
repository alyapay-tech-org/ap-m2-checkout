<?php
/**
 * Error handling result - user message, log message, severity, audience
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Error;

class Result
{
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_NOTICE = 'notice';

    public const AUDIENCE_ADMIN = 'admin';
    public const AUDIENCE_CUSTOMER = 'customer';

    /**
     * @param string $userMessage Message suitable for display to user (admin/frontend)
     * @param string $logMessage Message for logs (can include more detail)
     * @param string $severity error|warning|notice
     * @param string $audience admin|customer - who should see the message
     */
    public function __construct(
        private readonly string $userMessage,
        private readonly string $logMessage,
        private readonly string $severity = self::SEVERITY_ERROR,
        private readonly string $audience = self::AUDIENCE_ADMIN
    ) {
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    public function getLogMessage(): string
    {
        return $this->logMessage;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getAudience(): string
    {
        return $this->audience;
    }

    public function isForAdmin(): bool
    {
        return $this->audience === self::AUDIENCE_ADMIN;
    }

    public function isForCustomer(): bool
    {
        return $this->audience === self::AUDIENCE_CUSTOMER;
    }
}
