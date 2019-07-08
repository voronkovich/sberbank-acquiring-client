<?php

declare(strict_types=1);

namespace Voronkovich\SberbankAcquiring;

use Voronkovich\SberbankAcquiring\Exception\ActionException;
use Voronkovich\SberbankAcquiring\Exception\BadResponseException;
use Voronkovich\SberbankAcquiring\Exception\NetworkException;
use Voronkovich\SberbankAcquiring\Exception\ResponseParsingException;
use Voronkovich\SberbankAcquiring\HttpClient\CurlClient;
use Voronkovich\SberbankAcquiring\HttpClient\HttpClientInterface;

/**
 * Client for working with Sberbanks's aquiring REST API.
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:start#%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D1%84%D0%B5%D0%B9%D1%81_rest
 */
class Client
{
    const ACTION_SUCCESS = 0;

    const API_URI            = 'https://securepayments.sberbank.ru';
    const API_URI_TEST       = 'https://3dsec.sberbank.ru';
    const API_PREFIX_DEFAULT = '/payment/rest/';
    const API_PREFIX_APPLE   = '/payment/applepay/';
    const API_PREFIX_GOOGLE  = '/payment/google/';
    const API_PREFIX_SAMSUNG = '/payment/samsung/';

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
     * Default API endpoints prefix.
     *
     * @var string
     */
    private $prefixDefault;

    /**
     * Apple Pay endpoint prefix.
     *
     * @var string
     */
    private $prefixApple;

    /**
     * Google Pay endpoint prefix.
     *
     * @var string
     */
    private $prefixGoogle;

    /**
     * Samsung Pay endpoint prefix.
     *
     * @var string
     */
    private $prefixSamsung;

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
            'prefixDefault',
            'prefixApple',
            'prefixGoogle',
            'prefixSamsung',
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
        $this->prefixDefault = $options['prefixDefault'] ?? self::API_PREFIX_DEFAULT;
        $this->prefixApple = $options['prefixApple'] ?? self::API_PREFIX_APPLE;
        $this->prefixGoogle = $options['prefixGoogle'] ?? self::API_PREFIX_GOOGLE;
        $this->prefixSamsung = $options['prefixSamsung'] ?? self::API_PREFIX_SAMSUNG;

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
        return $this->doRegisterOrder($orderId, $amount, $returnUrl, $data, $this->prefixDefault . 'register.do');
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
        return $this->doRegisterOrder($orderId, $amount, $returnUrl, $data, $this->prefixDefault . 'registerPreAuth.do');
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

        if (isset($data['orderBundle']) && is_array($data['orderBundle'])) {
            $data['orderBundle'] = \json_encode($data['orderBundle']);
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

        return $this->execute($this->prefixDefault . 'deposit.do', $data);
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

        return $this->execute($this->prefixDefault . 'reverse.do', $data);
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

        return $this->execute($this->prefixDefault . 'refund.do', $data);
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

        return $this->execute($this->prefixDefault . 'getOrderStatusExtended.do', $data);
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

        return $this->execute($this->prefixDefault . 'verifyEnrollment.do', $data);
    }

    /**
     * Update an SSL card list.
     *
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:updateSSLCardList
     *
     * @param int|string $orderId An order identifier
     * @param array      $data    Additional data
     *
     * @return array A server's response
     */
    public function updateSSLCardList($orderId, array $data = []): array
    {
        $data['mdorder'] = $orderId;

        return $this->execute($this->prefixDefault . 'updateSSLCardList.do', $data);
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

        return $this->execute($this->prefixDefault . 'getLastOrdersForMerchants.do', $data);
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

        return $this->execute($this->prefixDefault . 'paymentOrderBinding.do', $data);
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

        return $this->execute($this->prefixDefault . 'bindCard.do', $data);
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

        return $this->execute($this->prefixDefault . 'unBindCard.do', $data);
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

        return $this->execute($this->prefixDefault . 'extendBinding.do', $data);
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

        return $this->execute($this->prefixDefault . 'getBindings.do', $data);
    }

    /**
     * Get a receipt status.
     *
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:getreceiptstatus
     *
     * @param array $data A data
     *
     * @return array A server's response
     */
    public function getReceiptStatus(array $data): array
    {
        return $this->execute($this->prefixDefault . 'getReceiptStatus.do', $data);
    }

    /**
     * Pay with Apple Pay.
     *
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:payment_applepay
     *
     * @param int|string $orderNumber  Order identifier
     * @param string     $merchant     Merchant
     * @param string     $paymentToken Payment token
     * @param array      $data         Additional data
     *
     * @return array A server's response
     */
    public function payWithApplePay($orderNumber, string $merchant, string $paymentToken, array $data = []): array
    {
        $data['orderNumber'] = $orderNumber;
        $data['merchant'] = $merchant;
        $data['paymentToken'] = $paymentToken;

        return $this->execute($this->prefixApple . 'payment.do', $data);
    }

    /**
     * Pay with Google Pay.
     *
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:payment_googlepay
     *
     * @param int|string $orderNumber  Order identifier
     * @param string     $merchant     Merchant
     * @param string     $paymentToken Payment token
     * @param array      $data         Additional data
     *
     * @return array A server's response
     */
    public function payWithGooglePay($orderNumber, string $merchant, string $paymentToken, array $data = []): array
    {
        $data['orderNumber'] = $orderNumber;
        $data['merchant'] = $merchant;
        $data['paymentToken'] = $paymentToken;

        return $this->execute($this->prefixGoogle . 'payment.do', $data);
    }

    /**
     * Pay with Samsung Pay.
     *
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:payment_samsungpay
     *
     * @param int|string $orderNumber  Order identifier
     * @param string     $merchant     Merchant
     * @param string     $paymentToken Payment token
     * @param array      $data         Additional data
     *
     * @return array A server's response
     */
    public function payWithSamsungPay($orderNumber, string $merchant, string $paymentToken, array $data = []): array
    {
        $data['orderNumber'] = $orderNumber;
        $data['merchant'] = $merchant;
        $data['paymentToken'] = $paymentToken;

        return $this->execute($this->prefixSamsung . 'payment.do', $data);
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
        // Add '/payment/rest/' prefix for BC compatibility if needed
        if ($action[0] !== '/') {
            $action = $this->prefixDefault . $action;
        }

        $rest = 0 === \strpos($action, $this->prefixDefault);

        $uri = $this->apiUri . $action;

        if (!isset($data['language']) && null !== $this->language) {
            $data['language'] = $this->language;
        }

        $headers['Cache-Control'] = 'no-cache';
        $method = $this->httpMethod;

        if ($rest) {
            if (null !== $this->token) {
                $data['token'] = $this->token;
            } else {
                $data['userName'] = $this->userName;
                $data['password'] = $this->password;
            }
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            $data = \http_build_query($data, '', '&');
        } else {
            $headers['Content-Type'] = 'application/json';
            $data = \json_encode($data);
            $method = HttpClientInterface::METHOD_POST;
        }

        $httpClient = $this->getHttpClient();

        list($httpCode, $response) = $httpClient->request($uri, $method, $headers, $data);

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
        } elseif (isset($response['error']['code'])) {
            $errorCode = (int) $response['error']['code'];
        } else {
            $errorCode = self::ACTION_SUCCESS;
        }

        unset($response['errorCode']);
        unset($response['ErrorCode']);

        if (isset($response['errorMessage'])) {
            $errorMessage = $response['errorMessage'];
        } elseif (isset($response['ErrorMessage'])) {
            $errorMessage = $response['ErrorMessage'];
        } elseif (isset($response['error']['message'])) {
            $errorMessage = $response['error']['message'];
        } elseif (isset($response['error']['description'])) {
            $errorMessage = $response['error']['description'];
        } else {
            $errorMessage = 'Unknown error.';
        }

        unset($response['errorMessage']);
        unset($response['ErrorMessage']);
        unset($response['error']);
        unset($response['success']);

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
