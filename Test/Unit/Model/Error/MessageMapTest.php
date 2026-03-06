<?php
/**
 * Unit tests for MessageMap
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Test\Unit\Model\Error;

use AlyaPay\Payment\Exception\AlyaPayApiException;
use AlyaPay\Payment\Model\Error\Context;
use AlyaPay\Payment\Model\Error\MessageMap;
use PHPUnit\Framework\TestCase;

class MessageMapTest extends TestCase
{
    private MessageMap $messageMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->messageMap = new MessageMap();
    }

    public function testGetMessageAndAudienceSessionIntentCode4239ReturnsAmountTooLow(): void
    {
        $e = new AlyaPayApiException('Test', 400, null, 4239);
        [$message, $audience] = $this->messageMap->getMessageAndAudience($e, Context::SESSION_INTENT);
        $this->assertStringContainsString('minimum', $message);
        $this->assertEquals('customer', $audience);
    }

    public function testGetMessageAndAudienceSessionIntentCode4242ReturnsAmountTooHigh(): void
    {
        $e = new AlyaPayApiException('Test', 400, null, 4242);
        [$message, $audience] = $this->messageMap->getMessageAndAudience($e, Context::SESSION_INTENT);
        $this->assertStringContainsString('maximum', $message);
        $this->assertEquals('customer', $audience);
    }

    public function testGetMessageAndAudienceSessionIntentCode4235ReturnsInvalidOrderData(): void
    {
        $e = new AlyaPayApiException('Test', 400, null, 4235);
        [$message, $audience] = $this->messageMap->getMessageAndAudience($e, Context::SESSION_INTENT);
        $this->assertStringContainsString('Invalid order data', $message);
        $this->assertEquals('admin', $audience);
    }

    public function testGetMessageAndAudienceSessionIntentStatus401ReturnsInvalidApiKey(): void
    {
        $e = new AlyaPayApiException('Error', 401);
        [$message, $audience] = $this->messageMap->getMessageAndAudience($e, Context::SESSION_INTENT);
        $this->assertStringContainsString('API key', $message);
        $this->assertEquals('admin', $audience);
    }

    public function testGetMessageAndAudienceSessionIntentStatus500ReturnsPaymentUnavailable(): void
    {
        $e = new AlyaPayApiException('Error', 500);
        [$message, $audience] = $this->messageMap->getMessageAndAudience($e, Context::SESSION_INTENT);
        $this->assertStringContainsString('temporarily unavailable', $message);
        $this->assertEquals('admin', $audience);
    }

    public function testGetMessageAndAudienceSessionIntentStatus400ReturnsInvalidRequest(): void
    {
        $e = new AlyaPayApiException('Error', 400);
        [$message] = $this->messageMap->getMessageAndAudience($e, Context::SESSION_INTENT);
        $this->assertStringContainsString('Invalid request', $message);
    }

    public function testGetMessageAndAudienceStatusCheckCode4042ReturnsTransactionNotFound(): void
    {
        $e = new AlyaPayApiException('Error', 400, null, 4042);
        [$message, $audience] = $this->messageMap->getMessageAndAudience($e, Context::STATUS_CHECK);
        $this->assertStringContainsString('Transaction not found', $message);
        $this->assertEquals('admin', $audience);
    }

    public function testGetMessageAndAudienceValidationErrorsReturnsValidationFailed(): void
    {
        $e = new AlyaPayApiException('Error', 422, null, null, [], [
            ['field' => 'items[0].price', 'message' => 'must be greater than 0'],
        ]);
        [$message, $audience] = $this->messageMap->getMessageAndAudience($e, Context::SESSION_INTENT);
        $this->assertStringContainsString('Validation failed', $message);
        $this->assertStringContainsString('items[0].price', $message);
        $this->assertEquals('admin', $audience);
    }

    public function testGetMessageAndAudienceNoMatchReturnsGeneric(): void
    {
        $e = new \Exception('Unknown error');
        [$message, $audience] = $this->messageMap->getMessageAndAudience($e, Context::SESSION_INTENT);
        $this->assertStringContainsString('payment error occurred', $message);
        $this->assertEquals('admin', $audience);
    }

    public function testGetMessageAndAudienceExtractsStatusFromExceptionMessage(): void
    {
        $e = new \Exception('AlyaPay API error: HTTP 500');
        [$message] = $this->messageMap->getMessageAndAudience($e, Context::SESSION_INTENT);
        $this->assertStringContainsString('temporarily unavailable', $message);
    }

    public function testGetUserMessageReturnsFirstElement(): void
    {
        $e = new AlyaPayApiException('Error', 401);
        $message = $this->messageMap->getUserMessage($e, Context::SESSION_INTENT);
        $this->assertStringContainsString('API key', $message);
    }
}
