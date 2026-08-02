<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class OptiGrid_Subscriptions_Public_Checkout_Controller
{
    private const ACTION = 'optigrid_subscriptions_public_checkout';
    private const NONCE_ACTION = 'optigrid_subscriptions_public_checkout';
    private const NONCE_NAME = 'optigrid_subscriptions_public_nonce';

    private OptiGrid_Subscriptions_Gateway_Registry $gateways;
    private OptiGrid_Subscriptions_Plan_Repository $plans;
    private OptiGrid_Subscriptions_Payment_Order_Repository $orders;
    private OptiGrid_Subscriptions_Subscription_Repository $subscriptions;
    private OptiGrid_Subscriptions_Entitlement_Repository $entitlements;
    private OptiGrid_Subscriptions_Checkout_Service $checkout;

    public function __construct(
        OptiGrid_Subscriptions_Gateway_Registry $gateways,
        OptiGrid_Subscriptions_Plan_Repository $plans,
        OptiGrid_Subscriptions_Payment_Order_Repository $orders,
        OptiGrid_Subscriptions_Subscription_Repository $subscriptions,
        OptiGrid_Subscriptions_Entitlement_Repository $entitlements,
        OptiGrid_Subscriptions_Checkout_Service $checkout
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
        add_shortcode('optigrid_subscription_checkout', [$this, 'render_checkout']);
        add_shortcode('optigrid_my_subscription', [$this, 'render_my_subscription']);
        add_action('admin_post_' . self::ACTION, [$this, 'handle_checkout']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets(): void
    {
        wp_register_style(
            'optigrid-subscriptions-public',
            OPTIGRID_SUBSCRIPTIONS_URL . 'assets/css/public.css',
            [],
            OPTIGRID_SUBSCRIPTIONS_VERSION
        );
    }

    public function render_checkout(): string
    {
        wp_enqueue_style('optigrid-subscriptions-public');

        if (!is_user_logged_in()) {
            return $this->login_required();
        }

        try {
            $this->plans->ensure_sandbox_plan();
        } catch (Throwable $exception) {
            return $this->public_error($exception->getMessage());
        }

        $user_id = get_current_user_id();
        $order = $this->requested_order($user_id);
        $plans = $this->plans->active();
        $sandbox_enabled = $this->gateways->is_enabled('sandbox');
        $checkout_error = isset($_GET['optigrid_checkout_error'])
            ? sanitize_text_field(rawurldecode(wp_unslash($_GET['optigrid_checkout_error'])))
            : '';

        $template = OPTIGRID_SUBSCRIPTIONS_DIR . 'templates/public/checkout.php';
        if (!is_readable($template)) {
            return $this->public_error(__('No se encuentra la plantilla pública de checkout.', 'optigrid-subscriptions'));
        }

        ob_start();
        require $template;
        return (string) ob_get_clean();
    }

    public function render_my_subscription(): string
    {
        wp_enqueue_style('optigrid-subscriptions-public');

        if (!is_user_logged_in()) {
            return $this->login_required();
        }

        $user_id = get_current_user_id();
        $active_subscriptions = $this->subscriptions->active_for_user($user_id);
        $active_entitlements = $this->entitlements->active_for_user($user_id, 'minecraft_access');
        $recent_orders = $this->orders->recent_for_user($user_id, 10);

        $template = OPTIGRID_SUBSCRIPTIONS_DIR . 'templates/public/my-subscription.php';
        if (!is_readable($template)) {
            return $this->public_error(__('No se encuentra la plantilla del área de suscripción.', 'optigrid-subscriptions'));
        }

        ob_start();
        require $template;
        return (string) ob_get_clean();
    }

    public function handle_checkout(): void
    {
        if (!is_user_logged_in()) {
            auth_redirect();
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

        $user_id = get_current_user_id();
        $plan_id = isset($_POST['plan_id']) ? absint(wp_unslash($_POST['plan_id'])) : 0;
        $idempotency_key = isset($_POST['idempotency_key'])
            ? sanitize_text_field(wp_unslash($_POST['idempotency_key']))
            : '';
        $return_url = isset($_POST['return_url'])
            ? esc_url_raw(wp_unslash($_POST['return_url']))
            : home_url('/');

        $return_url = wp_validate_redirect($return_url, home_url('/'));
        $return_host = wp_parse_url($return_url, PHP_URL_HOST);
        $home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        if (!is_string($return_host) || !is_string($home_host) || strtolower($return_host) !== strtolower($home_host)) {
            $return_url = home_url('/');
        }

        try {
            $gateway = $this->gateways->get_enabled('sandbox');
            $status = $gateway->get_status();
            $scenario = sanitize_key((string) ($status['default_scenario'] ?? 'approved'));

            $result = $this->checkout->checkout(
                $user_id,
                $plan_id,
                'sandbox',
                $scenario,
                $idempotency_key
            );

            $public_id = sanitize_text_field((string) ($result['public_id'] ?? ''));
            if ($public_id === '') {
                throw new RuntimeException(__('La operación terminó sin identificador público.', 'optigrid-subscriptions'));
            }

            $redirect = add_query_arg(
                ['optigrid_order' => $public_id],
                remove_query_arg(['optigrid_order', 'optigrid_checkout_error'], $return_url)
            );
        } catch (Throwable $exception) {
            $redirect = add_query_arg(
                ['optigrid_checkout_error' => rawurlencode($exception->getMessage())],
                remove_query_arg(['optigrid_order', 'optigrid_checkout_error'], $return_url)
            );
        }

        wp_safe_redirect($redirect);
        exit;
    }

    private function requested_order(int $user_id): ?array
    {
        $public_id = isset($_GET['optigrid_order'])
            ? sanitize_text_field(wp_unslash($_GET['optigrid_order']))
            : '';

        return $public_id === ''
            ? null
            : $this->orders->find_by_public_id_for_user($public_id, $user_id);
    }

    private function login_required(): string
    {
        return sprintf(
            '<div class="optigrid-public-notice optigrid-public-notice--info"><p>%s</p><p><a class="button" href="%s">%s</a></p></div>',
            esc_html__('Debes iniciar sesión para consultar o contratar una suscripción.', 'optigrid-subscriptions'),
            esc_url(wp_login_url(home_url(wp_unslash($_SERVER['REQUEST_URI'] ?? '/')))),
            esc_html__('Iniciar sesión', 'optigrid-subscriptions')
        );
    }

    private function public_error(string $message): string
    {
        return sprintf(
            '<div class="optigrid-public-notice optigrid-public-notice--error"><p>%s</p></div>',
            esc_html($message)
        );
    }

    public static function action(): string { return self::ACTION; }
    public static function nonce_action(): string { return self::NONCE_ACTION; }
    public static function nonce_name(): string { return self::NONCE_NAME; }
}
