<?php

declare(strict_types=1);

namespace Voronkovich\SberbankAcquiring\HttpClient;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

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

    public function request(string $uri, string $method = HttpClientInterface::METHOD_GET, array $headers = [], string $data = ''): array
    {
        $guzzleVersion = (int) $this->client::VERSION;

        $options = ['headers' => $headers];

        switch ($method) {
            case HttpClientInterface::METHOD_GET:
                $options['query'] = $data;
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

        if (6 > $guzzleVersion) {
            $request = $this->client->createRequest($method, $uri, $options);
            $response = $this->client->send($request);
        } else {
            $response = $this->client->request($method, $uri, $options);
        }

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        return [$statusCode, $body];
    }
}
