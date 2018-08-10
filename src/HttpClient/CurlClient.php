<?php

declare(strict_types=1);

namespace Voronkovich\SberbankAcquiring\HttpClient;

use Voronkovich\SberbankAcquiring\Exception\NetworkException;

/**
 * Simple HTTP client using curl.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
class CurlClient implements HttpClientInterface
{
    /**
     * @var resource
     */
    private $curl;

    /**
     * @var array
     */
    private $curlOptions = [];

    public function __construct(array $curlOptions)
    {
        if (!\extension_loaded('curl')) {
            throw new \RuntimeException('Curl extension is not loaded.');
        }

        $this->curlOptions = $curlOptions;
    }

    private function getCurl(): resource
    {
        if (null === $this->curl) {
            $this->curl = \curl_init();
            \curl_setopt_array($this->curl, $this->curlOptions);
        }

        return $this->curl;
    }

    public function request(string $uri, string $method = 'GET', array $headers = [], array $data = []): array
    {
        $data = \http_build_query($data, '', '&');

        if ('GET' === $method) {
            $curlOptions[\CURLOPT_HTTPGET] = true;
            $curlOptions[\CURLOPT_URL] = $uri . '?' . $data;
        } elseif ('POST' === $method) {
            $curlOptions[\CURLOPT_POST] = true;
            $curlOptions[\CURLOPT_URL] = $uri;
            $curlOptions[\CURLOPT_POSTFIELDS] = $data;
        } else {
            throw new \InvalidArgumentException(\sprintf('An HTTP method "%s" is not supported. Use "GET" or "POST".', $method));
        }

        $curlOptions[\CURLOPT_HTTPHEADER] = $headers;
        $curlOptions[\CURLOPT_RETURNTRANSFER] = true;

        $curl = $this->getCurl();
        \curl_setopt_array($curl, $curlOptions);

        $response = \curl_exec($curl);

        if (false === $response) {
            $error = \curl_error($curl);
            $errorCode = \curl_errno($curl);

            throw new NetworkException('Curl error: ' . $error, $errorCode);
        }

        $httpCode = \curl_getinfo($this->curl, \CURLINFO_HTTP_CODE);

        return [$httpCode, $response];
    }

    public function __destruct()
    {
        if (null !== $this->curl) {
            \curl_close($this->curl);
        }
    }
}
