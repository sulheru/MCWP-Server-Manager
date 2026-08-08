<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PayPal como proveedor de pago.
 *
 * OptiGrid conserva la propiedad de planes, suscripciones y entitlements.
 */
final class OptiGrid_Subscriptions_PayPal_Gateway implements
    OptiGrid_Subscriptions_Payment_Gateway_Interface
{
    public function get_id(): string
    {
        return 'paypal';
    }

    public function get_name(): string
    {
        return 'PayPal';
    }

    public function get_description(): string
    {
        return __(
            'Pago mediante PayPal. El entorno activo se configura desde administración.',
            'optigrid-subscriptions'
        );
    }

    public function is_available(): bool
    {
        return $this->client()->configured();
    }

    public function is_test_gateway(): bool
    {
        return OptiGrid_Subscriptions_Gateway_Settings::paypal_environment() === 'sandbox';
    }

    /**
     * Nunca expone el Client Secret.
     *
     * @return array<string,mixed>
     */
    public function get_status(): array
    {
        $settings =
            OptiGrid_Subscriptions_Gateway_Settings::for_gateway(
                $this->get_id()
            );

        $environment = OptiGrid_Subscriptions_Gateway_Settings::paypal_environment();
        return [
            'id' => $this->get_id(),
            'name' => $this->get_name(),
            'enabled' => !empty($settings['enabled']),
            'available' => $this->is_available(),
            'test_gateway' => $environment === 'sandbox',
            'environment' => $environment,
            'credentials_configured' => $this->is_available(),
            'sandbox_configured' => OptiGrid_Subscriptions_Gateway_Settings::paypal_environment_complete('sandbox'),
            'live_configured' => OptiGrid_Subscriptions_Gateway_Settings::paypal_environment_complete('live'),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function create_checkout(array $context): array
    {
        $order = $context['order'] ?? null;
        $plan = $context['plan'] ?? null;

        if (!is_array($order) || !is_array($plan)) {
            throw new InvalidArgumentException(
                'PayPal requiere una orden y un plan válidos.'
            );
        }

        $public_id = (string) $order['public_id'];

        $state = hash_hmac(
            'sha256',
            $public_id,
            wp_salt('auth')
        );

        $return_url = add_query_arg(
            [
                'action' => 'optigrid_paypal_return',
                'public_id' => $public_id,
                'state' => $state,
            ],
            admin_url('admin-post.php')
        );

        $cancel_url = add_query_arg(
            [
                'action' => 'optigrid_paypal_cancel',
                'public_id' => $public_id,
                'state' => $state,
            ],
            admin_url('admin-post.php')
        );

        $response = $this->client()->create_order(
            $order,
            $plan,
            $return_url,
            $cancel_url
        );

        $paypal_order_id = sanitize_text_field(
            (string) ($response['id'] ?? '')
        );

        if ($paypal_order_id === '') {
            throw new RuntimeException(
                'PayPal no devolvió un identificador de orden.'
            );
        }

        $redirect_url = $this->approval_url($response);

        if ($redirect_url === '') {
            throw new RuntimeException(
                'PayPal no devolvió una URL de aprobación.'
            );
        }

        return [
            'gateway' => $this->get_id(),
            'status' => 'pending',
            'external_operation_id' => $paypal_order_id,
            'redirect_url' => $redirect_url,
        ];
    }

    /**
     * PayPal no usa escenarios internos.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function create_payment(array $context): array
    {
        throw new LogicException(
            'PayPal procesa el pago mediante retorno/capture.'
        );
    }

    /**
     * Valida la orden remota y captura el pago.
     *
     * @return array<string,mixed>
     */
    public function capture(
        array $order,
        string $paypal_order_id
    ): array {
        $remote = $this->client()->get_order(
            $paypal_order_id
        );

        $this->validate_remote_order(
            $order,
            $remote
        );

        $response = $this->client()->capture_order(
            $paypal_order_id,
            'optigrid-capture-' . (string) $order['public_id']
        );

        $capture = $this->first_capture($response);

        $capture_id = sanitize_text_field(
            (string) (
                $capture['id']
                ?? $paypal_order_id
            )
        );

        $status = strtoupper(
            (string) (
                $capture['status']
                ?? $response['status']
                ?? ''
            )
        );

        $normalized = match ($status) {
            'COMPLETED' => 'approved',
            'PENDING' => 'pending',
            'DECLINED', 'FAILED' => 'rejected',
            default => 'error',
        };

        $amount = is_array($capture['amount'] ?? null)
            ? $capture['amount']
            : [];

        return [
            'gateway' => $this->get_id(),
            'external_operation_id' => $capture_id,
            'scenario' => 'paypal_capture',
            'status' => $normalized,
            'amount' => (string) (
                $amount['value']
                ?? $order['amount']
            ),
            'currency' => strtoupper(
                (string) (
                    $amount['currency_code']
                    ?? $order['currency']
                )
            ),
            'message' =>
                'PayPal capture: '
                . ($status !== '' ? $status : 'UNKNOWN'),
            'processed_at' => current_time('mysql', true),
            'raw' => $response,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function cancelled_result(
        array $order,
        string $paypal_order_id
    ): array {
        return [
            'gateway' => $this->get_id(),
            'external_operation_id' =>
                $paypal_order_id !== ''
                    ? $paypal_order_id
                    : 'paypal-cancel-' . wp_generate_uuid4(),
            'scenario' => 'paypal_cancel',
            'status' => 'cancelled',
            'amount' => (string) $order['amount'],
            'currency' => strtoupper(
                (string) $order['currency']
            ),
            'message' =>
                'El comprador canceló el checkout de PayPal.',
            'processed_at' => current_time('mysql', true),
            'raw' => [
                'paypal_order_id' => $paypal_order_id,
                'cancelled' => true,
            ],
        ];
    }

    public function public_id_from_webhook(array $event): string
    {
        $resource=is_array($event['resource'] ?? null)?$event['resource']:[];
        $direct=$this->public_id_from_resource($resource);
        if($direct!==''){return $direct;}
        $related=$resource['supplementary_data']['related_ids'] ?? [];
        $paypal_order_id=is_array($related)?sanitize_text_field((string)($related['order_id'] ?? '')):'';
        if($paypal_order_id===''){return '';}
        return $this->public_id_from_resource($this->client()->get_order($paypal_order_id));
    }

    public function result_from_capture_webhook(array $order,array $event): ?array
    {
        $type=strtoupper((string)($event['event_type'] ?? ''));
        $status=match($type){
            'PAYMENT.CAPTURE.COMPLETED'=>'approved',
            'PAYMENT.CAPTURE.DENIED'=>'rejected',
            default=>null,
        };
        if($status===null){return null;}
        $resource=is_array($event['resource'] ?? null)?$event['resource']:[];
        $external_id=sanitize_text_field((string)($resource['id'] ?? ''));
        if($external_id===''){throw new RuntimeException('Webhook PayPal sin capture id.');}
        $amount=is_array($resource['amount'] ?? null)?$resource['amount']:[];
        $value=number_format((float)($amount['value'] ?? -1),2,'.','');
        $currency=strtoupper((string)($amount['currency_code'] ?? ''));
        if($value!==number_format((float)$order['amount'],2,'.','') || $currency!==strtoupper((string)$order['currency'])){
            throw new RuntimeException('El importe del webhook PayPal no coincide con la orden local.');
        }
        return [
            'gateway'=>'paypal','external_operation_id'=>$external_id,'scenario'=>'paypal_webhook','status'=>$status,
            'amount'=>$value,'currency'=>$currency,'message'=>'PayPal webhook: '.$type,
            'processed_at'=>current_time('mysql',true),'raw'=>$event,
        ];
    }

    public function reconcile(array $order): ?array
    {
        $paypal_order_id=sanitize_text_field((string)($order['gateway_reference'] ?? ''));
        if($paypal_order_id===''){return null;}
        $remote=$this->client()->get_order($paypal_order_id);
        $this->validate_remote_order($order,$remote);
        $status=strtoupper((string)($remote['status'] ?? ''));
        if($status==='APPROVED'){return $this->capture($order,$paypal_order_id);}
        if($status!=='COMPLETED'){return null;}
        $capture=$this->first_capture($remote);
        if($capture===[]){return null;}
        return $this->result_from_capture_resource($order,$capture,['source'=>'paypal_reconcile','order'=>$remote],'paypal_reconcile');
    }

    private function public_id_from_resource(array $resource): string
    {
        $direct=sanitize_text_field((string)($resource['custom_id'] ?? ''));
        if($direct!==''){return $direct;}
        $units=$resource['purchase_units'] ?? [];
        if(!is_array($units)){return '';}
        foreach($units as $unit){
            if(!is_array($unit)){continue;}
            $value=sanitize_text_field((string)($unit['custom_id'] ?? $unit['reference_id'] ?? ''));
            if($value!==''){return $value;}
        }
        return '';
    }

    private function result_from_capture_resource(array $order,array $capture,array $raw,string $scenario): array
    {
        $external_id=sanitize_text_field((string)($capture['id'] ?? ''));
        if($external_id===''){throw new RuntimeException('PayPal no devolvió capture id.');}
        $status=strtoupper((string)($capture['status'] ?? ''));
        $normalized=match($status){'COMPLETED'=>'approved','PENDING'=>'pending','DECLINED','FAILED','DENIED'=>'rejected',default=>'error'};
        $amount=is_array($capture['amount'] ?? null)?$capture['amount']:[];
        return [
            'gateway'=>'paypal','external_operation_id'=>$external_id,'scenario'=>$scenario,'status'=>$normalized,
            'amount'=>(string)($amount['value'] ?? $order['amount']),
            'currency'=>strtoupper((string)($amount['currency_code'] ?? $order['currency'])),
            'message'=>'PayPal '.$scenario.': '.$status,'processed_at'=>current_time('mysql',true),'raw'=>$raw,
        ];
    }

    private function client(): OptiGrid_Subscriptions_PayPal_Client
    {
        $environment = OptiGrid_Subscriptions_Gateway_Settings::paypal_environment();
        $settings = OptiGrid_Subscriptions_Gateway_Settings::paypal_environment_settings($environment);
        return new OptiGrid_Subscriptions_PayPal_Client(
            $settings['client_id'],
            $settings['client_secret'],
            $environment
        );
    }

    /**
     * @param array<string,mixed> $response
     */
    private function approval_url(array $response): string
    {
        $links = $response['links'] ?? [];

        if (!is_array($links)) {
            return '';
        }

        foreach (['payer-action', 'approve'] as $wanted_rel) {
            foreach ($links as $link) {
                if (
                    is_array($link)
                    && (string) ($link['rel'] ?? '') === $wanted_rel
                ) {
                    return esc_url_raw(
                        (string) ($link['href'] ?? '')
                    );
                }
            }
        }

        return '';
    }

    /**
     * Evita aplicar a una orden local el pago de otra orden PayPal.
     *
     * @param array<string,mixed> $order
     * @param array<string,mixed> $remote
     */
    private function validate_remote_order(
        array $order,
        array $remote
    ): void {
        $units = $remote['purchase_units'] ?? [];

        if (!is_array($units) || !isset($units[0]) || !is_array($units[0])) {
            throw new RuntimeException(
                'PayPal devolvió una orden sin purchase_units.'
            );
        }

        $unit = $units[0];

        $reference = (string) (
            $unit['reference_id']
            ?? $unit['custom_id']
            ?? ''
        );

        if (
            $reference === ''
            || !hash_equals(
                (string) $order['public_id'],
                $reference
            )
        ) {
            throw new RuntimeException(
                'La orden PayPal no corresponde a la orden local.'
            );
        }

        $amount = is_array($unit['amount'] ?? null)
            ? $unit['amount']
            : [];

        $remote_value = number_format(
            (float) ($amount['value'] ?? -1),
            2,
            '.',
            ''
        );

        $local_value = number_format(
            (float) $order['amount'],
            2,
            '.',
            ''
        );

        $remote_currency = strtoupper(
            (string) ($amount['currency_code'] ?? '')
        );

        $local_currency = strtoupper(
            (string) $order['currency']
        );

        if (
            $remote_value !== $local_value
            || $remote_currency !== $local_currency
        ) {
            throw new RuntimeException(
                'El importe de PayPal no coincide con la orden local.'
            );
        }
    }

    /**
     * @param array<string,mixed> $response
     * @return array<string,mixed>
     */
    private function first_capture(array $response): array
    {
        $units = $response['purchase_units'] ?? [];

        if (!is_array($units)) {
            return [];
        }

        foreach ($units as $unit) {
            if (!is_array($unit)) {
                continue;
            }

            $captures =
                $unit['payments']['captures']
                ?? [];

            if (
                is_array($captures)
                && isset($captures[0])
                && is_array($captures[0])
            ) {
                return $captures[0];
            }
        }

        return [];
    }
}
