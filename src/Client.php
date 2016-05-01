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

    /**
     * Language code in ISO 639-1 format.
     *
     * @var string
     */
    private $language = 'en';

    private $apiUri = 'https://3dsec.sberbank.ru/payment/rest/';
    private $httpMethod = 'POST';

    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    /**
     * Constructor.
     *
     * @param array $settings Client's settings
     */
    public function __construct(array $settings)
    {
        if (isset($settings['userName'])) {
            $this->userName = $settings['userName'];
        } else {
            throw new \LogicException('UserName is required.');
        }

        if (isset($settings['password'])) {
            $this->password = $settings['password'];
        } else {
            throw new \LogicException('Password is required.');
        }

        if (isset($settings['language'])) {
            $this->language = $settings['language'];
        }

        if (isset($settings['apiUri'])) {
            $this->apiUri = $settings['apiUri'];
        }

        if (isset($settings['httpMethod'])) {
            if ('GET' !== $settings['httpMethod'] && 'POST' !== $settings['httpMethod']) {
                throw new \UnexpectedValueException(sprintf('An HTTP method "%s" is not supported. Use "GET" or "POST".', $settings['httpMethod']));
            }

            $this->httpMethod = $settings['httpMethod'];
        }

        if (isset($settings['httpClient'])) {
            if (!$settings instanceof HttpClientInterface) {
                throw new \UnexpectedValueException('An HTTP client must implement HttpClientInterface.');
            }

            $this->httpClient = $settings['httpClient'];
        }
    }

    /**
     * Register a new order.
     *
     * @param int|string $orderNumber An order identifier
     * @param int        $amount      An order amount
     * @param string     $returnUrl   An url for redirecting a user after successfull order handling
     * @param array      $data        Additional data
     *
     * @return array A server's response
     */
    public function registerOrder($orderNumber, $amount, $returnUrl, array $data = array())
    {
        $data['orderNumber'] = $orderNumber;
        $data['amount']      = $amount;
        $data['returnUrl']   = $returnUrl;

        return $this->execute('register.do', $data);
    }

    /**
     * Reverse an existing order.
     *
     * @param string $orderId An order identifier
     * @param array  $data    Additional data
     *
     * @return array A server's response
     */
    public function reverseOrder($orderId, array $data = array())
    {
        $data['orderId'] = $orderId;

        return $this->execute('reverse.do', $data);
    }

    /**
     * Refund an existing order.
     *
     * @param string $orderId An order identifier
     * @param int    $amount  An amount to refund
     * @param array  $data    Additional data
     *
     * @return array A server's response
     */
    public function refundOrder($orderId, $amount, array $data = array())
    {
        $data['orderId'] = $orderId;
        $data['amount']  = $amount;

        return $this->execute('refund.do', $data);
    }

    /**
     * Get an existing order's status.
     *
     * @param string $orderId An order identifier
     * @param array  $data    Additional data
     *
     * @return array A server's response
     */
    public function getOrderStatus($orderId, array $data = array())
    {
        $data['orderId'] = $orderId;

        return $this->execute('getOrderStatus.do', $data);
    }

    /**
     * Get an existing order's extended status.
     *
     * @param string $orderId An order identifier
     * @param array  $data    Additional data
     *
     * @return array A server's response
     */
    public function getOrderStatusExtended($orderId, array $data = array())
    {
        $data['orderId'] = $orderId;

        return $this->execute('getOrderStatusExtended.do', $data);
    }

    /**
     * Execute an action.
     *
     * @param string $action An action's name e.g. 'register.do'
     * @param array  $data   An actions's data
     *
     * @throws ActionException
     * @throws NetworkException
     *
     * @return array A server's response
     */
    public function execute($action, array $data = array())
    {
        $uri = $this->apiUri . $action;

        $headers = array(
            'Cache-Control: no-cache',
        );

        $data['userName'] = $this->userName;
        $data['password'] = $this->password;
        $data['language'] = $this->language;

        if (isset($data['jsonParams'])) {
            $data['jsonParams'] = json_encode($data['jsonParams']);
        }

        $httpClient = $this->getHttpClient();

        $response = $httpClient->request($uri, $this->httpMethod, $headers, $data);
        $response = json_decode($response, true);

        $this->handleResponseError($response);

        return $response;
    }

    /**
     * Get an HTTP client.
     *
     * @return HttpClientInterface
     */
    private function getHttpClient()
    {
        if (null === $this->httpClient) {
            $this->httpClient = new CurlClient(array(
                \CURLOPT_VERBOSE => false,
                \CURLOPT_SSL_VERIFYHOST => false,
                \CURLOPT_SSL_VERIFYPEER => false,
            ));
        }

        return $this->httpClient;
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
        if (isset($response['errorCode'])) {
            $errorCode = $response['errorCode'];
        } elseif (isset($response['ErrorCode'])) {
            $errorCode = $response['ErrorCode'];
        } else {
            return;
        }

        if ('0' === $errorCode) {
            return;
        }

        if (isset($response['errorMessage'])) {
            $errorMessage = $response['errorMessage'];
        } elseif (isset($response['ErrorMessage'])) {
            $errorMessage = $response['ErrorMessage'];
        } else {
            $errorMessage = 'Unknown error.';
        }

        throw new ActionException($errorMessage, $errorCode);
    }
}
