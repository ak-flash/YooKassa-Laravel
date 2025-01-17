<?php

namespace Fiks\YooKassa\Payment;

use Exception;
use Fiks\YooKassa\Callback\Callback;
use Fiks\YooKassa\Traits\Contract;
use Fiks\YooKassa\YooKassaApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use SuperClosure\Serializer;
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
use YooKassa\Model\Webhook\Webhook;
use YooKassa\Request\Payments\CreatePaymentResponse;
use YooKassa\Request\Webhook\WebhookListResponse;

class WebhookPayment
{
    use Contract;

    /**
     * YooKassa Client
     *
     * @var Client
     */
    private Client $client;

    /**
     * API
     *
     * @var ?YooKassaApi
     */
    private ?YooKassaApi $api;

    public function __construct(?YooKassaApi $api = null)
    {
        $this->client = new Client();
        $this->client->setAuthToken('Bearer ' . env('YOOKASSA_CLIENT_ID', null) . ':' . Cache::get('yookassa_token'));
        $this->api = $api;
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
            'url'   => $url
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
     * @return Webhook|null
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
        return $this->client->removeWebhook($webhook_id);
    }

    public function read(Request $request)
    {
        # Read Webhook
        $data = $request->all();

        # Notification
        if(isset($data['type']) && $data['type'] == 'notification') {
            # Create Response
            $response = new CreatePaymentResponse();
            # Create Object to Array and Class
            $response->fromArray($data['object']);
            $serializer = new Serializer();

            # Check Payment
            $this->api->checkPayment($response->getMetadata()['uniq_id'], function($payment, $invoice) use($serializer){
                # Log Success Payment
                // \Storage::disk('public')->append('payment/success.txt', json_encode($payment));

                # Execute custom function
                $func = $this->callback()->exec();

                # If exist cached function
                if( $func ) {
                    try {
                        $func = $serializer->unserialize($func);
                        $func($payment, $invoice);
                    } catch(Exception $exception) {
                        print_r($exception->getMessage());
                        die();
                    }
                }
            }, function($payment, $invoice) use($serializer) {
                # Log Error Payment
                // \Storage::disk('public')->append('payment/error.txt', json_encode($payment));

                # Execute custom function
                $func = $this->callback()->exec('failed');

                # If exist cached function
                if( $func ) {
                    try {
                        $func = $serializer->unserialize($func);
                        $func($payment, $invoice);
                    } catch(Exception $exception) {
                        print_r($exception->getMessage());
                        die();
                    }
                }
            });
        }

        if(isset($data['code'])) {
            $client_id = env('YOOKASSA_CLIENT_ID', null);;
            $client_secret = env('YOOKASSA_CLIENT_SECRET', null);

            if(!$client_id)
                die('YOOKASSA_CLIENT_ID not exist');

            if(!$client_secret)
                die('YOOKASSA_CLIENT_SECRET not exist');

            $http = new \GuzzleHttp\Client();

            $response = $http->post('https://yookassa.ru/oauth/v2/token', [
                'form_params' => [
                    'grant_type'    => 'authorization_code',
                    'code'          => $data['code'],
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret
                ]
            ]);

            $response = json_decode($response->getBody()->getContents(), true);
            if($response['access_token']) {
                Cache::set('yookassa_token', $response['access_token']);
                echo '<script>window.close()</script>';
            } else {
                die('Generate token again. Token not exist');
            }
        }
    }

    /**
     * Add Callback functions
     *
     * @return Callback
     */
    public function callback()
    {
        return new Callback();
    }
}
