<?php
/**
 * Centralized error handler - maps exceptions to user messages, logs, returns Result
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Error;

use AlyaPay\Payment\Exception\AlyaPayApiException;
use Psr\Log\LoggerInterface;
use Throwable;

class Handler
{
    /**
     * @var MessageMap
     */
    private $messageMap;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param MessageMap $messageMap
     * @param LoggerInterface $logger
     */
    public function __construct(
        MessageMap $messageMap,
        LoggerInterface $logger
    ) {
        $this->messageMap = $messageMap;
        $this->logger = $logger;
    }

    /**
     * Handle exception: map to user message, log, return Result with audience
     *
     * @param Throwable $e
     * @param string $context One of Context::* constants
     * @param array $extra Optional: ['status' => 401] when HTTP status is known
     * @return Result
     */
    public function handle(Throwable $e, string $context, array $extra = []): Result
    {
        $extra = $this->enrichExtraFromException($e, $extra);
        [$userMessage, $audience] = $this->messageMap->getMessageAndAudience($e, $context, $extra);
        $logMessage = $this->buildLogMessage($e, $context, $extra);

        $this->logger->error('[AlyaPay][' . $context . '] ' . $logMessage, [
            'exception' => $e->getMessage(),
            'context' => $context,
            'status' => $extra['status'] ?? null,
            'code' => $extra['code'] ?? null,
            'key' => $extra['key'] ?? null,
        ]);

        return new Result($userMessage, $logMessage, Result::SEVERITY_ERROR, $audience);
    }

    private function enrichExtraFromException(Throwable $e, array $extra): array
    {
        if ($e instanceof AlyaPayApiException) {
            $extra['status'] = $e->getStatusCode();
            $extra['code'] = $e->getApiCode();
            $extra['key'] = $e->getKey();
            $extra['validationErrors'] = $e->getValidationErrors();
        }
        return $extra;
    }

    private function buildLogMessage(Throwable $e, string $context, array $extra): string
    {
        $parts = ['context=' . $context];
        if (isset($extra['status'])) {
            $parts[] = 'status=' . $extra['status'];
        }
        if (isset($extra['code'])) {
            $parts[] = 'code=' . $extra['code'];
        }
        if (isset($extra['key'])) {
            $parts[] = 'key=' . $extra['key'];
        }
        if (count($parts) > 1) {
            return implode(' ', $parts);
        }
        $msg = $e->getMessage();
        return strlen($msg) > 200 ? substr($msg, 0, 200) . '...' : $msg;
    }
}
