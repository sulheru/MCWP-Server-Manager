<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SML_Token_Store {
    const TABLE_NAME = 'solidario_microsoft_tokens';
    const PREFIX = 'smlenc:v1:';

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
            access_token LONGTEXT NULL,
            refresh_token LONGTEXT NULL,
            id_token LONGTEXT NULL,
            scope TEXT NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY microsoft_account_id (microsoft_account_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        dbDelta($sql);
    }

    private static function encryption_key(): string {
        $material = '';

        if (defined('AUTH_KEY')) {
            $material .= AUTH_KEY;
        }
        if (defined('SECURE_AUTH_KEY')) {
            $material .= SECURE_AUTH_KEY;
        }
        if (defined('LOGGED_IN_KEY')) {
            $material .= LOGGED_IN_KEY;
        }
        if (defined('NONCE_KEY')) {
            $material .= NONCE_KEY;
        }

        if ($material === '') {
            $material = DB_NAME . DB_USER . ABSPATH;
        }

        return hash('sha256', $material, true);
    }

    public static function encrypt(?string $plain): ?string {
        if ($plain === null || $plain === '') {
            return $plain;
        }

        if (!function_exists('openssl_encrypt')) {
            return $plain;
        }

        $iv = random_bytes(16);
        $cipher = openssl_encrypt(
            $plain,
            'AES-256-CBC',
            self::encryption_key(),
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($cipher === false) {
            return $plain;
        }

        return self::PREFIX . base64_encode($iv . $cipher);
    }

    public static function decrypt(?string $stored): ?string {
        if ($stored === null || $stored === '') {
            return $stored;
        }

        if (strpos($stored, self::PREFIX) !== 0) {
            return $stored;
        }

        if (!function_exists('openssl_decrypt')) {
            return null;
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);

        if ($raw === false || strlen($raw) <= 16) {
            return null;
        }

        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);

        $plain = openssl_decrypt(
            $cipher,
            'AES-256-CBC',
            self::encryption_key(),
            OPENSSL_RAW_DATA,
            $iv
        );

        return $plain === false ? null : $plain;
    }

    public static function save_tokens(int $microsoft_account_id, array $token_response): void {
        global $wpdb;

        $table = self::table_name();
        $now = current_time('mysql');

        $expires_at = null;
        if (!empty($token_response['expires_in'])) {
            $expires_at = gmdate(
                'Y-m-d H:i:s',
                time() + (int) $token_response['expires_in']
            );
        }

        $data = [
            'microsoft_account_id' => $microsoft_account_id,
            'access_token' => self::encrypt($token_response['access_token'] ?? null),
            'refresh_token' => self::encrypt($token_response['refresh_token'] ?? null),
            'id_token' => self::encrypt($token_response['id_token'] ?? null),
            'scope' => $token_response['scope'] ?? null,
            'expires_at' => $expires_at,
            'updated_at' => $now,
        ];

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE microsoft_account_id = %d LIMIT 1",
                $microsoft_account_id
            )
        );

        if ($existing_id) {
            $wpdb->update(
                $table,
                $data,
                ['id' => (int) $existing_id],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
            return;
        }

        $data['created_at'] = $now;

        $wpdb->insert(
            $table,
            $data,
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    public static function get_tokens(int $microsoft_account_id): ?array {
        global $wpdb;

        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE microsoft_account_id = %d LIMIT 1",
                $microsoft_account_id
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        $row['access_token'] = self::decrypt($row['access_token'] ?? null);
        $row['refresh_token'] = self::decrypt($row['refresh_token'] ?? null);
        $row['id_token'] = self::decrypt($row['id_token'] ?? null);

        return $row;
    }

    public static function delete_tokens(int $microsoft_account_id): void {
        global $wpdb;

        $wpdb->delete(
            self::table_name(),
            ['microsoft_account_id' => $microsoft_account_id],
            ['%d']
        );
    }
}
