<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Activación del plugin.
 */
final class OptiGrid_Subscriptions_Activator
{
    public static function activate(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        try {
            OptiGrid_Subscriptions_Database::install();
            OptiGrid_Subscriptions_Gateway_Settings::ensure_defaults();
            delete_option('optigrid_subscriptions_db_error');
        } catch (Throwable $exception) {
            update_option(
                'optigrid_subscriptions_db_error',
                $exception->getMessage(),
                false
            );

            throw $exception;
        }
    }
}
