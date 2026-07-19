<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SML_Minecraft_Verifier {
    const TABLE_NAME = 'solidario_minecraft_verification';

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    public static function install(): void {
        global $wpdb;

        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            microsoft_account_id BIGINT UNSIGNED NOT NULL,
            microsoft_ok TINYINT(1) NOT NULL DEFAULT 0,
            xbox_ok TINYINT(1) NOT NULL DEFAULT 0,
            xsts_ok TINYINT(1) NOT NULL DEFAULT 0,
            minecraft_ok TINYINT(1) NOT NULL DEFAULT 0,
            minecraft_username VARCHAR(32) NULL,
            minecraft_uuid VARCHAR(64) NULL,
            last_error TEXT NULL,
            raw_response_json LONGTEXT NULL,
            last_checked_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY microsoft_account_id (microsoft_account_id),
            KEY minecraft_username (minecraft_username),
            KEY minecraft_uuid (minecraft_uuid),
            KEY minecraft_ok (minecraft_ok)
        ) $charset_collate;";

        dbDelta($sql);
    }

    public static function record_status(int $microsoft_account_id, array $data): void {
        global $wpdb;

        $table = self::table_name();
        $now = current_time('mysql');

        $defaults = [
            'microsoft_ok' => 0,
            'xbox_ok' => 0,
            'xsts_ok' => 0,
            'minecraft_ok' => 0,
            'minecraft_username' => null,
            'minecraft_uuid' => null,
            'last_error' => null,
            'raw_response_json' => null,
            'last_checked_at' => $now,
            'updated_at' => $now,
        ];

        $row = array_merge($defaults, $data);
        $row['microsoft_account_id'] = $microsoft_account_id;

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE microsoft_account_id = %d LIMIT 1",
                $microsoft_account_id
            )
        );

        if ($existing_id) {
            $wpdb->update(
                $table,
                $row,
                ['id' => (int) $existing_id],
                ['%d','%d','%d','%d','%s','%s','%s','%s','%s','%s','%d'],
                ['%d']
            );
            return;
        }

        $row['created_at'] = $now;

        $wpdb->insert(
            $table,
            $row,
            ['%d','%d','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s']
        );
    }

    public static function get_status(int $microsoft_account_id): ?array {
        global $wpdb;

        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE microsoft_account_id = %d LIMIT 1",
                $microsoft_account_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public static function test_verification(int $microsoft_account_id): array {
        if (!class_exists('SML_Token_Store')) {
            $result = [
                'microsoft_ok' => 0,
                'last_error' => 'SML_Token_Store no está disponible.',
            ];
            self::record_status($microsoft_account_id, $result);
            return $result;
        }

        $tokens = SML_Token_Store::get_tokens($microsoft_account_id);

        if (!$tokens || empty($tokens['access_token'])) {
            $result = [
                'microsoft_ok' => 0,
                'last_error' => 'No hay access_token Microsoft disponible.',
            ];
            self::record_status($microsoft_account_id, $result);
            return $result;
        }

        $result = [
            'microsoft_ok' => 1,
            'xbox_ok' => 0,
            'xsts_ok' => 0,
            'minecraft_ok' => 0,
            'last_error' => 'Diagnóstico base OK. Flujo Xbox todavía no implementado.',
            'raw_response_json' => wp_json_encode([
                'token_present' => true,
                'scope' => $tokens['scope'] ?? null,
                'expires_at' => $tokens['expires_at'] ?? null,
            ]),
        ];

        self::record_status($microsoft_account_id, $result);

        return $result;
    }
}
