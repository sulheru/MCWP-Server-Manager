<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Public_Checkout_Controller
{
    private const AJAX_CREATE='optigrid_create_checkout_order';
    private const AJAX_STATUS='optigrid_checkout_order_status';

    private OptiGrid_Subscriptions_Gateway_Registry $gateways;
    private OptiGrid_Subscriptions_Plan_Repository $plans;
    private OptiGrid_Subscriptions_Payment_Order_Repository $orders;
    private OptiGrid_Subscriptions_Subscription_Repository $subscriptions;
    private OptiGrid_Subscriptions_Entitlement_Repository $entitlements;
    private OptiGrid_Subscriptions_Checkout_Service $checkout;

    public function __construct(
        $gateways,
        $plans,
        $orders,
        $subscriptions,
        $entitlements,
        $checkout
    ) {
        $this->gateways = $gateways;
        $this->plans = $plans;
        $this->orders = $orders;
        $this->subscriptions = $subscriptions;
        $this->entitlements = $entitlements;
        $this->checkout = $checkout;
    }

    public function register(): void
    {
        add_shortcode(
            'optigrid_subscription_checkout',
            [$this,'render_checkout']
        );
        add_shortcode(
            'optigrid_my_subscription',
            [$this,'render_my_subscription']
        );
        add_action(
            'wp_enqueue_scripts',
            [$this,'register_assets']
        );
        add_action(
            'wp_ajax_'.self::AJAX_CREATE,
            [$this,'ajax_create_order']
        );
        add_action(
            'wp_ajax_'.self::AJAX_STATUS,
            [$this,'ajax_order_status']
        );
    }

    public function register_assets(): void
    {
        wp_register_style(
            'optigrid-subscriptions-public',
            OPTIGRID_SUBSCRIPTIONS_URL.'assets/css/public.css',
            [],
            OPTIGRID_SUBSCRIPTIONS_VERSION
        );

        wp_register_script(
            'optigrid-subscriptions-checkout',
            OPTIGRID_SUBSCRIPTIONS_URL.'assets/js/public-checkout.js',
            [],
            OPTIGRID_SUBSCRIPTIONS_VERSION,
            true
        );
    }

    public function render_checkout(): string
    {
        wp_enqueue_style(
            'optigrid-subscriptions-public'
        );
        wp_enqueue_script(
            'optigrid-subscriptions-checkout'
        );

        if (!is_user_logged_in()) {
            return $this->login_required();
        }

        try {
            $this->plans->ensure_sandbox_plan();
        } catch (Throwable $e) {
            return $this->public_error(
                $e->getMessage()
            );
        }

        $plans = $this->plans->public_active();
        $gateways = $this->gateways->enabled();
        $template =
            OPTIGRID_SUBSCRIPTIONS_DIR
            .'templates/public/checkout.php';

        ob_start();
        require $template;
        return (string) ob_get_clean();
    }

    public function render_my_subscription(): string
    {
        wp_enqueue_style(
            'optigrid-subscriptions-public'
        );

        if (!is_user_logged_in()) {
            return $this->login_required();
        }

        $user_id = get_current_user_id();

        $active_subscriptions =
            $this->subscriptions->active_for_user(
                $user_id
            );

        $active_entitlements =
            $this->entitlements->active_for_user(
                $user_id,
                'minecraft_access'
            );

        $recent_orders =
            $this->orders->recent_for_user(
                $user_id,
                10
            );

        ob_start();
        require
            OPTIGRID_SUBSCRIPTIONS_DIR
            .'templates/public/my-subscription.php';
        return (string) ob_get_clean();
    }

    public function ajax_create_order(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(
                ['message'=>'Debes iniciar sesión.'],
                401
            );
        }

        check_ajax_referer(
            'optigrid_public_checkout_ajax',
            'nonce'
        );

        try {
            $plan_id =
                isset($_POST['plan_id'])
                    ? absint(
                        wp_unslash($_POST['plan_id'])
                    )
                    : 0;

            $gateway_id =
                isset($_POST['gateway'])
                    ? sanitize_key(
                        wp_unslash($_POST['gateway'])
                    )
                    : '';

            $key =
                isset($_POST['idempotency_key'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['idempotency_key']
                        )
                    )
                    : '';

            $order = $this->checkout->create_order(
                get_current_user_id(),
                $plan_id,
                $gateway_id,
                $key
            );

            $gateway_checkout =
                $this->checkout
                    ->create_gateway_checkout($order);

            $url = (string) (
                $gateway_checkout['redirect_url']
                ?? ''
            );

            if ($url === '') {
                throw new RuntimeException(
                    'La pasarela no devolvió una URL de checkout.'
                );
            }

            wp_send_json_success([
                'publicId' => $order['public_id'],
                'status' => $order['status'],
                'gatewayUrl' => $url,
            ]);
        } catch (Throwable $e) {
            wp_send_json_error(
                ['message'=>$e->getMessage()],
                400
            );
        }
    }

    public function ajax_order_status(): void
    {
        if (!is_user_logged_in()) {
            wp_send_json_error(
                ['message'=>'Sesión no válida.'],
                401
            );
        }

        check_ajax_referer(
            'optigrid_public_checkout_ajax',
            'nonce'
        );

        $public_id =
            isset($_POST['public_id'])
                ? sanitize_text_field(
                    wp_unslash($_POST['public_id'])
                )
                : '';

        $order =
            $this->orders
                ->find_by_public_id_for_user(
                    $public_id,
                    get_current_user_id()
                );

        if ($order === null) {
            wp_send_json_error(
                ['message'=>'Orden no encontrada.'],
                404
            );
        }

        wp_send_json_success([
            'publicId'=>$order['public_id'],
            'status'=>$order['status'],
            'amount'=>$order['amount'],
            'currency'=>$order['currency'],
        ]);
    }

    private function login_required(): string
    {
        return sprintf(
            '<div class="optigrid-public-notice optigrid-public-notice--info"><p>%s</p><p><a class="button" href="%s">%s</a></p></div>',
            esc_html__(
                'Debes iniciar sesión para contratar una suscripción.',
                'optigrid-subscriptions'
            ),
            esc_url(
                wp_login_url(
                    home_url(
                        wp_unslash(
                            $_SERVER['REQUEST_URI']
                            ?? '/'
                        )
                    )
                )
            ),
            esc_html__(
                'Iniciar sesión',
                'optigrid-subscriptions'
            )
        );
    }

    private function public_error(
        string $message
    ): string {
        return
            '<div class="optigrid-public-notice optigrid-public-notice--error"><p>'
            .esc_html($message)
            .'</p></div>';
    }
}
