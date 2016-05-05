<?php

namespace Voronkovich\SberbankAcquiring;

use Voronkovich\SberbankAcquiring\Exception\ActionException;
use Voronkovich\SberbankAcquiring\Exception\BadResponseException;
use Voronkovich\SberbankAcquiring\Exception\NetworkException;
use Voronkovich\SberbankAcquiring\Exception\ResponseParsingException;
use Voronkovich\SberbankAcquiring\HttpClient\CurlClient;
use Voronkovich\SberbankAcquiring\HttpClient\HttpClientInterface;
use Voronkovich\SberbankAcquiring\OrderStatus;

/**
 * Client for working with Sberbanks's aquiring REST API.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 * @see http://www.sberbank.ru/ru/s_m_business/bankingservice/internet_acquiring
 */
class Client
{
    const ACTION_SUCCESS = 0;

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

    private $dateFormat = 'YmdHis';

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
            throw new \InvalidArgumentException('UserName is required.');
        }

        if (isset($settings['password'])) {
            $this->password = $settings['password'];
        } else {
            throw new \InvalidArgumentException('Password is required.');
        }

        if (isset($settings['language'])) {
            $this->language = $settings['language'];
        }

        if (isset($settings['apiUri'])) {
            $this->apiUri = $settings['apiUri'];
        }

        if (isset($settings['httpMethod'])) {
            if ('GET' !== $settings['httpMethod'] && 'POST' !== $settings['httpMethod']) {
                throw new \DomainException(sprintf('An HTTP method "%s" is not supported. Use "GET" or "POST".', $settings['httpMethod']));
            }

            $this->httpMethod = $settings['httpMethod'];
        }

        if (isset($settings['httpClient'])) {
            if (!$settings instanceof HttpClientInterface) {
                throw new \InvalidArgumentException('An HTTP client must implement HttpClientInterface.');
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
     * Verify card enrollment in the 3DS.
     *
     * @param string $pan  A primary account number
     * @param array  $data Additional data
     *
     * @return array A server's response
     */
    public function verifyEnrollment($pan, array $data = array())
    {
        $data['pan'] = $pan;

        return $this->execute('verifyEnrollment.do', $data);
    }

    /**
     * Get last orders for merchants.
     *
     * @param \DateTime      $from A begining date of a period
     * @param \DateTime|null $to   An ending date of a period
     * @param array          $data Additional data
     *
     * @thows \UnexpectedValueException
     *
     * @return array A server's response
     */
    public function getLastOrdersForMerchants(\DateTime $from, \DateTime $to = null, array $data = array())
    {
        if (null === $to) {
            $to = new \DateTime();
        }

        if ($from >= $to) {
            throw new \InvalidArgumentException('A "from" parameter must be less than "to" parameter.');
        }

        $allowedStatuses = array(
            OrderStatus::CREATED,
            OrderStatus::APPROVED,
            OrderStatus::DEPOSITED,
            OrderStatus::REVERSED,
            OrderStatus::DECLINED,
            OrderStatus::REFUNDED,
        );

        if (isset($data['transactionStates'])) {
            if (!is_array($data['transactionStates'])) {
                throw new \InvalidArgumentException('A "transactionStates" parameter must be an array.');
            }

            if (empty($data['transactionStates'])) {
                throw new \InvalidArgumentException('A "transactionStates" parameter cannot be empty.');
            } elseif (!empty(array_diff($data['transactionStates'], $allowedStatuses))) {
                throw new \DomainException('A "transactionStates" parameter contains not allowed values.');
            }
        } else {
            $data['transactionStates'] = $allowedStatuses;
        }

        $data['transactionStates'] = array_map('Voronkovich\SberbankAcquiring\OrderStatus::statusToString', $data['transactionStates']);

        if (isset($data['merchants'])) {
            if (!is_array($data['merchants'])) {
                throw new \InvalidArgumentException('A "merchants" parameter must be an array.');
            }
        } else {
            $data['merchants'] = array();
        }

        $data['from']              = $from->format($this->dateFormat);
        $data['to']                = $to->format($this->dateFormat);
        $data['transactionStates'] = implode(array_unique($data['transactionStates']), ',');
        $data['merchants']         = implode(array_unique($data['merchants']), ',');

        return $this->execute('getLastOrdersForMerchants.do', $data);
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

        list($httpCode, $response) = $httpClient->request($uri, $this->httpMethod, $headers, $data);

        if (200 !== $httpCode) {
            $badResponseException = new BadResponseException(sprintf('Bad HTTP code: %d.', $httpCode), $httpCode);
            $badResponseException->setResponse($response);

            throw $badResponseException;
        }

        $response = $this->parseResponse($response);
        $response = $this->normalizeResponse($response);

        if (self::ACTION_SUCCESS !== $response['errorCode']) {
            throw new ActionException($response['errorMessage'], $response['errorCode']);
        }

        return $response;
    }

    /**
     * Parse a servers's response.
     *
     * @param string $response A string in the JSON format
     *
     * @throws ResponseParsingException
     *
     * @return array
     */
    private function parseResponse($response)
    {
        $response  = json_decode($response, true);
        $errorCode = json_last_error();

        if (\JSON_ERROR_NONE !== $errorCode || null === $response) {
            $errorMessage = function_exists('json_last_error_msg') ? json_last_error_msg() : 'JSON parsing error.';

            throw new ResponseParsingException($errorMessage, $errorCode);
        }

        return $response;
    }

    /**
     * Normalize server's response.
     *
     * Server's response can contain an error code and an error message in differend fields.
     * This method handles those situations and normalizes the response.
     *
     * @param array $response A response
     *
     * @return array A normalized response
     */
    private function normalizeResponse(array $response)
    {
        if (isset($response['errorCode'])) {
            $errorCode = (int) $response['errorCode'];
        } elseif (isset($response['ErrorCode'])) {
            $errorCode = (int) $response['ErrorCode'];
        } else {
            $errorCode = self::ACTION_SUCCESS;
        }

        unset($response['ErrorCode']);
        $response['errorCode'] = $errorCode;

        if (isset($response['errorMessage'])) {
            $errorMessage = $response['errorMessage'];
        } elseif (isset($response['ErrorMessage'])) {
            $errorMessage = $response['ErrorMessage'];
        } else {
            $errorMessage = 'Unknown error.';
        }

        unset($response['ErrorMessage']);
        $response['errorMessage'] = $errorMessage;

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
}
