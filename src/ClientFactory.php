<?php

declare(strict_types=1);

namespace Voronkovich\SberbankAcquiring;

/**
 * Client factory.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
class ClientFactory
{
    const ALFABANK_PROD_URI = 'https://pay.alfabank.ru';
    const ALFABANK_TEST_URI = 'https://web.rbsuat.com';

    public static function prod(array $options): Client
    {
        $options = \array_merge([
            'apiUri' => Client::API_URI
        ], $options);

        return new Client($options);
    }

    public static function test(array $options): Client
    {
        $options = \array_merge([
            'apiUri' => Client::API_URI_TEST
        ], $options);

        return new Client($options);
    }

    public static function alfabankProd(array $options): Client
    {
        $options = \array_merge([
            'apiUri' => self::ALFABANK_PROD_URI,
            'prefixDefault' => '/payment/rest/',
            'prefixApple' => '/payment/applepay/',
            'prefixGoogle' => '/payment/google/',
            'prefixSamsung' => '/payment/samsung/',
        ], $options);

        return new Client($options);
    }

    public static function alfabankTest(array $options): Client
    {
        $options = \array_merge([
            'apiUri' => self::ALFABANK_TEST_URI,
            'prefixDefault' => '/ab/rest/',
            'prefixApple' => '/ab/applepay/',
            'prefixGoogle' => '/ab/google/',
            'prefixSamsung' => '/ab/samsung/',
        ], $options);

        return new Client($options);
    }
}
