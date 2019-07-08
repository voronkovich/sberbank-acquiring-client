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
                $this->equalTo('anything=anything&userName=oleg&password=querty123')
            )
        ;

        $client = new Client([
            'userName' => 'oleg',
            'password' => 'querty123',
            'httpClient' => $httpClient,
        ]);

        $client->execute('/payment/rest/somethig.do', ['anything' => 'anything']);
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
                $this->equalTo('anything=anything&token=querty123')
            )
        ;

        $client = new Client([
            'token' => 'querty123',
            'httpClient' => $httpClient,
        ]);

        $client->execute('/payment/rest/somethig.do', ['anything' => 'anything']);
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

    /**
     * @testdox Uses an HTTP method POST by default
     */
    public function testUsesAPostHttpMethodByDefault()
    {
        $httpClient = $this->mockHttpClient();

        $httpClient
            ->expects($this->atLeastOnce())
            ->method('request')
            ->with(
                $this->anything(),
                HttpClientInterface::METHOD_POST,
                $this->anything(),
                $this->anything()
            )
        ;

        $client = new Client([
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
        ]);

        $client->execute('/payment/rest/testAction.do');
    }

    public function testAllowsToSetAnHttpMethodAndApiUrl()
    {
        $httpClient = $this->mockHttpClient();

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'https://github.com/voronkovich/sberbank-acquiring-client/payment/rest/testAction.do',
                HttpClientInterface::METHOD_GET
            )
        ;

        $client = new Client([
            'userName' => 'oleg',
            'password' => 'qwerty123',
            'httpClient' => $httpClient,
            'httpMethod' => HttpClientInterface::METHOD_GET,
            'apiUri' => 'https://github.com/voronkovich/sberbank-acquiring-client',
        ]);

        $client->execute('/payment/rest/testAction.do');
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

    /**
     * @dataProvider provideErredResponses
     */
    public function testThrowsAnExceptionIfAServerSetAnErrorCode(array $response)
    {
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

    public function provideErredResponses(): iterable
    {
        yield [[200, \json_encode(['errorCode' => 100, 'errorMessage' => 'Error!'])]];
        yield [[200, \json_encode(['ErrorCode' => 100, 'ErrorMessage' => 'Error!'])]];
        yield [[200, \json_encode(['error' => ['code' => 100, 'message' => 'Error!']])]];
        yield [[200, \json_encode(['error' => ['code' => 100, 'description' => 'Error!']])]];
    }

    public function testRegistersANewOrder()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/register.do',
            'currency=330&orderNumber=eee-eee-eee&amount=1200&returnUrl=https%3A%2F%2Fgithub.com%2Fvoronkovich%2Fsberbank-acquiring-client&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->registerOrder('eee-eee-eee', 1200, 'https://github.com/voronkovich/sberbank-acquiring-client', ['currency' => 330]);
    }

    public function testRegistersANewOrderWithCustomPrefix()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/other/prefix/register.do',
            'currency=330&orderNumber=eee-eee-eee&amount=1200&returnUrl=https%3A%2F%2Fgithub.com%2Fvoronkovich%2Fsberbank-acquiring-client&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
            'prefixDefault'=>'/other/prefix/'
        ]);

        $client->registerOrder('eee-eee-eee', 1200, 'https://github.com/voronkovich/sberbank-acquiring-client', ['currency' => 330]);
    }

    public function testRegisterANewPreAuthorizedOrder()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/registerPreAuth.do',
            'currency=330&orderNumber=eee-eee-eee&amount=1200&returnUrl=https%3A%2F%2Fgithub.com%2Fvoronkovich%2Fsberbank-acquiring-client&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
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

    /**
     * @testdox Encodes to JSON an "orderBundle" parameter.
     */
    public function testEncodesToJSONAnOrderBundleParameter()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/register.do',
            'orderBundle=%7B%22items%22%3A%5B%22item1%22%2C%22item2%22%5D%7D&orderNumber=1&amount=1&returnUrl=returnUrl&token=abc'
        );

        $client = new Client([
            'token' => 'abc',
            'httpClient' => $httpClient,
        ]);

        $client->registerOrder(1, 1, 'returnUrl', [
            'orderBundle' => [
                'items' => [
                    'item1',
                    'item2',
                ],
            ],
        ]);
    }

    public function testDepositsAPreAuthorizedOrder()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/deposit.do',
            'currency=810&orderId=aaa-bbb-yyy&amount=1000&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->deposit('aaa-bbb-yyy', 1000, ['currency' => 810]);
    }

    public function testReversesAnOrder()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/reverse.do',
            'currency=480&orderId=aaa-bbb-yyy&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->reverseOrder('aaa-bbb-yyy', ['currency' => 480]);
    }

    public function testRefundsAnOrder()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/refund.do',
            'currency=456&orderId=aaa-bbb-yyy&amount=5050&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->refundOrder('aaa-bbb-yyy', 5050, ['currency' => 456]);
    }

    public function testGetsAnOrderStatus()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/rest/getOrderStatusExtended.do',
            'currency=100&orderId=aaa-bbb-yyy&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->getOrderStatus('aaa-bbb-yyy', ['currency' => 100]);
    }

    public function testVerifiesACardEnrollment()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/verifyEnrollment.do',
            'currency=200&pan=aaazzz&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->verifyEnrollment('aaazzz', ['currency' => 200]);
    }

    /**
     * @testdox Updates an SSL card list
     */
    public function testUpdatesAnSSLCardList()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/updateSSLCardList.do',
            'mdorder=aaazzz&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->updateSSLCardList('aaazzz');
    }

    public function testPaysAnOrderUsingBinding()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/paymentOrderBinding.do',
            'language=en&mdOrder=xxx-yyy-zzz&bindingId=600&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->paymentOrderBinding('xxx-yyy-zzz', '600', ['language' => 'en']);
    }

    public function testBindsACard()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/bindCard.do',
            'language=ru&bindingId=bbb000&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->bindCard('bbb000', ['language' => 'ru']);
    }

    public function testUnbindsACard()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/unBindCard.do',
            'language=en&bindingId=uuu800&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->unBindCard('uuu800', ['language' => 'en']);
    }

    public function testExtendsABinding()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/extendBinding.do',
            'language=ru&bindingId=eeeB00&newExpiry=203009&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->extendBinding('eeeB00', new \DateTime('2030-09'), ['language' => 'ru']);
    }

    public function testGetsBindings()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/getBindings.do',
            'language=ru&clientId=clientIDABC&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->getBindings('clientIDABC', ['language' => 'ru']);
    }

    public function testGetsARepceiptStatus()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/rest/getReceiptStatus.do',
            'uuid=ffff&language=ru&token=abrakadabra'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->getReceiptStatus(['uuid' => 'ffff', 'language' => 'ru']);
    }

    /**
     * @testdox Pays with an "Apple Pay"
     */
    public function testPaysWithAnApplePay()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/applepay/payment.do',
            '{"language":"en","orderNumber":"eee-eee","merchant":"my_merchant","paymentToken":"token_zzz"}'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->payWithApplePay('eee-eee', 'my_merchant', 'token_zzz', ['language' => 'en']);
    }

    /**
     * @testdox Pays with an "Apple Pay" with custom prefix
     */
    public function testPaysWithAnApplePayWithCustomPrefix()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/other/prefix/payment.do',
            '{"language":"en","orderNumber":"eee-eee","merchant":"my_merchant","paymentToken":"token_zzz"}'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
            'prefixApple'=>'/other/prefix/'
        ]);

        $client->payWithApplePay('eee-eee', 'my_merchant', 'token_zzz', ['language' => 'en']);
    }

    /**
     * @testdox Pays with a "Google Pay"
     */
    public function testPaysWithAGooglePay()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/google/payment.do',
            '{"language":"en","orderNumber":"eee-eee","merchant":"my_merchant","paymentToken":"token_zzz"}'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->payWithGooglePay('eee-eee', 'my_merchant', 'token_zzz', ['language' => 'en']);
    }

    /**
     * @testdox Pays with a "Google Pay" with custom prefix
     */
    public function testPaysWithAGooglePayWithCustomPrefix()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/other/prefix/google/payment.do',
            '{"language":"en","orderNumber":"eee-eee","merchant":"my_merchant","paymentToken":"token_zzz"}'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
            'prefixGoogle'=>'/other/prefix/google/'
        ]);

        $client->payWithGooglePay('eee-eee', 'my_merchant', 'token_zzz', ['language' => 'en']);
    }

    /**
     * @testdox Pays with a "Samsung Pay"
     */
    public function testPaysWithASamsungPay()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/payment/samsung/payment.do',
            '{"language":"en","orderNumber":"eee-eee","merchant":"my_merchant","paymentToken":"token_zzz"}'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
        ]);

        $client->payWithSamsungPay('eee-eee', 'my_merchant', 'token_zzz', ['language' => 'en']);
    }

    /**
     * @testdox Pays with a "Samsung Pay" with custom prefix
     */
    public function testPaysWithASamsungPayWithCustomPrefix()
    {
        $httpClient = $this->getHttpClientToTestSendingData(
            '/other/prefix/sumsung/payment.do',
            '{"language":"en","orderNumber":"eee-eee","merchant":"my_merchant","paymentToken":"token_zzz"}'
        );

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
            'prefixSamsung' => '/other/prefix/sumsung/',
        ]);

        $client->payWithSamsungPay('eee-eee', 'my_merchant', 'token_zzz', ['language' => 'en']);
    }

    public function testAddsASpecialPrefixToActionForBackwardCompatibility()
    {
        $httpClient = $this->mockHttpClient();

        $httpClient
            ->expects($this->atLeastOnce())
            ->method('request')
            ->with($this->equalTo(Client::API_URI.'/payment/rest/getOrderStatusExtended.do'))
        ;

        $client = new Client([
            'token' => 'abrakadabra',
            'httpClient' => $httpClient,
            'apiUri' => Client::API_URI,
        ]);

        $client->execute('getOrderStatusExtended.do');
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

    private function getHttpClientToTestSendingData(string $uri, string $data)
    {
        $httpClient = $this->mockHttpClient();

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with($this->stringEndsWith($uri), $this->anything(), $this->anything(), $this->equalTo($data))
        ;

        return $httpClient;
    }
}
