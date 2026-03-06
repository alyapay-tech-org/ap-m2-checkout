<?php
/**
 * AlyaPay HTTP Client
 *
 * @category  AlyaPay
 * @package   AlyaPay_Payment
 */

namespace AlyaPay\Payment\Model\Api\Http;

use AlyaPay\Payment\Exception\AlyaPayApiException;
use AlyaPay\Payment\Model\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class Client
{
    private const CONTENT_TYPE = 'application/json';

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Curl $curl
     * @param Config $config
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        Curl $curl,
        Config $config,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->curl = $curl;
        $this->config = $config;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * POST request with API key auth
     *
     * @param string $path
     * @param array $data
     * @param int|null $storeId
     * @return array
     * @throws \Exception
     */
    public function postWithApiKey(string $path, array $data, ?int $storeId = null): array
    {
        $apiKey = $this->config->getApiKey($storeId);
        if (!$apiKey) {
            throw new \Exception('AlyaPay API key is not configured.');
        }
        return $this->post($path, $data, ['X-Api-Key' => $apiKey], $storeId);
    }

    /**
     * GET request with API key auth
     *
     * @param string $path
     * @param int|null $storeId
     * @return array
     * @throws \Exception
     */
    public function getWithApiKey(string $path, ?int $storeId = null): array
    {
        $apiKey = $this->config->getApiKey($storeId);
        if (!$apiKey) {
            throw new \Exception('AlyaPay API key is not configured.');
        }
        return $this->get($path, ['X-Api-Key' => $apiKey], $storeId);
    }

    /**
     * PUT request with API key auth
     *
     * @param string $path
     * @param array $data
     * @param int|null $storeId
     * @return array
     * @throws \Exception
     */
    public function putWithApiKey(string $path, array $data, ?int $storeId = null): array
    {
        $apiKey = $this->config->getApiKey($storeId);
        if (!$apiKey) {
            throw new \Exception('AlyaPay API key is not configured.');
        }
        return $this->put($path, $data, ['X-Api-Key' => $apiKey], $storeId);
    }

    /**
     * GET request
     *
     * @param string $path
     * @param array $extraHeaders
     * @param int|null $storeId
     * @return array
     */
    public function get(string $path, array $extraHeaders = [], ?int $storeId = null): array
    {
        $url = $this->config->getApiBaseUrl($storeId) . $path;
        $this->curl->addHeader('Content-Type', self::CONTENT_TYPE);
        foreach ($extraHeaders as $name => $value) {
            $this->curl->addHeader($name, $value);
        }
        $this->curl->get($url);

        $response = $this->curl->getBody();
        $status = $this->curl->getStatus();

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('AlyaPay API GET', [
                'url' => $path,
                'status' => $status,
            ]);
        }

        if ($status >= 400) {
            throw $this->parseErrorResponse($response ?? '', $status, $path);
        }

        return $this->json->unserialize($response ?: '{}');
    }

    /**
     * POST request
     *
     * @param string $path
     * @param array $data
     * @param array $extraHeaders
     * @param int|null $storeId
     * @return array
     */
    private function post(string $path, array $data, array $extraHeaders = [], ?int $storeId = null): array
    {
        $url = $this->config->getApiBaseUrl($storeId) . $path;
        $body = $this->json->serialize($data);

        // $this->logger->info('AlyaPay API POST payload', [
        //     'url' => $path,
        //     'payload' => $data,
        //     'vendorReference_in_payload' => $data['vendorReference'] ?? '(not set)',
        // ]);

        $this->curl->addHeader('Content-Type', self::CONTENT_TYPE);
        foreach ($extraHeaders as $name => $value) {
            $this->curl->addHeader($name, $value);
        }
        $this->curl->post($url, $body);

        $response = $this->curl->getBody();
        $status = $this->curl->getStatus();

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('AlyaPay API POST', [
                'url' => $path,
                'status' => $status,
                'has_data' => !empty($data),
            ]);
        }

        if ($status >= 400) {
            throw $this->parseErrorResponse($response ?? '', $status, $path);
        }

        return $this->json->unserialize($response ?: '{}');
    }

    /**
     * PUT request
     *
     * @param string $path
     * @param array $data
     * @param array $extraHeaders
     * @param int|null $storeId
     * @return array
     */
    private function put(string $path, array $data, array $extraHeaders = [], ?int $storeId = null): array
    {
        $url = $this->config->getApiBaseUrl($storeId) . $path;
        $body = $this->json->serialize($data);

        $this->curl->addHeader('Content-Type', self::CONTENT_TYPE);
        foreach ($extraHeaders as $name => $value) {
            $this->curl->addHeader($name, $value);
        }
        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, 'PUT');
        $this->curl->post($url, $body);

        $response = $this->curl->getBody();
        $status = $this->curl->getStatus();

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('AlyaPay API PUT', [
                'url' => $path,
                'status' => $status,
            ]);
        }

        if ($status >= 400) {
            throw $this->parseErrorResponse($response ?? '', $status, $path);
        }

        return $this->json->unserialize($response ?: '{}');
    }

    /**
     * Parse error response body and throw AlyaPayApiException
     *
     * @param string $body
     * @param int $statusCode
     * @param string $path
     * @return AlyaPayApiException
     */
    private function parseErrorResponse(string $body, int $statusCode, string $path): AlyaPayApiException
    {
        $key = null;
        $code = null;
        $parameters = [];
        $validationErrors = [];
        $message = trim($body);

        if (!empty($body)) {
            try {
                $data = $this->json->unserialize($body);
                if (is_array($data)) {
                    if (isset($data['key'], $data['code'])) {
                        $key = (string) $data['key'];
                        $code = (int) $data['code'];
                        $parameters = is_array($data['parameters'] ?? null) ? $data['parameters'] : [];
                    } elseif (isset($data[0]) && is_array($data[0])) {
                        foreach ($data as $item) {
                            if (is_array($item) && isset($item['field']) && isset($item['message'])) {
                                $validationErrors[] = [
                                    'field' => (string) $item['field'],
                                    'message' => (string) $item['message'],
                                ];
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                $message = $body;
            }
        }

        if (empty($message) && $statusCode > 0) {
            $message = "AlyaPay API error: HTTP $statusCode";
        }

        return new AlyaPayApiException(
            $message,
            $statusCode,
            $key,
            $code,
            $parameters,
            $validationErrors,
            $body
        );
    }
}
