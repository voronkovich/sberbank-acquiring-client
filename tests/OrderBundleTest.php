<?php

declare(strict_types=1);

namespace Voronkovich\SberbankAcquiring\Tests;

use Voronkovich\SberbankAcquiring\OrderBundle;
use PHPUnit\Framework\TestCase;

class OrderBundleTest extends TestCase
{
    public function testConvertsProvidedDataToArray()
    {
        $orderBundle = new OrderBundle();
        $orderBundle->setCustomerEmail('oleg-voronkovich@yandex.ru');
        $orderBundle->setCustomerPhone('+71234567890');
        $orderBundle->setCustomerContact('Customer contact');

        $this->assertEquals(
            [
                'customerDetails' => [
                    'email' => 'oleg-voronkovich@yandex.ru',
                    'phone' => '+71234567890',
                    'contact' => 'Customer contact',
                ],
                'cartItems' => [
                    'items' => [],
                ]
            ],
            $orderBundle->toArray()
        );
    }
}
