<?php

declare(strict_types=1);

namespace Voronkovich\SberbankAcquiring\Tests;

use PHPUnit\Framework\TestCase;
use Voronkovich\SberbankAcquiring\Client;
use Voronkovich\SberbankAcquiring\Exception\ActionException;
use Voronkovich\SberbankAcquiring\Exception\BadResponseException;
use Voronkovich\SberbankAcquiring\Exception\ResponseParsingException;
use Voronkovich\SberbankAcquiring\HttpClient\HttpClientInterface;

/**
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
class ClientTest extends TestCase
{
    public function testThrowsAnExceptionIfUnkownOptionProvided()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option "foo".');

        $client = new Client(['token' => 'token', 'foo' => 'bar']);
    }

    public function testAllowsToUseAUsernameAndAPasswordForAuthentication()
    {
        $httpClient = $this->mockHttpClient();
        $httpClient
            ->expects($this->atLeastOnce())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->equalTo([
                    'userName' => 'oleg',
                    'password' => 'querty123',
                    'anything' => 'anything',
                ])
            )
        ;

        $client = new Client(['userName' => 'oleg', 'password' => 'querty123', 'httpClient' => $httpClient]);

        $client->execute('somethig.do', ['anything' => 'anything']);
    }

    public function testAllowsToUseATokenForAuthentication()
    {
        $httpClient = $this->mockHttpClient();
        $httpClient
            ->expects($this->atLeastOnce())
            ->method('request')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->equalTo([
                    'token' => 'querty123',
                    'anything' => 'anything',
                ])
            )
        ;

        $client = new Client(['token' => 'querty123', 'httpClient' => $httpClient]);

        $client->execute('somethig.do', ['anything' => 'anything']);
    }

    public function testThrowsAnExceptionIfBothAPasswordAndATokenUsed()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You can use either "userName" and "password" or "token".');

        $client = new Client(['userName' => 'username', 'password' => 'password', 'token' => 'token']);
    }

    public function testThrowsAnExceptionIfNoCredentialsProvided()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You must provide authentication credentials: "userName" and "password", or "token".');

        $client = new Client();
    }

    public function testThrowsAnExceptionIfAnInvalidHttpMethodSpecified()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('An HTTP method "PUT" is not supported. Use "GET" or "POST".');

        $client = new Client([
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpMethod' => 'PUT'
        ]);
    }

    public function testAllowsToUseACustomHttpClient()
    {
        $httpClient = $this->mockHttpClient();

        $httpClient
            ->expects($this->atLeastOnce())
            ->method('request')
        ;

        $client = new Client([
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => $httpClient
        ]);

        $client->execute('testAction');
    }

    public function testThrowsAnExceptionIfAnInvalidHttpClientSpecified()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('An HTTP client must implement HttpClientInterface.');

        $client = new Client([
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => new \stdClass(),
        ]);
    }

    public function testAllowsToSetAnHttpMethodAndApiUrl()
    {
        $httpClient = $this->mockHttpClient();

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with('/api/rest/testAction', 'GET')
        ;

        $client = new Client([
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
            'httpMethod' => 'GET',
            'apiUri' => '/api/rest/',
        ]);

        $client->execute('testAction');
    }

    public function testThrowsAnExceptionIfABadResponseReturned()
    {
        $httpClient = $this->mockHttpClient([500, 'Internal server error.']);

        $client = new Client([
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
        ]);

        $this->expectException(BadResponseException::class);
        $this->expectExceptionMessage('Bad HTTP code: 500.');

        $client->execute('testAction');
    }

    public function testThrowsAnExceptionIfAMalformedJsonReturned()
    {
        $httpClient = $this->mockHttpClient([200, 'Malformed json!']);

        $client = new Client([
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
        ]);

        $this->expectException(ResponseParsingException::class);

        $client->execute('testAction');
    }

    public function testThrowsAnExceptionIfAServerSetAnErrorCode()
    {
        $response = [200, \json_encode(['errorCode' => 100, 'errorMessage' => 'Error!'])];

        $httpClient = $this->mockHttpClient($response);

        $client = new Client([
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => $httpClient
        ]);

        $this->expectException(ActionException::class);
        $this->expectExceptionMessage('Error!');

        $client->execute('testAction');
    }

    public function testRegistersANewOrder()
    {
        $client = $this->getClientToTestSendingData([
            'orderNumber' => 'eee-eee-eee',
            'amount' => 1200,
            'returnUrl' => 'https://github.com/voronkovich/sberbank-acquiring-client',
            'currency' => 330,
        ]);

        $client->registerOrder('eee-eee-eee', 1200, 'https://github.com/voronkovich/sberbank-acquiring-client', ['currency' => 330]);
    }

    public function testRegisterANewPreAuthorizedOrder()
    {
        $client = $this->getClientToTestSendingData([
            'orderNumber' => 'eee-eee-eee',
            'amount' => 1200,
            'returnUrl' => 'https://github.com/voronkovich/sberbank-acquiring-client',
            'currency' => 330,
        ]);

        $client->registerOrderPreAuth('eee-eee-eee', 1200, 'https://github.com/voronkovich/sberbank-acquiring-client', ['currency' => 330]);
    }

    /**
     * @testdox Throws an exception if a "jsonParams" is not an array.
     */
    public function testThrowsAnExceptionIfAJsonParamsIsNotAnArray()
    {
        $client = new Client(['userName' => 'oleg', 'password' => 'qwerty123']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "jsonParams" parameter must be an array.');

        $client->registerOrder(1, 1, 'returnUrl', ['jsonParams' => '{}']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "jsonParams" parameter must be an array.');

        $client->registerOrderPreAuth(1, 1, 'returnUrl', ['jsonParams' => '{}']);
    }

    public function testDepositsAPreAuthorizedOrder()
    {
        $client = $this->getClientToTestSendingData([
            'orderId' => 'aaa-bbb-yyy',
            'amount' => 1000,
            'currency' => 810,
        ]);

        $client->deposit('aaa-bbb-yyy', 1000, ['currency' => 810]);
    }

    public function testReversesAnOrder()
    {
        $client = $this->getClientToTestSendingData([
            'orderId' => 'aaa-bbb-yyy',
            'currency' => 480,
        ]);

        $client->reverseOrder('aaa-bbb-yyy', ['currency' => 480]);
    }

    public function testRefundsAnOrder()
    {
        $client = $this->getClientToTestSendingData([
            'orderId' => 'aaa-bbb-yyy',
            'amount' => 5050,
            'currency' => 456,
        ]);

        $client->refundOrder('aaa-bbb-yyy', 5050, ['currency' => 456]);
    }

    public function testGetsAnOrderStatus()
    {
        $client = $this->getClientToTestSendingData([
            'orderId' => 'aaa-bbb-yyy',
            'currency' => 100,
        ]);

        $client->getOrderStatus('aaa-bbb-yyy', ['currency' => 100]);
    }

    public function testVerifiesACardEnrollment()
    {
        $client = $this->getClientToTestSendingData([
            'pan' => 'aaazzz',
            'currency' => 200,
        ]);

        $client->verifyEnrollment('aaazzz', ['currency' => 200]);
    }

    public function testPaysAnOrderUsingBinding()
    {
        $client = $this->getClientToTestSendingData([
            'mdOrder' => 'xxx-yyy-zzz',
            'bindingId' => '600',
            'language' => 'en',
        ]);

        $client->paymentOrderBinding('xxx-yyy-zzz', '600', ['language' => 'en']);
    }

    public function testBindsACard()
    {
        $client = $this->getClientToTestSendingData([
            'bindingId' => 'bbb000',
            'language' => 'ru',
        ]);

        $client->bindCard('bbb000', ['language' => 'ru']);
    }

    public function testUnbindsACard()
    {
        $client = $this->getClientToTestSendingData([
            'bindingId' => 'uuu800',
            'language' => 'en',
        ]);

        $client->unBindCard('uuu800', ['language' => 'en']);
    }

    public function testExtendsABinding()
    {
        $client = $this->getClientToTestSendingData([
            'bindingId' => 'eeeB00',
            'newExpiry' => '203009',
            'language' => 'ru',
        ]);

        $client->extendBinding('eeeB00', new \DateTime('2030-09'), ['language' => 'ru']);
    }

    public function testGetsBindings()
    {
        $client = $this->getClientToTestSendingData([
            'clientId' => 'clientIDABC',
            'language' => 'ru',
        ]);

        $client->getBindings('clientIDABC', ['language' => 'ru']);
    }

    private function mockHttpClient(array $response = null)
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        if (null === $response) {
            $response = [200, \json_encode(['errorCode' => 0, 'errorMessage' => 'No error.'])];
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

        $client = new Client([
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => $httpClient
        ]);

        return $client;
    }
}
