<?php

namespace Fiks\YooMoney;

use YooKassa\Client;
use YooKassa\Common\Exceptions\ApiException;
use YooKassa\Common\Exceptions\BadApiRequestException;
use YooKassa\Common\Exceptions\ExtensionNotFoundException;
use YooKassa\Common\Exceptions\ForbiddenException;
use YooKassa\Common\Exceptions\InternalServerError;
use YooKassa\Common\Exceptions\NotFoundException;
use YooKassa\Common\Exceptions\ResponseProcessingException;
use YooKassa\Common\Exceptions\TooManyRequestsException;
use YooKassa\Common\Exceptions\UnauthorizedException;
use YooKassa\Request\Payments\CreatePaymentResponse;

class YooMoneyApi
{
    /**
     * Configuration YooMoney
     *
     * @var array
     */
    private array $config;

    /**
     * YooKassa Client
     *
     * @var Client
     */
    private Client $client;

    /**
     * YooMoneyApi constructor.
     */
    public function __construct(array $config = [])
    {
        $default = config('yoomoney');
        # Configuration
        $this->config = array_merge($config, $default);

        # Create Client
        $this->client = new Client();
        # Create Authorization
        $this->client->setAuth($this->config['shop_id'], $this->config['token']);
    }

    /**
     * Create Payment
     *
     * @param float  $sum
     * @param string $currency
     * @param string $description
     *
     * @return CreatePaymentResponse|null
     *
     * @throws ApiException
     * @throws BadApiRequestException
     * @throws ExtensionNotFoundException
     * @throws ForbiddenException
     * @throws InternalServerError
     * @throws NotFoundException
     * @throws ResponseProcessingException
     * @throws TooManyRequestsException
     * @throws UnauthorizedException
     */
    public function createPayment(float $sum, string $currency, string $description): ?CreatePaymentResponse
    {
        return $this->client->createPayment([
            'amount' => [
                'value' => $sum,
                'currency' => $currency
            ],
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $this->config['redirect_uri']
            ],
            'capture' => true,
            'description' => $description,
        ], uniqid('', true));
    }
}