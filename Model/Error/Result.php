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

    /** @var string */
    private string $userMessage;

    /** @var string */
    private string $logMessage;

    /** @var string */
    private string $severity;

    /** @var string */
    private string $audience;

    /**
     * @param string $userMessage Message suitable for display to user (admin/frontend)
     * @param string $logMessage Message for logs (can include more detail)
     * @param string $severity error|warning|notice
     * @param string $audience admin|customer - who should see the message
     */
    public function __construct(
        string $userMessage,
        string $logMessage,
        string $severity = self::SEVERITY_ERROR,
        string $audience = self::AUDIENCE_ADMIN
    ) {
        $this->userMessage = $userMessage;
        $this->logMessage = $logMessage;
        $this->severity = $severity;
        $this->audience = $audience;
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
