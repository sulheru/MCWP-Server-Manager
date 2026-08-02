<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Procesa la configuración de extensiones de pago.
 */
final class OptiGrid_Subscriptions_Gateway_Settings_Controller
{
    private const ACTION = 'optigrid_subscriptions_save_gateways';
    private const NONCE_ACTION = 'optigrid_subscriptions_gateways';
    private const NONCE_NAME = 'optigrid_subscriptions_gateways_nonce';

    private OptiGrid_Subscriptions_Gateway_Registry $registry;

    public function __construct(
        OptiGrid_Subscriptions_Gateway_Registry $registry
    ) {
        $this->registry = $registry;
    }

    public function register(): void
    {
        add_action(
            'admin_post_' . self::ACTION,
            [$this, 'handle_save']
        );
    }

    public function handle_save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__(
                    'No tienes permisos suficientes para modificar las pasarelas.',
                    'optigrid-subscriptions'
                )
            );
        }

        check_admin_referer(
            self::NONCE_ACTION,
            self::NONCE_NAME
        );

        $gateway_id = isset($_POST['gateway_id'])
            ? sanitize_key(wp_unslash($_POST['gateway_id']))
            : '';

        if (!$this->registry->has($gateway_id)) {
            $this->redirect('unknown_gateway');
        }

        $enabled = isset($_POST['enabled']);

        $default_scenario = isset($_POST['default_scenario'])
            ? sanitize_key(wp_unslash($_POST['default_scenario']))
            : 'approved';

        $allowed_scenarios = [
            'approved',
            'rejected',
            'pending',
            'cancelled',
            'technical_error',
        ];

        if (!in_array($default_scenario, $allowed_scenarios, true)) {
            $default_scenario = 'approved';
        }

        OptiGrid_Subscriptions_Gateway_Settings::save_gateway(
            $gateway_id,
            [
                'enabled'          => $enabled,
                'default_scenario' => $default_scenario,
            ]
        );

        /**
         * Permite reaccionar a cambios de configuración sin acoplar
         * el núcleo a una pasarela concreta.
         */
        do_action(
            'optigrid_subscriptions_gateway_settings_saved',
            $gateway_id,
            [
                'enabled'          => $enabled,
                'default_scenario' => $default_scenario,
            ]
        );

        $this->redirect('saved');
    }

    private function redirect(string $status): void
    {
        $url = add_query_arg(
            [
                'page'             => 'optigrid-subscriptions',
                'gateway_updated'  => sanitize_key($status),
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    public static function nonce_action(): string
    {
        return self::NONCE_ACTION;
    }

    public static function nonce_name(): string
    {
        return self::NONCE_NAME;
    }

    public static function form_action(): string
    {
        return self::ACTION;
    }
}
