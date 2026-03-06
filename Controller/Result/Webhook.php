<?php
/**
 * Webhook controller - receives POST from AlyaPay
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Controller\Result;

use AlyaPay\Payment\Api\WebhookProcessorInterface;
use AlyaPay\Payment\Model\Webhook\SignatureVerifier;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

class Webhook implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var WebhookProcessorInterface
     */
    private $webhookProcessor;

    /**
     * @var SignatureVerifier
     */
    private $signatureVerifier;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @param WebhookProcessorInterface $webhookProcessor
     * @param SignatureVerifier $signatureVerifier
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        WebhookProcessorInterface $webhookProcessor,
        SignatureVerifier $signatureVerifier,
        RequestInterface $request,
        JsonFactory $resultJsonFactory
    ) {
        $this->webhookProcessor = $webhookProcessor;
        $this->signatureVerifier = $signatureVerifier;
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $payload = (string) $this->request->getContent();
        $signature = $this->getHeaderValue('X-Alya-Signature');
        $timestamp = $this->getHeaderValue('X-Alya-Timestamp');

        if (!$this->signatureVerifier->verify($payload, $signature, $timestamp, null)) {
            $result = $this->resultJsonFactory->create();
            $result->setData(['success' => false, 'error' => 'Invalid signature']);
            $result->setHttpResponseCode(401);
            return $result;
        }

        $success = $this->webhookProcessor->process($payload);

        $result = $this->resultJsonFactory->create();
        $result->setData(['success' => $success]);
        $result->setHttpResponseCode($success ? 200 : 500);

        return $result;
    }

    /**
     * Get HTTP header value (works with RequestInterface)
     */
    private function getHeaderValue(string $name): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->request->getServer($serverKey);
        return $value !== null && $value !== false ? (string) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
