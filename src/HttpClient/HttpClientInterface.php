<?php

namespace Voronkovich\SberbankAcquiring\HttpClient;

/**
 * Simple HTTP client interface.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
interface HttpClientInterface
{
    public function request($uri, $method = 'GET', array $headers = array(), array $data = array());
}
