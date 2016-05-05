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

        $this->assertInstanceOf('\Voronkovich\SberbankAcquiring\Client', $client);
    }

    /**
     * @expectedException \Voronkovich\SberbankAcquiring\Exception\BadResponseException
     */
    public function test_execute_badResponse()
    {
        $httpClient = $this->mockHttpClient(array(500, 'Internal server error.'));

        $client = new Client(array('userName' => 'oleg', 'password' => 'qwerty123'));
        $this->setHttpClient($client, $httpClient);

        $client->execute('testAction');
    }

    private function setHttpClient($client, $httpClient)
    {
        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($client, $httpClient);
    }

    private function mockHttpClient(array $response)
    {
        $response[0] = json_encode($response[0]);

        $httpClient = $this->getMock('\Voronkovich\SberbankAcquiring\HttpClient\HttpClientInterface');

        $httpClient
            ->method('request')
            ->willReturn($response);

        return $httpClient;
    }
}
