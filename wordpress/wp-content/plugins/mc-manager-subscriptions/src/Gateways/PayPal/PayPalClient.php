<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cliente HTTP mínimo para PayPal REST API.
 *
 * S3.1 está bloqueada deliberadamente a Sandbox.
 */
final class OptiGrid_Subscriptions_PayPal_Client
{
    private const BASE_URL = 'https://api-m.sandbox.paypal.com';

    private string $client_id;
    private string $client_secret;

    public function __construct(
        string $client_id,
        string $client_secret
    ) {
        $this->client_id = trim($client_id);
        $this->client_secret = trim($client_secret);
    }

    public function configured(): bool
    {
        return $this->client_id !== ''
            && $this->client_secret !== '';
    }

    /**
     * @return array<string,mixed>
     */
    public function create_order(
        array $order,
        array $plan,
        string $return_url,
        string $cancel_url
    ): array {
        $payload = [
            'intent' => 'CAPTURE',
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'brand_name' => 'OptiGrid',
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'PAY_NOW',
                        'return_url' => $return_url,
                        'cancel_url' => $cancel_url,
                    ],
                ],
            ],
            'purchase_units' => [
                [
                    'reference_id' => (string) $order['public_id'],
                    'custom_id' => (string) $order['public_id'],
                    'description' => mb_substr(
                        (string) $plan['name'],
                        0,
                        127
                    ),
                    'amount' => [
                        'currency_code' => strtoupper(
                            (string) $order['currency']
                        ),
                        'value' => number_format(
                            (float) $order['amount'],
                            2,
                            '.',
                            ''
                        ),
                    ],
                ],
            ],
        ];

        return $this->request(
            'POST',
            '/v2/checkout/orders',
            $payload,
            [
                'PayPal-Request-Id' =>
                    'optigrid-create-' . (string) $order['public_id'],
                'Prefer' => 'return=representation',
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function get_order(string $order_id): array
    {
        return $this->request(
            'GET',
            '/v2/checkout/orders/' . rawurlencode($order_id)
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function capture_order(
        string $order_id,
        string $request_id
    ): array {
        return $this->request(
            'POST',
            '/v2/checkout/orders/'
                . rawurlencode($order_id)
                . '/capture',
            new stdClass(),
            [
                'PayPal-Request-Id' => $request_id,
                'Prefer' => 'return=representation',
            ]
        );
    }

    public function verify_webhook_signature(array $headers,array $event,string $webhook_id): bool
    {
        foreach(['transmission_id','transmission_time','cert_url','auth_algo','transmission_sig'] as $key){
            if(trim((string)($headers[$key] ?? ''))===''){return false;}
        }
        $webhook_id=trim($webhook_id);
        if($webhook_id===''){return false;}
        $response=$this->request('POST','/v1/notifications/verify-webhook-signature',[
            'transmission_id'=>$headers['transmission_id'],
            'transmission_time'=>$headers['transmission_time'],
            'cert_url'=>$headers['cert_url'],
            'auth_algo'=>$headers['auth_algo'],
            'transmission_sig'=>$headers['transmission_sig'],
            'webhook_id'=>$webhook_id,
            'webhook_event'=>$event,
        ]);
        return strtoupper((string)($response['verification_status'] ?? ''))==='SUCCESS';
    }

    private function access_token(): string
    {
        if (!$this->configured()) {
            throw new RuntimeException(
                'Las credenciales Sandbox de PayPal no están configuradas.'
            );
        }

        $response = wp_remote_post(
            self::BASE_URL . '/v1/oauth2/token',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' =>
                        'Basic '
                        . base64_encode(
                            $this->client_id
                            . ':'
                            . $this->client_secret
                        ),
                    'Content-Type' =>
                        'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                ],
                'body' => 'grant_type=client_credentials',
            ]
        );

        $data = $this->decode_response($response);

        $token = trim(
            (string) ($data['access_token'] ?? '')
        );

        if ($token === '') {
            throw new RuntimeException(
                'PayPal no devolvió un access token.'
            );
        }

        return $token;
    }

    /**
     * @param array<string,string> $extra_headers
     * @return array<string,mixed>
     */
    private function request(
        string $method,
        string $path,
        mixed $payload = null,
        array $extra_headers = []
    ): array {
        $headers = array_merge(
            [
                'Authorization' =>
                    'Bearer ' . $this->access_token(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            $extra_headers
        );

        $args = [
            'method' => $method,
            'timeout' => 40,
            'headers' => $headers,
        ];

        if ($payload !== null) {
            $args['body'] = wp_json_encode($payload);
        }

        $response = wp_remote_request(
            self::BASE_URL . $path,
            $args
        );

        return $this->decode_response($response);
    }

    /**
     * @return array<string,mixed>
     */
    private function decode_response(mixed $response): array
    {
        if (is_wp_error($response)) {
            throw new RuntimeException(
                'Error HTTP PayPal: '
                . $response->get_error_message()
            );
        }

        $code = (int) wp_remote_retrieve_response_code(
            $response
        );

        $body = (string) wp_remote_retrieve_body(
            $response
        );

        $data = json_decode($body, true);

        if (!is_array($data)) {
            $data = [];
        }

        if ($code < 200 || $code >= 300) {
            $message = (string) (
                $data['message']
                ?? $data['error_description']
                ?? ('HTTP ' . $code)
            );

            $debug_id = (string) (
                $data['debug_id']
                ?? ''
            );

            throw new RuntimeException(
                'PayPal rechazó la operación: '
                . $message
                . (
                    $debug_id !== ''
                        ? ' [debug_id=' . $debug_id . ']'
                        : ''
                )
            );
        }

        return $data;
    }
}
