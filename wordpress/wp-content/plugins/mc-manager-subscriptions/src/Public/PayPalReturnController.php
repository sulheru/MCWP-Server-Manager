<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Recibe el navegador después de aprobar/cancelar en PayPal.
 *
 * La confirmación definitiva se obtiene server-to-server mediante
 * GET/CAPTURE de PayPal; no se confía en parámetros del navegador.
 */
final class OptiGrid_Subscriptions_PayPal_Return_Controller
{
    private OptiGrid_Subscriptions_Payment_Order_Repository $orders;
    private OptiGrid_Subscriptions_Checkout_Service $checkout;
    private OptiGrid_Subscriptions_PayPal_Gateway $paypal;

    public function __construct(
        OptiGrid_Subscriptions_Payment_Order_Repository $orders,
        OptiGrid_Subscriptions_Checkout_Service $checkout,
        OptiGrid_Subscriptions_PayPal_Gateway $paypal
    ) {
        $this->orders = $orders;
        $this->checkout = $checkout;
        $this->paypal = $paypal;
    }

    public function register(): void
    {
        add_action(
            'admin_post_optigrid_paypal_return',
            [$this, 'handle_return']
        );

        add_action(
            'admin_post_nopriv_optigrid_paypal_return',
            [$this, 'handle_return']
        );

        add_action(
            'admin_post_optigrid_paypal_cancel',
            [$this, 'handle_cancel']
        );

        add_action(
            'admin_post_nopriv_optigrid_paypal_cancel',
            [$this, 'handle_cancel']
        );
    }

    public function handle_return(): void
    {
        try {
            [$order, $paypal_order_id] =
                $this->context();

            if ((string) $order['status'] !== 'pending') {
                $this->render(
                    'Operación ya procesada',
                    'La orden ya se encuentra en estado '
                        . (string) $order['status']
                        . '.',
                    (string) $order['status'] === 'paid'
                );
            }

            $raw = $this->paypal->capture(
                $order,
                $paypal_order_id
            );

            $result =
                $this->checkout
                    ->process_external_result(
                        (string) $order['public_id'],
                        $raw
                    );

            $paid =
                (string) $result['order_status']
                === 'paid';

            $this->render(
                $paid
                    ? 'Pago aprobado'
                    : 'Resultado recibido',
                (string) $result['message'],
                $paid
            );
        } catch (Throwable $e) {
            $this->render(
                'No se pudo completar el pago',
                $e->getMessage(),
                false
            );
        }
    }

    public function handle_cancel(): void
    {
        try {
            [$order, $paypal_order_id] =
                $this->context(false);

            if ((string) $order['status'] === 'pending') {
                $raw =
                    $this->paypal
                        ->cancelled_result(
                            $order,
                            $paypal_order_id
                        );

                $this->checkout
                    ->process_external_result(
                        (string) $order['public_id'],
                        $raw
                    );
            }

            $this->render(
                'Pago cancelado',
                'La operación se canceló en PayPal Sandbox.',
                false
            );
        } catch (Throwable $e) {
            $this->render(
                'No se pudo cancelar correctamente',
                $e->getMessage(),
                false
            );
        }
    }

    /**
     * @return array{0:array<string,mixed>,1:string}
     */
    private function context(
        bool $require_paypal_token = true
    ): array {
        $public_id =
            isset($_GET['public_id'])
                ? sanitize_text_field(
                    wp_unslash($_GET['public_id'])
                )
                : '';

        $state =
            isset($_GET['state'])
                ? sanitize_text_field(
                    wp_unslash($_GET['state'])
                )
                : '';

        $paypal_order_id =
            isset($_GET['token'])
                ? sanitize_text_field(
                    wp_unslash($_GET['token'])
                )
                : '';

        $expected = hash_hmac(
            'sha256',
            $public_id,
            wp_salt('auth')
        );

        if (
            $public_id === ''
            || $state === ''
            || !hash_equals($expected, $state)
        ) {
            throw new RuntimeException(
                'La respuesta de PayPal no contiene un estado válido.'
            );
        }

        if (
            $require_paypal_token
            && $paypal_order_id === ''
        ) {
            throw new RuntimeException(
                'PayPal no devolvió el identificador de la orden.'
            );
        }

        $order =
            $this->orders
                ->find_by_public_id($public_id);

        if (
            $order === null
            || (string) $order['gateway'] !== 'paypal'
        ) {
            throw new RuntimeException(
                'La orden PayPal no existe.'
            );
        }

        return [$order, $paypal_order_id];
    }

    private function render(
        string $title,
        string $message,
        bool $success
    ): never {
        status_header(200);
        nocache_headers();

        echo '<!doctype html>';
        echo '<html lang="es"><head>';
        echo '<meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . esc_html($title) . '</title>';
        echo '<style>';
        echo 'body{font-family:system-ui,sans-serif;background:#f6f7f7;margin:0;padding:40px}';
        echo 'main{max-width:620px;margin:8vh auto;background:#fff;padding:32px;border-radius:12px;box-shadow:0 3px 18px rgba(0,0,0,.08)}';
        echo '.ok{border-left:5px solid #00a32a}.notice{border-left:5px solid #dba617}';
        echo 'h1{margin-top:0}button{padding:10px 18px}';
        echo '</style>';
        echo '</head><body>';
        echo '<main class="' . ($success ? 'ok' : 'notice') . '">';
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '<p>Puedes cerrar esta pestaña. OptiGrid actualizará el estado automáticamente.</p>';
        echo '<button type="button" onclick="window.close()">Cerrar</button>';
        echo '</main></body></html>';
        exit;
    }
}
