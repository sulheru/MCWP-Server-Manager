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
            'Pago mediante PayPal Sandbox. No mueve dinero real durante esta fase.',
            'optigrid-subscriptions'
        );
    }

    public function is_available(): bool
    {
        return $this->client()->configured();
    }

    public function is_test_gateway(): bool
    {
        return true;
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

        return [
            'id' => $this->get_id(),
            'name' => $this->get_name(),
            'enabled' => !empty($settings['enabled']),
            'available' => $this->is_available(),
            'test_gateway' => true,
            'environment' => 'sandbox',
            'credentials_configured' => $this->is_available(),
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
                'PayPal Sandbox capture: '
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
                'El comprador canceló el checkout de PayPal Sandbox.',
            'processed_at' => current_time('mysql', true),
            'raw' => [
                'paypal_order_id' => $paypal_order_id,
                'cancelled' => true,
            ],
        ];
    }

    private function client(): OptiGrid_Subscriptions_PayPal_Client
    {
        $settings =
            OptiGrid_Subscriptions_Gateway_Settings::for_gateway(
                $this->get_id()
            );

        return new OptiGrid_Subscriptions_PayPal_Client(
            (string) ($settings['client_id'] ?? ''),
            (string) ($settings['client_secret'] ?? '')
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
