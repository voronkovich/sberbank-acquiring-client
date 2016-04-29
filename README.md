# sberbank-acquiring-client

PHP client for Sberbank's acquiring REST API.

## Installation

```sh
composer require 'voronkovich/sberbank-acquiring-client:dev-master'
```

## Usage

```php
<?php

use Voronkovich\SberbankAcquiring\Client;

$client = new Client([ 'userName' => 'userName', 'password' => 'password' ]);

// Register new order
$result = $client->execute('register.do', [
    'orderNumber' => 1234,
    'amount'      => 60000,
    'returnUrl'   => 'http://mycoolshop.dev/fail',
]);
```
