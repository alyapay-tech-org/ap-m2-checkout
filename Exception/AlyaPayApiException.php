<?php
/**
 * AlyaPay API error exception with parsed response data
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Exception;

use Exception;

class AlyaPayApiException extends Exception
{
    /**
     * @param string $message
     * @param int $statusCode
     * @param string|null $key ApiBusinessException key
     * @param int|null $code ApiBusinessException code
     * @param array $parameters ApiBusinessException parameters
     * @param array $validationErrors [{field, message}, ...]
     * @param string $rawBody Raw response body
     */
    public function __construct(
        string $message = '',
        private int $statusCode = 0,
        private ?string $key = null,
        private ?int $code = null,
        private array $parameters = [],
        private array $validationErrors = [],
        private string $rawBody = ''
    ) {
        parent::__construct($message ?: "AlyaPay API error: HTTP $statusCode");
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getApiCode(): ?int
    {
        return $this->code;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return array<array{field?: string, message?: string}>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }
}
