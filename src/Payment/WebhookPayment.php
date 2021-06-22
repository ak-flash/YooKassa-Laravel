<?php

namespace Fiks\YooKassa\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use YooKassa\Client;
use YooKassa\Common\Exceptions\ApiException;
use YooKassa\Common\Exceptions\AuthorizeException;
use YooKassa\Common\Exceptions\BadApiRequestException;
use YooKassa\Common\Exceptions\ExtensionNotFoundException;
use YooKassa\Common\Exceptions\ForbiddenException;
use YooKassa\Common\Exceptions\InternalServerError;
use YooKassa\Common\Exceptions\NotFoundException;
use YooKassa\Common\Exceptions\ResponseProcessingException;
use YooKassa\Common\Exceptions\TooManyRequestsException;
use YooKassa\Common\Exceptions\UnauthorizedException;
use YooKassa\Model\NotificationEventType;
use YooKassa\Model\Requestor;
use YooKassa\Model\Webhook\Webhook;
use YooKassa\Request\Webhook\WebhookListResponse;

class WebhookPayment
{
    /**
     * YooKassa Client
     *
     * @var Client
     */
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setAuthToken(Cache::get('yookassa_token'));
    }

    /**
     * Create Webhook
     *
     * @param string $url
     * @param string $event
     *
     * @return Webhook|null
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
    public function addWebhook(string $url, string $event = NotificationEventType::PAYMENT_SUCCEEDED)
    {
        return $this->client->addWebhook([
            'event' => $event,
            'url' => $url
        ]);
    }

    /**
     * Get List Webhooks
     *
     * @return WebhookListResponse|null
     * @throws ApiException
     * @throws BadApiRequestException
     * @throws ExtensionNotFoundException
     * @throws ForbiddenException
     * @throws InternalServerError
     * @throws NotFoundException
     * @throws ResponseProcessingException
     * @throws TooManyRequestsException
     * @throws UnauthorizedException
     * @throws AuthorizeException
     */
    public function getWebhooks()
    {
        return $this->client->getWebhooks();
    }

    /**
     * Remove Webhook
     *
     * @param string $webhook_id
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
    public function deleteWebhook(string $webhook_id)
    {
        $response = $this->client->removeWebhook($webhook_id);
    }

    public function read(Request $request)
    {
        $data = $request->all();

        if( isset($data['code']) ) {
            $client_id = env('YOOKASSA_CLIENT_ID', null);;
            $client_secret = env('YOOKASSA_CLIENT_SECRET', null);

            if( !$client_id )
                die('YOOKASSA_CLIENT_ID not exist');

            if( !$client_secret )
                die('YOOKASSA_CLIENT_SECRET not exist');

            $http = new \GuzzleHttp\Client();

            $response = $http->post('https://yookassa.ru/oauth/v2/token', [
                'body' => [
                    'grant_type' => 'authorization_code',
                    'code' => $data['code'],
                    'client_id' => $client_id,
                    'client_secret' => $client_secret
                ]
            ]);

            $response = json_decode($response->getBody()->getContents(), true);
            if( $response['access_token'] ) {
                Cache::set('yookassa_token', $response['access_token']);
            } else {
                die('Generate token again not exist');
            }
        }
    }
}