<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Sandbox_Checkout_Controller
{
    private const ACTION='optigrid_subscriptions_run_sandbox';
    private const NONCE_ACTION='optigrid_subscriptions_sandbox_checkout';
    private const NONCE_NAME='optigrid_subscriptions_sandbox_nonce';
    private OptiGrid_Subscriptions_Checkout_Service $checkout;

    public function __construct(OptiGrid_Subscriptions_Checkout_Service $checkout) { $this->checkout=$checkout; }
    public function register(): void { add_action('admin_post_' . self::ACTION, [$this,'handle']); }

    public function handle(): void
    {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('No tienes permisos suficientes.','optigrid-subscriptions')); }
        check_admin_referer(self::NONCE_ACTION,self::NONCE_NAME);
        try {
            $result=$this->checkout->checkout(
                isset($_POST['user_id'])?(int)$_POST['user_id']:0,
                isset($_POST['plan_id'])?(int)$_POST['plan_id']:0,
                'sandbox',
                isset($_POST['scenario'])?sanitize_key(wp_unslash($_POST['scenario'])):'approved',
                isset($_POST['idempotency_key'])?sanitize_text_field(wp_unslash($_POST['idempotency_key'])):''
            );
            set_transient('optigrid_sandbox_result_' . get_current_user_id(), ['ok'=>true,'data'=>$result], 5 * MINUTE_IN_SECONDS);
        } catch (Throwable $e) {
            set_transient('optigrid_sandbox_result_' . get_current_user_id(), ['ok'=>false,'error'=>$e->getMessage()], 5 * MINUTE_IN_SECONDS);
        }
        wp_safe_redirect(add_query_arg(['page'=>'optigrid-subscriptions','sandbox_result'=>'1'], admin_url('admin.php')));
        exit;
    }

    public static function form_action(): string { return self::ACTION; }
    public static function nonce_action(): string { return self::NONCE_ACTION; }
    public static function nonce_name(): string { return self::NONCE_NAME; }
}
