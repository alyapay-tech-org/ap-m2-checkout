<?php
/**
 * Error context constants for centralized error handling
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Error;

class Context
{
    public const PARTNER_CONFIG = 'partner_config';
    public const SESSION_INTENT = 'session_intent';
    public const WEBHOOK = 'webhook';
    public const STATUS_CHECK = 'status_check';
    public const REDIRECT = 'redirect';
}
