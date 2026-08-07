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
        $settings = [
            'enabled' => $enabled,
        ];

        if ($gateway_id === 'sandbox') {
            $default_scenario =
                isset($_POST['default_scenario'])
                    ? sanitize_key(
                        wp_unslash($_POST['default_scenario'])
                    )
                    : 'approved';

            $allowed_scenarios = [
                'approved',
                'rejected',
                'pending',
                'cancelled',
                'technical_error',
            ];

            if (
                !in_array(
                    $default_scenario,
                    $allowed_scenarios,
                    true
                )
            ) {
                $default_scenario = 'approved';
            }

            $settings['default_scenario'] =
                $default_scenario;
        }

        if ($gateway_id === 'paypal') {
            $current =
                OptiGrid_Subscriptions_Gateway_Settings::for_gateway(
                    'paypal'
                );

            $client_id =
                isset($_POST['client_id'])
                    ? sanitize_text_field(
                        wp_unslash($_POST['client_id'])
                    )
                    : '';

            $client_secret =
                isset($_POST['client_secret'])
                    ? sanitize_text_field(
                        wp_unslash($_POST['client_secret'])
                    )
                    : '';

            if ($client_id === '') {
                $client_id =
                    (string) ($current['client_id'] ?? '');
            }

            if ($client_secret === '') {
                $client_secret =
                    (string) ($current['client_secret'] ?? '');
            }

            $webhook_id = isset($_POST['webhook_id'])
                ? sanitize_text_field(wp_unslash($_POST['webhook_id']))
                : '';

            $settings['environment'] = 'sandbox';
            $settings['client_id'] = $client_id;
            $settings['client_secret'] = $client_secret;
            $settings['webhook_id'] = $webhook_id !== ''
                ? $webhook_id
                : (string) ($current['webhook_id'] ?? '');
        }

        OptiGrid_Subscriptions_Gateway_Settings::save_gateway(
            $gateway_id,
            $settings
        );

        $hook_settings = $settings;

        if (isset($hook_settings['client_secret'])) {
            unset($hook_settings['client_secret']);
            $hook_settings['client_secret_configured'] =
                ($settings['client_secret'] ?? '') !== '';
        }

        do_action(
            'optigrid_subscriptions_gateway_settings_saved',
            $gateway_id,
            $hook_settings
        );

        $this->redirect('saved');
    }

    private function redirect(string $status): void
    {
        $url = add_query_arg(
            [
                'page' => 'optigrid-subscriptions',
                'gateway_updated' => sanitize_key($status),
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
