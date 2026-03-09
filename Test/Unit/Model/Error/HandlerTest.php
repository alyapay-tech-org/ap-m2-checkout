<?php
/**
 * Unit tests for Handler
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Test\Unit\Model\Error;

use AlyaPay\Payment\Exception\AlyaPayApiException;
use AlyaPay\Payment\Model\Error\Context;
use AlyaPay\Payment\Model\Error\Handler;
use AlyaPay\Payment\Model\Error\MessageMap;
use AlyaPay\Payment\Model\Error\Result;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class HandlerTest extends TestCase
{
    /** @var MessageMap|MockObject */
    private $messageMap;

    /** @var LoggerInterface|MockObject */
    private $logger;

    private Handler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->messageMap = $this->createMock(MessageMap::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new Handler($this->messageMap, $this->logger);
    }

    public function testHandleReturnsResultWithUserMessageFromMessageMap(): void
    {
        $e = new \Exception('Test error');
        $this->messageMap->method('getMessageAndAudience')
            ->with($e, Context::SESSION_INTENT, $this->anything())
            ->willReturn(['Mapped message from MessageMap', 'admin']);

        $this->logger->expects($this->once())->method('error');

        $result = $this->handler->handle($e, Context::SESSION_INTENT);

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals('Mapped message from MessageMap', $result->getUserMessage());
        $this->assertEquals('admin', $result->getAudience());
        $this->assertEquals(Result::SEVERITY_ERROR, $result->getSeverity());
    }

    public function testHandleLogsError(): void
    {
        $e = new \Exception('Something failed');
        $this->messageMap->method('getMessageAndAudience')->willReturn(['User msg', 'admin']);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('[AlyaPay][session_intent]'),
                $this->callback(function ($context) {
                    return isset($context['exception'], $context['context'])
                        && $context['exception'] === 'Something failed'
                        && $context['context'] === 'session_intent';
                })
            );

        $this->handler->handle($e, Context::SESSION_INTENT);
    }

    public function testHandleEnrichesExtraFromAlyaPayApiException(): void
    {
        $e = new AlyaPayApiException('API Error', 422, 'validation.failed', 4235, [], []);
        $this->messageMap->method('getMessageAndAudience')
            ->with(
                $e,
                Context::SESSION_INTENT,
                $this->callback(function ($extra) {
                    return ($extra['status'] ?? null) === 422
                        && ($extra['code'] ?? null) === 4235
                        && ($extra['key'] ?? null) === 'validation.failed';
                })
            )
            ->willReturn(['Invalid order data', 'admin']);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->anything(),
                $this->callback(function ($context) {
                    return ($context['status'] ?? null) === 422
                        && ($context['code'] ?? null) === 4235
                        && ($context['key'] ?? null) === 'validation.failed';
                })
            );

        $this->handler->handle($e, Context::SESSION_INTENT);
    }

    public function testHandleResultIsForCustomerWhenAudienceCustomer(): void
    {
        $e = new \Exception('Amount error');
        $this->messageMap->method('getMessageAndAudience')->willReturn(['Amount too low', 'customer']);

        $result = $this->handler->handle($e, Context::SESSION_INTENT);

        $this->assertTrue($result->isForCustomer());
        $this->assertFalse($result->isForAdmin());
    }
}
