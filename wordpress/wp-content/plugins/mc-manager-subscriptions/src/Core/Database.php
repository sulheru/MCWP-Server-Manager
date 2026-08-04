<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Instalación, actualización y diagnóstico del esquema de datos.
 */
final class OptiGrid_Subscriptions_Database
{
    public const OPTION_DB_VERSION = 'optigrid_subscriptions_db_version';

    /**
     * Devuelve los nombres físicos de las tablas usando el prefijo real
     * de la instalación de WordPress.
     *
     * @return array<string,string>
     */
    public static function tables(): array
    {
        global $wpdb;

        return [
            'plans'        => $wpdb->prefix . 'optigrid_subscription_plans',
            'subscriptions' => $wpdb->prefix . 'optigrid_subscriptions',
            'orders'       => $wpdb->prefix . 'optigrid_payment_orders',
            'transactions' => $wpdb->prefix . 'optigrid_payment_transactions',
            'events'       => $wpdb->prefix . 'optigrid_payment_events',
            'entitlements' => $wpdb->prefix . 'optigrid_entitlements',
        ];
    }

    /**
     * Instala o actualiza el esquema mediante dbDelta().
     *
     * Esta operación es idempotente. No elimina tablas ni columnas.
     */
    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::tables();

        $sql = [];

        /*
         * Planes comercializables.
         */
        $sql[] = "CREATE TABLE {$tables['plans']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(100) NOT NULL,
            name varchar(190) NOT NULL,
            description text NULL,
            price decimal(12,2) NOT NULL DEFAULT 0.00,
            currency char(3) NOT NULL DEFAULT 'EUR',
            duration_days int(10) unsigned NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_visible tinyint(1) NOT NULL DEFAULT 1,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY active_sort (is_active, sort_order),
            KEY public_sort (is_active, is_visible, sort_order)
        ) {$charset_collate};";

        /*
         * Periodos de acceso adquiridos por un usuario.
         */
        $sql[] = "CREATE TABLE {$tables['subscriptions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            plan_id bigint(20) unsigned NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'pending',
            starts_at datetime NULL,
            ends_at datetime NULL,
            cancelled_at datetime NULL,
            cancellation_reason varchar(255) NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_status (user_id, status),
            KEY plan_id (plan_id),
            KEY ends_at (ends_at)
        ) {$charset_collate};";

        /*
         * Intención comercial de pago.
         *
         * public_id puede exponerse fuera de wp-admin.
         * idempotency_key impide crear dos órdenes para la misma operación.
         */
        $sql[] = "CREATE TABLE {$tables['orders']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            idempotency_key varchar(191) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            plan_id bigint(20) unsigned NOT NULL,
            subscription_id bigint(20) unsigned NULL,
            gateway varchar(50) NOT NULL DEFAULT 'sandbox',
            status varchar(32) NOT NULL DEFAULT 'pending',
            amount decimal(12,2) NOT NULL,
            currency char(3) NOT NULL DEFAULT 'EUR',
            expires_at datetime NULL,
            paid_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY user_status (user_id, status),
            KEY subscription_id (subscription_id),
            KEY gateway_status (gateway, status)
        ) {$charset_collate};";

        /*
         * Resultado financiero comunicado por una pasarela.
         *
         * No se almacenan tarjetas ni credenciales bancarias.
         */
        $sql[] = "CREATE TABLE {$tables['transactions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            gateway varchar(50) NOT NULL,
            external_transaction_id varchar(191) NULL,
            transaction_type varchar(32) NOT NULL DEFAULT 'payment',
            status varchar(32) NOT NULL DEFAULT 'pending',
            amount decimal(12,2) NOT NULL,
            currency char(3) NOT NULL DEFAULT 'EUR',
            gateway_message text NULL,
            processed_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY gateway_external (gateway, external_transaction_id),
            KEY gateway_status (gateway, status)
        ) {$charset_collate};";

        /*
         * Registro de callbacks y webhooks.
         *
         * gateway + event_id proporciona idempotencia frente a reintentos
         * del proveedor.
         */
        $sql[] = "CREATE TABLE {$tables['events']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            gateway varchar(50) NOT NULL,
            event_id varchar(191) NOT NULL,
            event_type varchar(100) NOT NULL,
            payload_hash char(64) NOT NULL,
            payload longtext NULL,
            status varchar(32) NOT NULL DEFAULT 'received',
            error_message text NULL,
            received_at datetime NOT NULL,
            processed_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY gateway_event (gateway, event_id),
            KEY status_received (status, received_at)
        ) {$charset_collate};";

        /*
         * Derecho funcional derivado de una suscripción u otra fuente.
         *
         * No representa whitelist, blacklist ni el estado operativo
         * de Minecraft. Es únicamente el derecho comercial.
         */
        $sql[] = "CREATE TABLE {$tables['entitlements']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            entitlement_key varchar(100) NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'active',
            source_type varchar(32) NOT NULL,
            source_id bigint(20) unsigned NOT NULL,
            starts_at datetime NOT NULL,
            ends_at datetime NULL,
            revoked_at datetime NULL,
            revocation_reason varchar(255) NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY source_entitlement (source_type, source_id, entitlement_key),
            KEY user_entitlement_status (user_id, entitlement_key, status),
            KEY entitlement_ends (entitlement_key, ends_at)
        ) {$charset_collate};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        $missing = self::missing_tables();

        if ($missing !== []) {
            throw new RuntimeException(
                'No se pudieron crear todas las tablas: '
                . implode(', ', $missing)
            );
        }

        update_option(
            self::OPTION_DB_VERSION,
            OPTIGRID_SUBSCRIPTIONS_DB_VERSION,
            false
        );
    }

    /**
     * Ejecuta migraciones cuando la versión almacenada no coincide.
     */
    public static function maybe_upgrade(): void
    {
        $installed_version = (string) get_option(
            self::OPTION_DB_VERSION,
            ''
        );

        if (
            $installed_version === OPTIGRID_SUBSCRIPTIONS_DB_VERSION
            && self::missing_tables() === []
        ) {
            return;
        }

        try {
            self::install();
            delete_option('optigrid_subscriptions_db_error');
        } catch (Throwable $exception) {
            update_option(
                'optigrid_subscriptions_db_error',
                $exception->getMessage(),
                false
            );
        }
    }

    /**
     * Comprueba si una tabla existe.
     */
    public static function table_exists(string $table_name): bool
    {
        global $wpdb;

        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $wpdb->esc_like($table_name)
            )
        );

        return $result === $table_name;
    }

    /**
     * Devuelve las tablas que todavía no existen.
     *
     * @return array<int,string>
     */
    public static function missing_tables(): array
    {
        $missing = [];

        foreach (self::tables() as $table_name) {
            if (!self::table_exists($table_name)) {
                $missing[] = $table_name;
            }
        }

        return $missing;
    }

    /**
     * Estado resumido para el diagnóstico administrativo.
     *
     * @return array<string,mixed>
     */
    public static function status(): array
    {
        $tables = [];
        $all_present = true;

        foreach (self::tables() as $logical_name => $table_name) {
            $exists = self::table_exists($table_name);

            $tables[$logical_name] = [
                'name'   => $table_name,
                'exists' => $exists,
            ];

            if (!$exists) {
                $all_present = false;
            }
        }

        return [
            'expected_version'  => OPTIGRID_SUBSCRIPTIONS_DB_VERSION,
            'installed_version' => (string) get_option(
                self::OPTION_DB_VERSION,
                ''
            ),
            'all_present'       => $all_present,
            'tables'            => $tables,
            'error'             => (string) get_option(
                'optigrid_subscriptions_db_error',
                ''
            ),
        ];
    }
}
