# sberbank-acquiring-client [![Build Status](https://travis-ci.org/voronkovich/sberbank-acquiring-client.svg?branch=master)](https://travis-ci.org/voronkovich/sberbank-acquiring-client)

PHP client for [Sberbank](https://securepayments.sberbank.ru/wiki/doku.php/integration:api:start#%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D1%84%D0%B5%D0%B9%D1%81_rest) and [Alfabank](https://pay.alfabank.ru/ecommerce/instructions/merchantManual/pages/index/rest.html) REST API.

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

In most cases to instantiate a client you need to pass your username and password to a constructor:

```php
<?php

use Voronkovich\SberbankAcquiring\Client;

$client = new Client(['userName' => 'username', 'password' => 'password']);
```
Alternatively you can use an authentication token:
```php
<?php

use Voronkovich\SberbankAcquiring\Client;

$client = new Client(['token' => 'sberbank-token']);
```

More advanced example:

```php
<?php

use Voronkovich\SberbankAcquiring\Client;
use Voronkovich\SberbankAcquiring\Currency;
use Voronkovich\SberbankAcquiring\HttpClient\HttpClientInterface;

$client = new Client([
    'userName' => 'username',
    'password' => 'password',
    // A language code in ISO 639-1 format.
    // Use this option to set a language of error messages.
    'language' => 'ru',

    // A currency code in ISO 4217 format.
    // Use this option to set a currency used by default.
    'currency' => Currency::RUB,

    // An uri to send requests.
    // Use this option if you want to use the Sberbank's test server.
    'apiUri' => Client::API_URI_TEST,

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

Example for Alphabank:

```php
<?php

use Voronkovich\SberbankAcquiring\Client;

$client = new Client([
    'userName' => 'username',
    'password' => 'password',
    'apiUri' => 'https://web.rbsuat.com',
    'prefixDefault' => '/ab/rest/',
    'prefixApple' => '/ab/applepay/',
    'prefixGoogle' => '/ab/google/',
    'prefixSamsung' => '/ab/samsung/',
]);
```

Also you can use an adapter for the [Guzzle](https://github.com/guzzle/guzzle):
```php
<?php

use Voronkovich\SberbankAcquiring\Client;
use Voronkovich\SberbankAcquiring\HttpClient\GuzzleAdapter;

use GuzzleHttp\Client as Guzzle;

$client = new Client(
    'userName' => 'username',
    'password' => 'password',
    'httpClient' => new GuzzleAdapter(new Guzzle()),
]);
```

### Low level method "execute"

You can interact with the Sberbank REST API using a low level method `execute`:
```php
$client->execute('/payment/rest/register.do', [ 
    'orderNumber' => 1111,
    'amount' => 10,
    'returnUrl' => 'http://localhost/sberbank/success',
]);

$status = $client->execute('/payment/rest/getOrderStatusExtended.do', [
    'orderId' => '64fc8831-a2b0-721b-64fc-883100001553',
]);
```
But it's more convenient to use one of the shortcuts listed below.

### Creating a new order

[/payment/rest/register.do](https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:register)

```php
<?php

use Voronkovich\SberbankAcquiring\Client;
use Voronkovich\SberbankAcquiring\Currency;

$client = new Client(['userName' => 'username', 'password' => 'password']);

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

[/payment/rest/getOrderStatusExtended.do](https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:getorderstatusextended)

```php
<?php

use Voronkovich\SberbankAcquiring\Client;
use Voronkovich\SberbankAcquiring\OrderStatus;

$client = new Client(['userName' => 'username', 'password' => 'password']);

$result = $client->getOrderStatus($orderId);

if (OrderStatus::isDeposited($result['orderStatus'])) {
    echo "Order #$orderId is deposited!";
}

if (OrderStatus::isDeclined($result['orderStatus'])) {
    echo "Order #$orderId was declined!";
}
```

### Reversing an exising order

[/payment/rest/reverse.do](https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:reverse)

```php
<?php

use Voronkovich\SberbankAcquiring\Client;

$client = new Client(['userName' => 'username', 'password' => 'password']);

$result = $client->reverseOrder($orderId);
```

### Refunding an exising order

[/payment/rest/refund.do](https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:refund)

```php
<?php

use Voronkovich\SberbankAcquiring\Client;

$client = new Client(['userName' => 'username', 'password' => 'password']);

$result = $client->refundOrder($orderId, $amountToRefund);
```

### Apple Pay

[/payment/applepay/payment.do](https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:payment_applepay)

```php
<?php

use Voronkovich\SberbankAcquiring\Client;

$client = new Client(['userName' => 'username', 'password' => 'password']);

$orderNumber = 777;
$merchant = 'my_merchant';
$paymentToken = 'token';

$result = $client->payWithApplePay($orderNumber, $merchant, $paymentToken);
```

### Google Pay

[/payment/google/payment.do](https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:payment_googlepay)

```php
<?php

use Voronkovich\SberbankAcquiring\Client;

$client = new Client(['userName' => 'username', 'password' => 'password']);

$orderNumber = 777;
$merchant = 'my_merchant';
$paymentToken = 'token';

$result = $client->payWithGooglePay($orderNumber, $merchant, $paymentToken);
```

### Samsung Pay

[/payment/samsung/payment.do](https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:payment_samsungpay)

```php
<?php

use Voronkovich\SberbankAcquiring\Client;

$client = new Client(['userName' => 'username', 'password' => 'password']);

$orderNumber = 777;
$merchant = 'my_merchant';
$paymentToken = 'token';

$result = $client->payWithSamsungPay($orderNumber, $merchant, $paymentToken);
```

---
See `Client` source code to find methods for payment bindings and dealing with 2-step payments.

## License

Copyright (c) Voronkovich Oleg. Distributed under the MIT.
