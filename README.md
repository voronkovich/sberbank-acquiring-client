# sberbank-acquiring-client [![Build Status](https://travis-ci.org/voronkovich/sberbank-acquiring-client.svg?branch=master)](https://travis-ci.org/voronkovich/sberbank-acquiring-client)

PHP client for [Sberbank's acquiring](http://data.sberbank.ru/en/s_m_business/bankingservice/equairing/) REST API.

## Installation

```sh
composer require 'voronkovich/sberbank-acquiring-client:dev-master'
```

## Usage

### Instantiating a client

```php
<?php

use Voronkovich\SberbankAcquiring\Client;

// In most cases to instantiate a client you need
// to pass your username and password to a constructor
$client = new Client([ 'userName' => 'YourUserName', 'password' => 'YourPassword' ]);

// More advanced example
$client = new Client([
    'userName' => 'userName',
    'password' => 'password',

    // A language code in ISO 639-1 format.
    // Use this option to set a language of error messages.
    'language' => 'ru',

    // An uri to send requests.
    // Use this option if you want to use the Sberbank's test server.
    'apiUri' => Client::API_URI_TEST,

    // An HTTP method to use in requests.
    // Must be "GET" or "POST" ("POST" is used by default).
    'httpMethod' => 'GET',

    // An HTTP client for sending requests.
    // Use this option when you don't want to use
    // a default HTTP client implementation distributed
    // with this package (for example, when you have'nt
    // a CURL extension installed in your server).
    'httpClient' => new YourCustomHttpClient(),
]);
```

### Creating a new order

```php
<?php

use Voronkovich\SberbankAcquiring\Client;
use Voronkovich\SberbankAcquiring\Currency;

$client = new Client([ 'userName' => 'userName', 'password' => 'password' ]);

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

### Getting a status of an exising order

Never use this method, because a Sberbank's gateway does'nt handle it properly, use a `getOrderStatusExtended` instead. For more information see a Sberbank's documentation.

```php
<?php

use Voronkovich\SberbankAcquiring\Client;
use Voronkovich\SberbankAcquiring\OrderStatus;

$client = new Client([ 'userName' => 'userName', 'password' => 'password' ]);

$result = $client->getOrderStatus($orderId);

if (OrderStatus::isDeposited($result['orderStatus'])) {
    echo "Order #$orderId is deposited!";
}
```

### Getting an extended status of an exising order

```php
<?php

use Voronkovich\SberbankAcquiring\Client;
use Voronkovich\SberbankAcquiring\OrderStatus;

$client = new Client([ 'userName' => 'userName', 'password' => 'password' ]);

$result = $client->getOrderStatusExtended($orderId);

if (OrderStatus::isDeclined($result['orderStatus'])) {
    echo "Order #$orderId was declined!";
}
```

### Reversing an exising order

```php
<?php

use Voronkovich\SberbankAcquiring\Client;

$client = new Client([ 'userName' => 'userName', 'password' => 'password' ]);

$result = $client->reverseOrder($orderId);
```

### Refunding an exising order

```php
<?php

use Voronkovich\SberbankAcquiring\Client;

$client = new Client([ 'userName' => 'userName', 'password' => 'password' ]);

$result = $client->refundOrder($orderId, $amountToRefund);
```

## License

Copyright (c) Voronkovich Oleg. Distributed under the MIT.
