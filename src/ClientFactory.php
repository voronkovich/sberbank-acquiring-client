<?php

declare(strict_types=1);

namespace Voronkovich\SberbankAcquiring;

/**
 * Factory for creating API clients.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
final class ClientFactory
{
    /**
     * Create a client for the Sberbank production environment.
     *
     * @param array $options Client options (username, password and etc.)
     *
     * @see https://ecomtest.sberbank.ru/doc#section/Obshaya-informaciya/Obrabotka-soobshenij
     *
     * @return Client instance
     */
    public static function sberbank(array $options): Client
    {
        return new Client(
            \array_merge(
                [
                    'apiUri' => 'https://ecommerce.sberbank.ru',
                    'prefixDefault' => '/ecomm/gw/partner/api/v1/',
                    'ecom' => true,
                ],
                $options
            )
        );
    }

    /**
     * Create a client for the Sberbank testing environment.
     *
     * @param array $options Client options (username, password and etc.)
     *
     * @see https://ecomtest.sberbank.ru/doc#section/Obshaya-informaciya/Obrabotka-soobshenij
     *
     * @return Client instance
     */
    public static function sberbankTest(array $options): Client
    {
        return new Client(
            \array_merge(
                [
                    'apiUri' => 'https://ecomtest.sberbank.ru',
                    'prefixDefault' => '/ecomm/gw/partner/api/v1/',
                    'ecom' => true,
                ],
                $options
            )
        );
    }

    /**
     * Create a client for the Alfabank production environment.
     *
     * @param array $options Client options (username, password and etc.)
     *
     * @see https://pay.alfabank.ru/ecommerce/instructions/merchantManual/pages/fz_index.html#koordinati_podkljuchenija
     *
     * @return Client instance
     */
    public static function alfabank(array $options): Client
    {
        return new Client(
            \array_merge(
                [
                    'apiUri' => 'https://pay.alfabank.ru',
                    'prefixDefault' => '/payment/rest/',
                    'prefixSbpQr' => '/payment/rest/sbp/c2b/qr/dynamic/',
                    'prefixApple' => '/payment/applepay/',
                    'prefixGoogle' => '/payment/google/',
                    'prefixSamsung' => '/payment/samsung/',
                ],
                $options
            )
        );
    }

    /**
     * Create a client for the Alfabank testing environment.
     *
     * @param array $options Client options (username, password and etc.)
     *
     * @see https://ecomtest.sberbank.ru/doc#section/Obshaya-informaciya/Obrabotka-soobshenij
     *
     * @return Client instance
     */
    public static function alfabankTest(array $options): Client
    {
        return new Client(
            \array_merge(
                [
                    'apiUri' => 'https://alfa.rbsuat.com',
                    'prefixDefault' => '/payment/rest/',
                    'prefixSbpQr' => '/payment/rest/sbp/c2b/qr/dynamic/',
                    'prefixApple' => '/payment/applepay/',
                    'prefixGoogle' => '/payment/google/',
                    'prefixSamsung' => '/payment/samsung/',
                ],
                $options
            )
        );
    }
}
