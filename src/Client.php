<?php

namespace Voronkovich\SberbankAcquiring;

use Voronkovich\SberbankAcquiring\Exception\ActionException;
use Voronkovich\SberbankAcquiring\Exception\NetworkException;
use Voronkovich\SberbankAcquiring\HttpClient\CurlClient;
use Voronkovich\SberbankAcquiring\HttpClient\HttpClientInterface;

/**
 * Client for working with Sberbanks's aquiring REST API.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 * @see http://www.sberbank.ru/ru/s_m_business/bankingservice/internet_acquiring
 */
class Client
{
    private $userName = '';
    private $password = '';

    private $apiUri = 'https://3dsec.sberbank.ru/payment/rest/';
    private $httpMethod = 'POST';

    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    public function __construct(array $settings)
    {
        if (isset($settings['userName'])) {
            $this->userName = $settings['userName'];
        }

        if (isset($settings['password'])) {
            $this->password = $settings['password'];
        }

        if (isset($settings['apiUri'])) {
            $this->apiUri = $settings['apiUri'];
        }

        if (isset($settings['httpMethod'])) {
            $this->setHttpMethod($settings['httpMethod']);
        }

        if (isset($settings['httpClient'])) {
            $this->setHttpClient($settings['httpClient']);
        }
    }

    public function setUserName($userName)
    {
        $this->userName = $userName;

        return $this;
    }

    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    public function setApiUri($apiUri)
    {
        $this->apiUri = $apiUri;

        return $this;
    }

    /**
     * Set an HTTP method.
     *
     * @param string $httpMethod 'GET' or 'POST'
     *
     * @return $this
     */
    public function setHttpMethod($httpMethod)
    {
        if ('GET' !== $httpMethod && 'POST' !== $httpMethod) {
            throw new \UnexpectedValueException('An HTTP method must be "GET" or "POST".');
        }

        $this->httpMethod = $httpMethod;

        return $this;
    }

    /**
     * Set an HTTP client.
     *
     * @param HttpClientInterface $httpClient An HTTP client instance
     */
    public function setHttpClient(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * Execute an action.
     *
     * @param string $action An action's name e.g. 'register.do'
     * @param array  $data   An actions's data
     *
     * @throws \LogicException
     * @throws ActionException
     * @throws NetworkException
     *
     * @return array Server's response
     */
    public function execute($action, array $data = array())
    {
        $uri = $this->apiUri . $action;

        if ('' === $this->userName) {
            throw new \LogicException('UserName is required.');
        }

        if ('' === $this->password) {
            throw new \LogicException('Password is required.');
        }

        $data['userName'] = $this->userName;
        $data['password'] = $this->password;

        $headers = array(
            'Content-type: application/x-www-form-urlencoded',
            'Cache-Control: no-cache',
            'charset="utf-8"',
        );

        $httpClient = $this->getHttpClient();

        $response = $httpClient->request($uri, $this->httpMethod, $headers, $data);
        $response = json_decode($response, true);

        $this->handleResponseError($response);

        return $response;
    }

    private function getHttpClient()
    {
        if (null === $this->httpClient) {
            $this->httpClient = $this->createDefaultHttpClient();
        }

        return $this->httpClient;
    }

    /**
     * Create a default HTTP client.
     *
     * @return HttpClientInterface Client's instance
     */
    private function createDefaultHttpClient()
    {
        return new CurlClient(array(
            \CURLOPT_VERBOSE => false,
            \CURLOPT_SSL_VERIFYHOST => false,
            \CURLOPT_SSL_VERIFYPEER => false,
        ));
    }

    /**
     * Handle a response error.
     *
     * @param array $response A server's response
     *
     * @throws ActionException If an error was occuried
     */
    private function handleResponseError(array $response)
    {
        if (!isset($response['errorCode'])) {
            throw new ActionException('Malformed response: "errorCode" field not found.');
        }

        $errorCode = $response['errorCode'];

        if ('0' === $errorCode) {
            return;
        }

        $errorMessage = isset($response['errorMessage']) ? $response['errorMessage'] : 'Unknown error.';

        throw new ActionException($errorMessage, $errorCode);
    }
}
