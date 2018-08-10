<?php

declare(strict_types=1);

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
 * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:start#%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D1%84%D0%B5%D0%B9%D1%81_rest
 */
class Client
{
    const ACTION_SUCCESS = 0;

    const API_URI      = 'https://securepayments.sberbank.ru/payment/rest/';
    const API_URI_TEST = 'https://3dsec.sberbank.ru/payment/rest/';

    private $userName = '';
    private $password = '';

    /**
     * Currency code in ISO 4217 format.
     *
     * @var int
     */
    private $currency;

    /**
     * A language code in ISO 639-1 format ('en', 'ru' and etc.).
     *
     * @var string
     */
    private $language;

    /**
     * An API uri.
     *
     * @var string
     */
    private $apiUri;

    /**
     * An HTTP method.
     *
     * @var string
     */
    private $httpMethod = 'POST';

    private $dateFormat = 'YmdHis';

    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    public function __construct(string $username, string $password, array $settings = [])
    {
        if (!\extension_loaded('json')) {
            throw new \RuntimeException('JSON extension is not loaded.');
        }

        $this->userName = $username;
        $this->password = $password;
        $this->language = $settings['language'] ?? null;
        $this->currency = $settings['currency'] ?? null;
        $this->apiUri = $settings['apiUri'] ?? self::API_URI;

        if (isset($settings['httpMethod'])) {
            if (!in_array($settings['httpMethod'], [ 'GET', 'POST' ])) {
                throw new \InvalidArgumentException(sprintf('An HTTP method "%s" is not supported. Use "GET" or "POST".', $settings['httpMethod']));
            }

            $this->httpMethod = $settings['httpMethod'];
        }

        if (isset($settings['httpClient'])) {
            if (!$settings['httpClient'] instanceof HttpClientInterface) {
                throw new \InvalidArgumentException('An HTTP client must implement HttpClientInterface.');
            }

            $this->httpClient = $settings['httpClient'];
        }
    }

    /**
     * Register a new order.
     *
     * @param int|string $orderId   An order identifier
     * @param int        $amount    An order amount
     * @param string     $returnUrl An url for redirecting a user after successfull order handling
     * @param array      $data      Additional data
     *
     * @return array A server's response
     */
    public function registerOrder($orderId, int $amount, string $returnUrl, array $data = []): array
    {
        return $this->doRegisterOrder($orderId, $amount, $returnUrl, $data, 'register.do');
    }

    /**
     * Register a new order using a 2-step payment process.
     *
     * @param int|string $orderId   An order identifier
     * @param int        $amount    An order amount
     * @param string     $returnUrl An url for redirecting a user after successfull order handling
     * @param array      $data      Additional data
     *
     * @return array A server's response
     */
    public function registerOrderPreAuth($orderId, int $amount, string $returnUrl, array $data = []): array
    {
        return $this->doRegisterOrder($orderId, $amount, $returnUrl, $data, 'registerPreAuth.do');
    }

    private function doRegisterOrder($orderId, int $amount, string $returnUrl, array $data = [], $method = 'register.do'): array
    {
        $data['orderNumber'] = $orderId;
        $data['amount']      = $amount;
        $data['returnUrl']   = $returnUrl;

        if (!isset($data['currency']) && null !== $this->currency) {
            $data['currency'] = $this->currency;
        }

        if (isset($data['jsonParams'])) {
            if (!is_array($data['jsonParams'])) {
                throw new \InvalidArgumentException('The "jsonParams" parameter must be an array.');
            }

            $data['jsonParams'] = json_encode($data['jsonParams']);
        }

        return $this->execute($method, $data);
    }

    /**
     * Deposit an existing order.
     *
     * @param int|string $orderId An order identifier
     * @param int        $amount  An order amount
     * @param array      $data    Additional data
     *
     * @return array A server's response
     */
    public function deposit($orderId, int $amount, array $data = []): array
    {
        $data['orderId'] = $orderId;
        $data['amount']  = $amount;

        return $this->execute('deposit.do', $data);
    }

    /**
     * Reverse an existing order.
     *
     * @param int|string $orderId An order identifier
     * @param array      $data    Additional data
     *
     * @return array A server's response
     */
    public function reverseOrder($orderId, array $data = []): array
    {
        $data['orderId'] = $orderId;

        return $this->execute('reverse.do', $data);
    }

    /**
     * Refund an existing order.
     *
     * @param int|string $orderId An order identifier
     * @param int        $amount  An amount to refund
     * @param array      $data    Additional data
     *
     * @return array A server's response
     */
    public function refundOrder($orderId, int $amount, array $data = []): array
    {
        $data['orderId'] = $orderId;
        $data['amount']  = $amount;

        return $this->execute('refund.do', $data);
    }

    /**
     * Get an existing order's status.
     *
     * @param int|string $orderId An order identifier
     * @param array      $data    Additional data
     *
     * @return array A server's response
     */
    public function getOrderStatus($orderId, array $data = []): array
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
    public function verifyEnrollment(string $pan, array $data = []): array
    {
        $data['pan'] = $pan;

        return $this->execute('verifyEnrollment.do', $data);
    }

    /**
     * Get last orders for merchants.
     *
     * @param \DateTimeInterface      $from A begining date of a period
     * @param \DateTimeInterface|null $to   An ending date of a period
     * @param array          $data Additional data
     *
     * @return array A server's response
     */
    public function getLastOrdersForMerchants(\DateTimeInterface $from, \DateTimeInterface $to = null, array $data = []): array
    {
        if (null === $to) {
            $to = new \DateTime();
        }

        if ($from >= $to) {
            throw new \InvalidArgumentException('A "from" parameter must be less than "to" parameter.');
        }

        $allowedStatuses = [
            OrderStatus::CREATED,
            OrderStatus::APPROVED,
            OrderStatus::DEPOSITED,
            OrderStatus::REVERSED,
            OrderStatus::DECLINED,
            OrderStatus::REFUNDED,
        ];

        if (isset($data['transactionStates'])) {
            if (!is_array($data['transactionStates'])) {
                throw new \InvalidArgumentException('A "transactionStates" parameter must be an array.');
            }

            if (empty($data['transactionStates'])) {
                throw new \InvalidArgumentException('A "transactionStates" parameter cannot be empty.');
            } elseif (0 < count(array_diff($data['transactionStates'], $allowedStatuses))) {
                throw new \InvalidArgumentException('A "transactionStates" parameter contains not allowed values.');
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
            $data['merchants'] = [];
        }

        $data['from']              = $from->format($this->dateFormat);
        $data['to']                = $to->format($this->dateFormat);
        $data['transactionStates'] = implode(array_unique($data['transactionStates']), ',');
        $data['merchants']         = implode(array_unique($data['merchants']), ',');

        return $this->execute('getLastOrdersForMerchants.do', $data);
    }

    /**
     * Payment order binding.
     *
     * @param int|string $orderId   An order identifier
     * @param int|string $bindingId A binding identifier
     * @param array      $data      Additional data
     *
     * @return array A server's response
     */
    public function paymentOrderBinding($orderId, $bindingId, array $data = []): array
    {
        $data['mdOrder']   = $orderId;
        $data['bindingId'] = $bindingId;

        return $this->execute('paymentOrderBinding.do', $data);
    }

    /**
     * Activate a binding.
     *
     * @param int|string $bindingId A binding identifier
     * @param array      $data      Additional data
     *
     * @return array A server's response
     */
    public function bindCard($bindingId, array $data = []): array
    {
        $data['bindingId'] = $bindingId;

        return $this->execute('bindCard.do', $data);
    }

    /**
     * Deactivate a binding.
     *
     * @param int|string $bindingId A binding identifier
     * @param array      $data      Additional data
     *
     * @return array A server's response
     */
    public function unBindCard($bindingId, array $data = []): array
    {
        $data['bindingId'] = $bindingId;

        return $this->execute('unBindCard.do', $data);
    }

    /**
     * Extend a binding.
     *
     * @param int|string          $bindingId  A binding identifier
     * @param \DateTimeInterface  $newExprity A new expiration date
     * @param array               $data       Additional data
     *
     * @return array A server's response
     */
    public function extendBinding($bindingId, \DateTimeInterface $newExpiry, array $data = []): array
    {
        $data['bindingId'] = $bindingId;
        $data['newExpiry'] = $newExpiry->format('Ym');

        return $this->execute('extendBinding.do', $data);
    }

    /**
     * Get bindings.
     *
     * @param int|string $clientId A binding identifier
     * @param array      $data     Additional data
     *
     * @return array A server's response
     */
    public function getBindings($clientId, array $data = []): array
    {
        $data['clientId'] = $clientId;

        return $this->execute('getBindings.do', $data);
    }

    /**
     * Execute an action.
     *
     * @param string $action An action's name e.g. 'register.do'
     * @param array  $data   An actions's data
     *
     * @throws NetworkException
     *
     * @return array A server's response
     */
    public function execute(string $action, array $data = []): array
    {
        $uri = $this->apiUri . $action;

        $headers = [
            'Cache-Control: no-cache',
        ];

        $data['userName'] = $this->userName;
        $data['password'] = $this->password;

        if (!isset($data['language']) && null !== $this->language) {
            $data['language'] = $this->language;
        }

        $httpClient = $this->getHttpClient();

        list($httpCode, $response) = $httpClient->request($uri, $this->httpMethod, $headers, $data);

        if (200 !== $httpCode) {
            $badResponseException = new BadResponseException(sprintf('Bad HTTP code: %d.', $httpCode), $httpCode);
            $badResponseException->setResponse($response);

            throw $badResponseException;
        }

        $response = $this->parseResponse($response);
        $this->handleErrors($response);

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
    private function parseResponse(string $response): array
    {
        $response  = \json_decode($response, true);
        $errorCode = \json_last_error();

        if (\JSON_ERROR_NONE !== $errorCode || null === $response) {
            throw new ResponseParsingException(\json_last_error_msg(), $errorCode);
        }

        return $response;
    }

    /**
     * Normalize server's response.
     *
     * @param array $response A response
     *
     * @throws ActionException
     */
    private function handleErrors(array &$response)
    {
        // Server's response can contain an error code and an error message in differend fields.
        if (isset($response['errorCode'])) {
            $errorCode = (int) $response['errorCode'];
        } elseif (isset($response['ErrorCode'])) {
            $errorCode = (int) $response['ErrorCode'];
        } else {
            $errorCode = self::ACTION_SUCCESS;
        }

        unset($response['errorCode']);
        unset($response['ErrorCode']);

        if (isset($response['errorMessage'])) {
            $errorMessage = $response['errorMessage'];
        } elseif (isset($response['ErrorMessage'])) {
            $errorMessage = $response['ErrorMessage'];
        } else {
            $errorMessage = 'Unknown error.';
        }

        unset($response['errorMessage']);
        unset($response['ErrorMessage']);

        if (self::ACTION_SUCCESS !== $errorCode) {
            throw new ActionException($errorMessage, $errorCode);
        }
    }

    /**
     * Get an HTTP client.
     */
    private function getHttpClient(): HttpClientInterface
    {
        if (null === $this->httpClient) {
            $this->httpClient = new CurlClient([
                \CURLOPT_VERBOSE => false,
                \CURLOPT_SSL_VERIFYHOST => false,
                \CURLOPT_SSL_VERIFYPEER => false,
            ]);
        }

        return $this->httpClient;
    }
}
