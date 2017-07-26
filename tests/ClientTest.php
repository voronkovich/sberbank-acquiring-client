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

    public function test_registerOrder_sendingData()
    {
        $client = $this->getClientToTestSendingData(array(
            'orderNumber' => 'eee-eee-eee',
            'amount' => 1200,
            'returnUrl' => 'https://github.com/voronkovich/sberbank-acquiring-client',
            'currency' => 330,
        ));

        $client->registerOrder('eee-eee-eee', 1200, 'https://github.com/voronkovich/sberbank-acquiring-client', array('currency' => 330));
    }

    public function test_registerOrderPreAuth_sendingData()
    {
        $client = $this->getClientToTestSendingData(array(
            'orderNumber' => 'eee-eee-eee',
            'amount' => 1200,
            'returnUrl' => 'https://github.com/voronkovich/sberbank-acquiring-client',
            'currency' => 330,
        ));

        $client->registerOrderPreAuth('eee-eee-eee', 1200, 'https://github.com/voronkovich/sberbank-acquiring-client', array('currency' => 330));
    }

    public function test_deposit_sendingData()
    {
        $client = $this->getClientToTestSendingData(array(
            'orderId' => 'aaa-bbb-yyy',
            'amount' => 1000,
            'currency' => 810,
        ));

        $client->deposit('aaa-bbb-yyy', 1000, array('currency' => 810));
    }

    public function test_reverseOrder_sendingData()
    {
        $client = $this->getClientToTestSendingData(array(
            'orderId' => 'aaa-bbb-yyy',
            'currency' => 480,
        ));

        $client->reverseOrder('aaa-bbb-yyy', array('currency' => 480));
    }

    public function test_refundOrder_sendingData()
    {
        $client = $this->getClientToTestSendingData(array(
            'orderId' => 'aaa-bbb-yyy',
            'amount' => 5050,
            'currency' => 456,
        ));

        $client->refundOrder('aaa-bbb-yyy', 5050, array('currency' => 456));
    }

    public function test_getOrderStatus_sendingData()
    {
        $client = $this->getClientToTestSendingData(array(
            'orderId' => 'aaa-bbb-yyy',
            'currency' => 100,
        ));

        $client->getOrderStatus('aaa-bbb-yyy', array('currency' => 100));
    }

    public function test_getOrderStatusExtended_sendingData()
    {
        $client = $this->getClientToTestSendingData(array(
            'orderId' => 'aaa-bbb-yyy',
            'currency' => 100,
        ));

        $client->getOrderStatusExtended('aaa-bbb-yyy', array('currency' => 100));
    }

    public function test_verifyEnrollment_sendingData()
    {
        $client = $this->getClientToTestSendingData(array(
            'pan' => 'aaazzz',
            'currency' => 200,
        ));

        $client->verifyEnrollment('aaazzz', array('currency' => 200));
    }

    public function test_paymentOrderBinding_sendingData()
    {
        $client = $this->getClientToTestSendingData(array(
            'mdOrder' => 'xxx-yyy-zzz',
            'bindingId' => 600,
            'language' => 'en',
        ));

        $client->paymentOrderBinding('xxx-yyy-zzz', 600, array('language' => 'en'));
    }

    public function test_bindCard_sendingData()
    {
        $client = $this->getClientToTestSendingData(array(
            'bindingId' => 'bbb000',
            'language' => 'ru',
        ));

        $client->bindCard('bbb000', array('language' => 'ru'));
    }

    public function test_unBindCard_sendingData()
    {
        $client = $this->getClientToTestSendingData(array(
            'bindingId' => 'uuu800',
            'language' => 'en',
        ));

        $client->unBindCard('uuu800', array('language' => 'en'));
    }

    public function test_extendBinding_sendingData()
    {
        $client = $this->getClientToTestSendingData(array(
            'bindingId' => 'eeeB00',
            'newExpiry' => '203009',
            'language' => 'ru',
        ));

        $client->extendBinding('eeeB00', new \DateTime('2030-09'), array('language' => 'ru'));
    }

    public function test_getBindings_sendingData()
    {
        $client = $this->getClientToTestSendingData(array(
            'clientId' => 'clientIDABC',
            'language' => 'ru',
        ));

        $client->getBindings('clientIDABC', array('language' => 'ru'));
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

    private function getClientToTestSendingData($data)
    {
        $httpClient = $this->mockHttpClient();

        $data['userName'] = 'oleg';
        $data['password'] = 'qwerty123';

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->equalTo($data))
        ;

        $client = new Client(array(
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
        ));

        return $client;
    }
}
