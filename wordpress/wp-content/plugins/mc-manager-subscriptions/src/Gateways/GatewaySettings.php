<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuración persistente de extensiones de pago.
 */
final class OptiGrid_Subscriptions_Gateway_Settings
{
    public const OPTION_NAME = 'optigrid_subscriptions_gateways';

    /**
     * @return array<string,mixed>
     */
    public static function all(): array
    {
        $stored = get_option(self::OPTION_NAME, []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * @return array<string,mixed>
     */
    public static function for_gateway(string $gateway_id): array
    {
        $gateway_id = sanitize_key($gateway_id);
        $all = self::all();
        $settings = $all[$gateway_id] ?? [];

        return is_array($settings) ? $settings : [];
    }

    public static function is_enabled(string $gateway_id): bool
    {
        $settings = self::for_gateway($gateway_id);

        return !empty($settings['enabled']);
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function save_gateway(
        string $gateway_id,
        array $settings
    ): bool {
        $gateway_id = sanitize_key($gateway_id);

        if ($gateway_id === '') {
            return false;
        }

        $all = self::all();
        $all[$gateway_id] = $settings;

        return update_option(self::OPTION_NAME, $all, false);
    }

    /**
     * Crea la configuración inicial sin activar ninguna pasarela.
     */
    public static function ensure_defaults(): void
    {
        $all = self::all();

        if (isset($all['sandbox']) && is_array($all['sandbox'])) {
            return;
        }

        $all['sandbox'] = [
            'enabled'          => false,
            'default_scenario' => 'approved',
        ];

        add_option(self::OPTION_NAME, $all, '', false);
    }
}
