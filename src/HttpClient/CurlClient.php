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

    /**
     * @return resource
     */
    private function getCurl()
    {
        if (null === $this->curl) {
            $this->curl = \curl_init();
            \curl_setopt_array($this->curl, $this->curlOptions);
        }

        return $this->curl;
    }

    public function request(string $uri, string $method = HttpClientInterface::METHOD_GET, array $headers = [], string $data = ''): array
    {
        if (HttpClientInterface::METHOD_GET === $method) {
            $curlOptions[\CURLOPT_HTTPGET] = true;
            $curlOptions[\CURLOPT_URL] = $uri . '?' . $data;
        } elseif (HttpClientInterface::METHOD_POST === $method) {
            $curlOptions[\CURLOPT_POST] = true;
            $curlOptions[\CURLOPT_URL] = $uri;
            $curlOptions[\CURLOPT_POSTFIELDS] = $data;
        } else {
            throw new \InvalidArgumentException(
                \sprintf(
                    'An HTTP method "%s" is not supported. Use "%s" or "%s".',
                    $method,
                    HttpClientInterface::METHOD_GET,
                    HttpClientInterface::METHOD_POST
                )
            );
        }

        foreach ($headers as $key => $value) {
            $curlOptions[\CURLOPT_HTTPHEADER][] = "$key: $value";
        }

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
