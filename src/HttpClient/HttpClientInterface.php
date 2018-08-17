<?php

declare(strict_types=1);

namespace Voronkovich\SberbankAcquiring\HttpClient;

use Voronkovich\SberbankAcquiring\Exception\NetworkException;

/**
 * Simple HTTP client interface.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
interface HttpClientInterface
{
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    /**
     * Send an HTTP request.
     *
     * @throws NetworkException
     *
     * @return array A response
     */
    public function request(string $uri, string $method = self::METHOD_GET, array $headers = [], string $data = ''): array;
}
