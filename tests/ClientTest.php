<?php

namespace Voronkovich\SberbankAcquiring\Tests;

use Voronkovich\SberbankAcquiring\Client;

/**
 * Tests for client.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
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

        $client = new Client(array(
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
        ));

        $client->execute('testAction');
    }

    /**
     * @expectedException \Voronkovich\SberbankAcquiring\Exception\ResponseParsingException
     */
    public function test_execute_malformedJsonResponse()
    {
        $httpClient = $this->mockHttpClient(array(200, 'Malformed json!'));

        $client = new Client(array(
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
        ));

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

        $client = new Client(array(
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
        ));

        $client->execute('testAction');
    }

    public function test_usingCustomHttpClient()
    {
        $httpClient = $this->mockHttpClient();

        $httpClient
            ->expects($this->once())
            ->method('request')
        ;

        $client = new Client(array('userName' => 'oleg', 'password' => 'qwerty123', 'httpClient' => $httpClient));

        $client->execute('testAction');
    }

    public function test_settingHttpMethodAndApiUrl()
    {
        $httpClient = $this->mockHttpClient();

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with('/api/rest/testAction', 'GET')
        ;

        $client = new Client(array(
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
            'httpMethod' => 'GET',
            'apiUri' => '/api/rest/',
        ));

        $client->execute('testAction');
    }

    private function mockHttpClient(array $response = null)
    {
        $httpClient = $this->getMock('\Voronkovich\SberbankAcquiring\HttpClient\HttpClientInterface');

        if (null === $response) {
            $response = array(200, json_encode(array('errorCode' => 0, 'errorMessage' => 'No error.')));
        }

        $httpClient
            ->method('request')
            ->willReturn($response)
        ;

        return $httpClient;
    }
}
