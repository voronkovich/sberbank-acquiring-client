# sberbank-acquiring-client

[![Build Status](https://app.travis-ci.com/voronkovich/sberbank-acquiring-client.svg?branch=master)](https://app.travis-ci.com/github/voronkovich/sberbank-acquiring-client)
[![Latest Stable Version](https://poser.pugx.org/voronkovich/sberbank-acquiring-client/v/stable)](https://packagist.org/packages/voronkovich/sberbank-acquiring-client)
[![Total Downloads](https://poser.pugx.org/voronkovich/sberbank-acquiring-client/downloads)](https://packagist.org/packages/voronkovich/sberbank-acquiring-client/stats)
[![License](https://poser.pugx.org/voronkovich/sberbank-acquiring-client/license)](./LICENSE)

PHP client for [Sberbank](https://ecomtest.sberbank.ru/doc), [Alfabank](https://pay.alfabank.ru/ecommerce/instructions/merchantManual/pages/index/rest.html) and [YooKassa](https://yoomoney.ru/i/forms/yc-program-interface-api-sberbank.pdf) REST APIs.

## Requirements

- PHP 7.1 or above (Old version for PHP 5 you can find [here](https://github.com/voronkovich/sberbank-acquiring-client/tree/1.x))
- TLS 1.2 or above (more information you can find [here](https://civicrm.org/blog/yashodha/are-you-ready-for-tls-12-update-cant-escape-it))
- `php-json` extension installed

## Installation

```sh
composer require 'voronkovich/sberbank-acquiring-client'
```

## Usage

### Instantiating a client

In most cases to instantiate a client you need to pass your `username` and `password` to a factory:

```php
use Voronkovich\SberbankAcquiring\ClientFactory;

// Sberbank production environment
$client = ClientFactory::sberbank(['userName' => 'username', 'password' => 'password']);

// Sberbank testing environment
$client = ClientFactory::sberbankTest(['userName' => 'username', 'password' => 'password']);

// Alfabank production environment
$client = ClientFactory::alfabank(['userName' => 'username', 'password' => 'password']);

// Alfabank testing environment
$client = ClientFactory::alfabankTest(['userName' => 'username', 'password' => 'password']);

// YooKassa production environment
$client = ClientFactory::yookassa(['userName' => 'username', 'password' => 'password']);
```

Alternatively you can use an authentication `token`:

```php
$client = ClientFactory::sberbank(['token' => 'sberbank-token']);
```

More advanced example:

```php
use Voronkovich\SberbankAcquiring\ClientFactory;
use Voronkovich\SberbankAcquiring\Currency;
use Voronkovich\SberbankAcquiring\HttpClient\HttpClientInterface;

$client = ClientFactory::sberbank([
    'userName' => 'username',
    'password' => 'password',
    // A language code in ISO 639-1 format.
    // Use this option to set a language of error messages.
    'language' => 'ru',

    // A currency code in ISO 4217 format.
    // Use this option to set a currency used by default.
    'currency' => Currency::RUB,

    // An HTTP method to use in requests.
    // Must be "GET" or "POST" ("POST" is used by default).
    'httpMethod' => HttpClientInterface::METHOD_GET,

    // An HTTP client for sending requests.
    // Use this option when you don't want to use
    // a default HTTP client implementation distributed
    // with this package (for example, when you have'nt
    // a CURL extension installed in your server).
    'httpClient' => new YourCustomHttpClient(),
]);
```

Also you can use an adapter for the [Guzzle](https://github.com/guzzle/guzzle):

```php
use Voronkovich\SberbankAcquiring\ClientFactory;
use Voronkovich\SberbankAcquiring\HttpClient\GuzzleAdapter;

use GuzzleHttp\Client as Guzzle;

$client = ClientFactory::sberbank([
    'userName' => 'username',
    'password' => 'password',
    'httpClient' => new GuzzleAdapter(new Guzzle()),
]);
```

Also, there are available adapters for [Symfony](https://symfony.com/doc/current/http_client.html) and [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP clents.

### Low level method "execute"

You can interact with the Gateway REST API using a low level method `execute`:

```php
$client->execute('/ecomm/gw/partner/api/v1/register.do', [ 
    'orderNumber' => 1111,
    'amount' => 10,
    'returnUrl' => 'http://localhost/sberbank/success',
]);

$status = $client->execute('/ecomm/gw/partner/api/v1/getOrderStatusExtended.do', [
    'orderId' => '64fc8831-a2b0-721b-64fc-883100001553',
]);
```
But it's more convenient to use one of the shortcuts listed below.

### Creating a new order

[Sberbank](https://ecomtest.sberbank.ru/doc#tag/basicServices/operation/register)
[Alfabank](https://pay.alfabank.ru/ecommerce/instructions/merchantManual/pages/index/rest.html#zapros_registratsii_zakaza_rest_)

```php
use Voronkovich\SberbankAcquiring\Currency;

// Required arguments
$orderId     = 1234;
$orderAmount = 1000;
$returnUrl   = 'http://mycoolshop.local/payment-success';

// You can pass additional parameters like a currency code and etc.
$params['currency'] = Currency::EUR;
$params['failUrl']  = 'http://mycoolshop.local/payment-failure';

$result = $client->registerOrder($orderId, $orderAmount, $returnUrl, $params);

$paymentOrderId = $result['orderId'];
$paymentFormUrl = $result['formUrl'];

header('Location: ' . $paymentFormUrl);
```

If you want to use UUID identifiers ([ramsey/uuid](https://github.com/ramsey/uuid)) for orders you should convert them to a hex format:
```php
use Ramsey\Uuid\Uuid;

$orderId = Uuid::uuid4();

$result = $client->registerOrder($orderId->getHex(), $orderAmount, $returnUrl);
```

Use a `registerOrderPreAuth` method to create a 2-step order.

### Getting a status of an exising order

[Sberbank](https://ecomtest.sberbank.ru/doc#tag/basicServices/operation/getOrderStatusExtended)
[Alfabank](https://pay.alfabank.ru/ecommerce/instructions/merchantManual/pages/index/rest.html#rasshirenniy_zapros_sostojanija_zakaza_rest_)

```php
use Voronkovich\SberbankAcquiring\OrderStatus;

$result = $client->getOrderStatus($orderId);

if (OrderStatus::isDeposited($result['orderStatus'])) {
    echo "Order #$orderId is deposited!";
}

if (OrderStatus::isDeclined($result['orderStatus'])) {
    echo "Order #$orderId was declined!";
}
```

Also, you can get an order's status by using you own identifier (e.g. assigned by your database):

```php
$result = $client->getOrderStatusByOwnId($orderId);
```

### Reversing an exising order

[Sberbank](https://ecomtest.sberbank.ru/doc#tag/basicServices/operation/reverse)
[Alfabank](https://pay.alfabank.ru/ecommerce/instructions/merchantManual/pages/index/rest.html#zapros_otmeni_oplati_zakaza_rest_)

```php
$result = $client->reverseOrder($orderId);
```

### Refunding an exising order

[Sberbank](https://ecomtest.sberbank.ru/doc#tag/basicServices/operation/refund)
[Alfabank](https://pay.alfabank.ru/ecommerce/instructions/merchantManual/pages/index/rest.html#zapros_vozvrata_sredstv_oplati_zakaza_rest_)

```php
$result = $client->refundOrder($orderId, $amountToRefund);
```

### SBP payments using QR codes

_Currently only supported by Alfabank, see [docs](https://pay.alfabank.ru/ecommerce/instructions/SBP_C2B.pdf)._

```php
$result = $client->getSbpDynamicQr($orderId, [
    'qrHeight' => 100,
    'qrWidth' => 100,
    'qrFormat' => 'image',
]);

echo sprintf(
    '<a href="%s"><img src="%s" /></a>',
    $result['payload'],
    'data:image/png;base64,' . $result['renderedQr']
);
```

---
See `Client` source code to find methods for payment bindings and dealing with 2-step payments.

## License

Copyright (c) Voronkovich Oleg. Distributed under the MIT.
