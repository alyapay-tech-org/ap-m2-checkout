<?php
/**
 * Unit tests for SignatureVerifier
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Test\Unit\Model\Webhook;

use AlyaPay\Payment\Model\Config;
use AlyaPay\Payment\Model\Webhook\SignatureVerifier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SignatureVerifierTest extends TestCase
{
    /** @var Config|MockObject */
    private $config;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var SignatureVerifier */
    private $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->verifier = new SignatureVerifier($this->config, $this->logger);
    }

    public function testVerifyNoSecretReturnsFalse(): void
    {
        $this->config->method('getWebhookSecret')->willReturn('');
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('AlyaPay webhook: webhook_secret is not configured. Configure it in Admin.');
        $this->assertFalse($this->verifier->verify('{"event":"test"}', null, null));
    }

    public function testVerifyNullSecretReturnsFalse(): void
    {
        $this->config->method('getWebhookSecret')->willReturn(null);
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('AlyaPay webhook: webhook_secret is not configured. Configure it in Admin.');
        $this->assertFalse($this->verifier->verify('{"event":"test"}', null, null));
    }

    public function testVerifyValidSignatureReturnsTrue(): void
    {
        $payload = '{"event":"transaction.approved"}';
        $secret = 'test_secret_123';
        $expectedSig = hash_hmac('sha256', $payload, $secret, true);
        $signatureHeader = 'sha256=' . bin2hex($expectedSig);

        $this->config->method('getWebhookSecret')->willReturn($secret);
        $this->assertTrue($this->verifier->verify($payload, $signatureHeader, null));
    }

    public function testVerifyValidSignatureWithTimestampReturnsTrue(): void
    {
        $payload = '{"event":"test"}';
        $timestamp = (string) time();
        $secret = 'secret';
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSig = hash_hmac('sha256', $signedPayload, $secret, true);
        $signatureHeader = 'sha256=' . bin2hex($expectedSig);

        $this->config->method('getWebhookSecret')->willReturn($secret);
        $this->assertTrue($this->verifier->verify($payload, $signatureHeader, $timestamp));
    }

    public function testVerifyInvalidSignatureReturnsFalse(): void
    {
        $this->config->method('getWebhookSecret')->willReturn('secret');
        $this->logger->expects($this->once())->method('warning')->with('AlyaPay webhook: signature mismatch');
        $this->assertFalse($this->verifier->verify('{"event":"test"}', 'sha256=invalid', null));
    }

    public function testVerifyMissingSignatureWhenSecretSetReturnsFalse(): void
    {
        $this->config->method('getWebhookSecret')->willReturn('secret');
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('AlyaPay webhook: missing signature (webhook_secret is set)');
        $this->assertFalse($this->verifier->verify('{"event":"test"}', null, null));
    }

    public function testVerifyEmptySignatureWhenSecretSetReturnsFalse(): void
    {
        $this->config->method('getWebhookSecret')->willReturn('secret');
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('AlyaPay webhook: missing signature (webhook_secret is set)');
        $this->assertFalse($this->verifier->verify('{"event":"test"}', '', null));
    }

    public function testVerifyTimestampTooOldReturnsFalse(): void
    {
        $payload = '{"event":"test"}';
        $timestamp = (string) (time() - 400);
        $secret = 'secret';
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSig = hash_hmac('sha256', $signedPayload, $secret, true);
        $signatureHeader = 'sha256=' . bin2hex($expectedSig);

        $this->config->method('getWebhookSecret')->willReturn($secret);
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('AlyaPay webhook: timestamp too old or invalid', $this->arrayHasKey('timestamp'));
        $this->assertFalse($this->verifier->verify($payload, $signatureHeader, $timestamp));
    }

    public function testVerifySignatureWithoutSha256Prefix(): void
    {
        $payload = '{"event":"test"}';
        $secret = 'secret';
        $expectedSig = bin2hex(hash_hmac('sha256', $payload, $secret, true));

        $this->config->method('getWebhookSecret')->willReturn($secret);
        $this->assertTrue($this->verifier->verify($payload, $expectedSig, null));
    }
}
