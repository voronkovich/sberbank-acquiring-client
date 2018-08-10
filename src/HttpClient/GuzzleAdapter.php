<?php

declare(strict_types=1);

namespace Voronkovich\SberbankAcquiring\HttpClient;

use GuzzleHttp\ClientInterface;

/**
 * Adapter for the guzzle.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 * @see http://docs.guzzlephp.org/en/latest/
 */
class GuzzleAdapter implements HttpClientInterface
{
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public function request(string $uri, string $method = HttpClientInterface::METHOD_GET, array $headers = [], array $data = []): array
    {
        $response = $this->client->request($method, $uri, ['headers' => $headers, 'form_params' => $data]);

        return [$response->getStatusCode(), $response->getBody()];
    }
}
