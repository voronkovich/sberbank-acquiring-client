<?php

namespace Voronkovich\SberbankAcquiring\Tests;

use Voronkovich\SberbankAcquiring\Client;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage UserName is required.
     */
    public function test_constructor_userNameIsNotSpecified()
    {
        $client = new Client(array('password' => 'veryStrongPasswordQwerty123'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Password is required.
     */
    public function test_constructor_passwordIsNotSpecified()
    {
        $client = new Client(array('userName' => 'oleg'));
    }

    /**
     * @expectedException \DomainException
     */
    public function test_constructor_invalidHttpMethod()
    {
        $client = new Client(array('userName' => 'oleg', 'password' => 'qwerty123', 'httpMethod' => 'PUT'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function test_constructor_invalidHttpClient()
    {
        $client = new Client(array('userName' => 'oleg', 'password' => 'qwerty123', 'httpClient' => new \stdClass()));
    }

    public function test_constructor_shouldCreateInstanceOfClientClass()
    {
        $client = new Client(array('userName' => 'oleg', 'password' => 'qwerty123'));

        $this->assertInstanceOf('Voronkovich\SberbankAcquiring\Client', $client);
    }
}
