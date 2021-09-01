<?php

declare(strict_types=1);

namespace Voronkovich\SberbankAcquiring\HttpClient;

use Symfony\Contracts\HttpClient\HttpClientInterface as ClientInterface;

/**
 * Adapter for the Symfony's HTTP client.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 * @see https://symfony.com/doc/current/http_client.html
 */
class SymfonyAdapter implements HttpClientInterface
{
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public function request(string $uri, string $method = HttpClientInterface::METHOD_GET, array $headers = [], string $data = ''): array
    {
        $requestUri = $uri;
        $options = [
            'headers' => $headers
        ];

        switch ($method) {
            case HttpClientInterface::METHOD_GET:
                $requestUri = $uri . '?' . $data;
                break;
            case HttpClientInterface::METHOD_POST:
                $options['body'] = $data;
                break;
            default:
                throw new \InvalidArgumentException(
                    sprintf(
                        'Invalid HTTP method "%s". Use "%s" or "%s".',
                        $method,
                        HttpClientInterface::METHOD_GET,
                        HttpClientInterface::METHOD_POST
                    )
                );
                break;
        }

        $response = $this->client->request($method, $requestUri, $options);

        $statusCode = $response->getStatusCode();
        $body = $response->getContent();

        return [$statusCode, $body];
    }
}
