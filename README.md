# sberbank-acquiring-client [![Build Status](https://travis-ci.org/voronkovich/sberbank-acquiring-client.svg?branch=master)](https://travis-ci.org/voronkovich/sberbank-acquiring-client)

PHP client for Sberbank's acquiring REST API.

## Installation

```sh
composer require 'voronkovich/sberbank-acquiring-client:dev-master'
```

## Usage

### Creating a new order

```php
<?php

use Voronkovich\SberbankAcquiring\Client;

$client = new Client([ 'userName' => 'userName', 'password' => 'password' ]);

$orderId = 1234;
$orderAmount = 1000;
$returnUrl = 'http://mycoolshop.local/payment-success';

$result = $client->registerOrder($orderId, $orderAmount, $returnUrl);

list($orderId, $formUrl) = $result;

header('Location: ' . $formUrl);
```

### Getting a status of an exising order

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
