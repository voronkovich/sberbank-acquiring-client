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

    /**
     * @var string
     */
    private $userName;

    /**
     * @var string
     */
    private $password;

    /**
     * Authentication token.
     *
     * @var string
     */
    private $token;

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
    private $httpMethod = HttpClientInterface::METHOD_POST;

    private $dateFormat = 'YmdHis';

    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    public function __construct(array $options = [])
    {
        if (!\extension_loaded('json')) {
            throw new \RuntimeException('JSON extension is not loaded.');
        }

        $allowedOptions = [
            'apiUri',
            'currency',
            'httpClient',
            'httpMethod',
            'language',
            'password',
            'token',
            'userName',
        ];

        $unknownOptions = \array_diff(\array_keys($options), $allowedOptions);

        if (!empty($unknownOptions)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Unknown option "%s". Allowed options: "%s".',
                    \reset($unknownOptions),
                    \implode('", "', $allowedOptions)
                )
            );
        }

        if (isset($options['userName']) && isset($options['password'])) {
            if (isset($options['token'])) {
                throw new \InvalidArgumentException('You can use either "userName" and "password" or "token".');
            }

            $this->userName = $options['userName'];
            $this->password = $options['password'];
        } elseif (isset($options['token'])) {
            $this->token = $options['token'];
        } else {
            throw new \InvalidArgumentException('You must provide authentication credentials: "userName" and "password", or "token".');
        }

        $this->language = $options['language'] ?? null;
        $this->currency = $options['currency'] ?? null;
        $this->apiUri = $options['apiUri'] ?? self::API_URI;

        if (isset($options['httpMethod'])) {
            if (!\in_array($options['httpMethod'], [ HttpClientInterface::METHOD_GET, HttpClientInterface::METHOD_POST ])) {
                throw new \InvalidArgumentException(
                    \sprintf(
                        'An HTTP method "%s" is not supported. Use "%s" or "%s".',
                        $options['httpMethod'],
                        HttpClientInterface::METHOD_GET,
                        HttpClientInterface::METHOD_POST
                    )
                );
            }

            $this->httpMethod = $options['httpMethod'];
        }

        if (isset($options['httpClient'])) {
            if (!$options['httpClient'] instanceof HttpClientInterface) {
                throw new \InvalidArgumentException('An HTTP client must implement HttpClientInterface.');
            }

            $this->httpClient = $options['httpClient'];
        }
    }

    /**
     * Register a new order.
     *
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:register
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
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:registerpreauth
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
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:deposit
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
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:reverse
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
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:refund
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
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:getorderstatusextended
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
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:verifyEnrollment
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
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:getLastOrdersForMerchants
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
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:paymentOrderBinding
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
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:bindCard
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
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:unBindCard
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
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:extendBinding
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
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:getBindings
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

        if (null !== $this->token) {
            $data['token'] = $this->token;
        } else {
            $data['userName'] = $this->userName;
            $data['password'] = $this->password;
        }

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
