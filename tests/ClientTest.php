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
     * @expectedException \InvalidArgumentException
     */
    public function test_registerOrder_jsonParamsIsNotAnArray()
    {
        $client = new Client(array('userName' => 'oleg', 'password' => 'qwerty123'));

        $client->registerOrder(1, 1, 'returnUrl', array('jsonParams' => '{}'));
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

    /**
     * @expectedException \Voronkovich\SberbankAcquiring\Exception\ResponseParsingException
     */
    public function test_execute_malformedJsonResponse()
    {
        $httpClient = $this->mockHttpClient(array(200, 'Malformed json!'));

        $client = new Client(array('userName' => 'oleg', 'password' => 'qwerty123'));
        $this->setHttpClient($client, $httpClient);

        $client->execute('testAction');
    }

    /**
     * @expectedException \Voronkovich\SberbankAcquiring\Exception\ActionException
     * @expectedExceptionMessage Error!
     */
    public function test_execute_actionError()
    {
        $response = array(200, json_encode(array('errorCode' => 100, 'errorMessage' => 'Error!')));

        $httpClient = $this->mockHttpClient($response);

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
        $httpClient = $this->getMock('\Voronkovich\SberbankAcquiring\HttpClient\HttpClientInterface');

        $httpClient
            ->method('request')
            ->willReturn($response);

        return $httpClient;
    }
}
