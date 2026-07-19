<?php
/**
 * Plugin Name: Gestor MC - Azure Entra ID
 * Description: Módulo de Gestor MC para autenticación con Microsoft Azure Entra ID y vinculación de cuentas de Minecraft.
 * Version: 0.11.1
 * Author: OptiGrid IT
 * Author URI: https://optigrid-it.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gestor-mc-azure-entra-id
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Update URI: false
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-sml-token-store.php';
require_once __DIR__ . '/includes/class-sml-minecraft-verifier.php';

final class Solidario_Microsoft_Login {
    const VERSION = '0.11.0';
    const OPTION_NAME = 'sml_settings';
    const TABLE_NAME = 'solidario_microsoft_accounts';
    const MC_TABLE_NAME = 'solidario_minecraft_accounts';
    const TOKEN_TABLE_NAME = 'solidario_microsoft_tokens';
    const XBOX_TOKEN_TABLE_NAME = 'solidario_microsoft_xbox_tokens';
    const XBOX_STATE_TABLE_NAME = 'solidario_microsoft_xbox_states';

    public static function init(): void {
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        add_action('admin_menu', [__CLASS__, 'admin_menu'], 20);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_init', [__CLASS__, 'maybe_upgrade_tables']);
        add_action('admin_init', [__CLASS__, 'ensure_xbox_token_table']);
        add_action('admin_init', [__CLASS__, 'ensure_xbox_state_table']);
        add_action('template_redirect', [__CLASS__, 'maybe_handle_callback']);
        add_action('init', [__CLASS__, 'maybe_handle_xbox_pretty_callback'], 1);
        add_action('admin_post_sml_xbox_start', [__CLASS__, 'handle_xbox_start']);
        add_action('admin_post_sml_test_minecraft_profile', [__CLASS__, 'handle_test_minecraft_profile']);
        add_action('admin_post_sml_xbox_callback', [__CLASS__, 'handle_xbox_callback']);
        add_action('admin_post_nopriv_sml_xbox_callback', [__CLASS__, 'handle_xbox_callback']);
        add_shortcode('solidario_microsoft_login', [__CLASS__, 'render_login_shortcode']);
        add_shortcode('solidario_microsoft_profile', [__CLASS__, 'render_profile_shortcode']);
        add_shortcode('solidario_microsoft_logout', [__CLASS__, 'render_logout_shortcode']);
        add_action('after_setup_theme', [__CLASS__, 'hide_admin_bar_for_players']);
        add_action('admin_init', [__CLASS__, 'block_wp_admin_for_players']);
        add_action('template_redirect', [__CLASS__, 'maybe_handle_minecraft_profile_post']);
    }

    public static function activate(): void {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            microsoft_oid VARCHAR(191) NOT NULL,
            microsoft_sub VARCHAR(191) NULL,
            tenant_id VARCHAR(191) NULL,
            email VARCHAR(191) NULL,
            display_name VARCHAR(191) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            last_login_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY microsoft_oid (microsoft_oid),
            KEY wp_user_id (wp_user_id),
            KEY email (email)
        ) $charset_collate;";

        dbDelta($sql);

        $token_table = $wpdb->prefix . self::TOKEN_TABLE_NAME;

        $token_sql = "CREATE TABLE $token_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            microsoft_account_id BIGINT UNSIGNED NOT NULL,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            access_token LONGTEXT NULL,
            refresh_token LONGTEXT NULL,
            id_token LONGTEXT NULL,
            token_type VARCHAR(64) NULL,
            scope TEXT NULL,
            expires_in INT NULL,
            expires_at DATETIME NULL,
            raw_token_response LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY microsoft_account_id (microsoft_account_id),
            KEY wp_user_id (wp_user_id)
        ) $charset_collate;";

        dbDelta($token_sql);


        $mc_table = $wpdb->prefix . self::MC_TABLE_NAME;

        $mc_sql = "CREATE TABLE $mc_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            microsoft_account_id BIGINT UNSIGNED NULL,
            minecraft_username VARCHAR(32) NULL,
            minecraft_uuid VARCHAR(64) NULL,
            verification_status VARCHAR(32) NOT NULL DEFAULT 'unverified',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY wp_user_id (wp_user_id),
            UNIQUE KEY minecraft_username (minecraft_username),
            KEY microsoft_account_id (microsoft_account_id),
            KEY verification_status (verification_status)
        ) $charset_collate;";

        dbDelta($mc_sql);

        if (class_exists('SML_Token_Store')) {
            SML_Token_Store::install();
        }

        if (class_exists('SML_Minecraft_Verifier')) {
            SML_Minecraft_Verifier::install();
        }
    }



    public static function maybe_upgrade_tables(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $installed_version = get_option('sml_db_version', '');

        if ($installed_version !== self::VERSION) {
            self::activate();
            update_option('sml_db_version', self::VERSION);
        }
    }


    public static function current_user_can_use_wp_backend(): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();

        return (
            in_array('administrator', $user->roles, true) ||
            in_array('editor', $user->roles, true) ||
            in_array('author', $user->roles, true)
        );
    }

    public static function hide_admin_bar_for_players(): void {
        if (!is_user_logged_in()) {
            return;
        }

        if (self::current_user_can_use_wp_backend()) {
            return;
        }

        show_admin_bar(false);
    }

    public static function block_wp_admin_for_players(): void {
        if (!is_user_logged_in()) {
            return;
        }

        if (wp_doing_ajax()) {
            return;
        }

        $action = isset($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
        if (in_array($action, ['sml_xbox_start', 'sml_xbox_callback', 'sml_test_minecraft_profile'], true)) {
            return;
        }

        if (self::current_user_can_use_wp_backend()) {
            return;
        }

        wp_safe_redirect(home_url('/mi-cuenta-minecraft/'));
        exit;
    }


    public static function defaults(): array {
        return [
            'client_id' => '',
            'client_secret' => '',
            'tenant_mode' => 'common',
            'redirect_uri' => home_url('/'),
            'post_login_redirect' => home_url('/mi-cuenta-minecraft/'),
        ];
    }

    public static function settings(): array {
        return wp_parse_args(get_option(self::OPTION_NAME, []), self::defaults());
    }

    public static function register_settings(): void {
        register_setting(
            'sml_settings_group',
            self::OPTION_NAME,
            [__CLASS__, 'sanitize_settings']
        );
    }

    public static function sanitize_settings($input): array {
        return [
            'client_id' => sanitize_text_field($input['client_id'] ?? ''),
            'client_secret' => sanitize_text_field($input['client_secret'] ?? ''),
            'tenant_mode' => sanitize_text_field($input['tenant_mode'] ?? 'common'),
            'redirect_uri' => esc_url_raw($input['redirect_uri'] ?? home_url('/')),
            'post_login_redirect' => esc_url_raw($input['post_login_redirect'] ?? home_url('/mi-cuenta-minecraft/')),
        ];
    }

    public static function admin_menu(): void {
/*
        if (!menu_page_url('gestor-mc-srv', false)) {
            add_menu_page(
                'Gestor MC',
                'Gestor MC-SRV',
                'manage_options',
                'gestor-mc-srv',
                [__CLASS__, 'render_settings_page'],
                'dashicons-shield',
                58
            );
        }
*/
        add_submenu_page(
            'gestor-mc-srv',
            'Azure Entra ID',
            'Azure Entra ID',
            'manage_options',
            'solidario-microsoft-login',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function build_login_url(): string {
        $s = self::settings();
        $state = wp_create_nonce('sml_oauth_state');

        $params = [
            'client_id' => $s['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $s['redirect_uri'],
            'response_mode' => 'query',
            'scope' => 'openid profile email offline_access User.Read',
            'state' => $state,
        ];

        return 'https://login.microsoftonline.com/' . rawurlencode($s['tenant_mode']) . '/oauth2/v2.0/authorize?' . http_build_query($params);
    }

    public static function maybe_handle_callback(): void {
        if (empty($_GET['code']) || empty($_GET['state'])) {
            return;
        }

        $state = sanitize_text_field(wp_unslash($_GET['state']));
        $code = sanitize_text_field(wp_unslash($_GET['code']));

        if (!wp_verify_nonce($state, 'sml_oauth_state')) {
            wp_die('State inválido.', 'OAuth Error', ['response' => 400]);
        }

        $token_response = self::exchange_code_for_tokens($code);
        if (is_wp_error($token_response)) {
            wp_die('<pre>' . esc_html($token_response->get_error_message()) . '</pre>', 'Token Error', ['response' => 500]);
        }

        $claims = [];
        if (!empty($token_response['id_token'])) {
            $claims = self::decode_jwt_payload($token_response['id_token']);
        }

        if (empty($claims['oid'])) {
            wp_die('No se recibió microsoft_oid en el id_token.', 'Identity Error', ['response' => 500]);
        }

        $graph = [];
        if (!empty($token_response['access_token'])) {
            $graph_response = self::call_graph_me($token_response['access_token']);
            if (!is_wp_error($graph_response)) {
                $graph = $graph_response;
            }
        }

        $user_id = self::find_or_create_wp_user($claims, $graph);
        if (is_wp_error($user_id)) {
            wp_die('<pre>' . esc_html($user_id->get_error_message()) . '</pre>', 'User Error', ['response' => 500]);
        }

        $microsoft_account_id = self::upsert_microsoft_account($user_id, $claims, $graph);

        if (
            class_exists('SML_Token_Store') &&
            is_int($microsoft_account_id) &&
            $microsoft_account_id > 0
        ) {
            SML_Token_Store::save_tokens($microsoft_account_id, $token_response);
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        do_action('wp_login', get_userdata($user_id)->user_login, get_userdata($user_id));

        $s = self::settings();
        wp_safe_redirect($s['post_login_redirect']);
        exit;
    }

    public static function find_or_create_wp_user(array $claims, array $graph) {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $oid = sanitize_text_field($claims['oid']);

        $existing_user_id = $wpdb->get_var(
            $wpdb->prepare("SELECT wp_user_id FROM $table WHERE microsoft_oid = %s LIMIT 1", $oid)
        );

        if ($existing_user_id) {
            return (int) $existing_user_id;
        }

        $email = sanitize_email(
            $graph['mail']
            ?? $claims['email']
            ?? $claims['preferred_username']
            ?? ''
        );

        if ($email && email_exists($email)) {
            return (int) email_exists($email);
        }

        $login_base = 'mc_ms_' . substr(preg_replace('/[^a-zA-Z0-9]/', '', $oid), 0, 16);
        $user_login = $login_base;
        $i = 1;

        while (username_exists($user_login)) {
            $user_login = $login_base . '_' . $i;
            $i++;
        }

        $display_name = sanitize_text_field(
            $graph['displayName']
            ?? $claims['name']
            ?? $email
            ?? $user_login
        );

        $user_id = wp_insert_user([
            'user_login' => $user_login,
            'user_pass' => wp_generate_password(32, true, true),
            'user_email' => $email,
            'display_name' => $display_name,
            'role' => 'subscriber',
        ]);

        return $user_id;
    }

    public static function upsert_microsoft_account(int $user_id, array $claims, array $graph): int {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;
        $now = current_time('mysql');

        $oid = sanitize_text_field($claims['oid']);
        $sub = sanitize_text_field($claims['sub'] ?? '');
        $tid = sanitize_text_field($claims['tid'] ?? '');

        $email = sanitize_email(
            $graph['mail']
            ?? $claims['email']
            ?? $claims['preferred_username']
            ?? ''
        );

        $display_name = sanitize_text_field(
            $graph['displayName']
            ?? $claims['name']
            ?? ''
        );

        $existing_id = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE microsoft_oid = %s LIMIT 1", $oid)
        );

        if ($existing_id) {
            $wpdb->update(
                $table,
                [
                    'wp_user_id' => $user_id,
                    'microsoft_sub' => $sub,
                    'tenant_id' => $tid,
                    'email' => $email,
                    'display_name' => $display_name,
                    'updated_at' => $now,
                    'last_login_at' => $now,
                ],
                ['id' => (int) $existing_id],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
            
            return (int) $existing_id;
        }

        $wpdb->insert(
            $table,
            [
                'wp_user_id' => $user_id,
                'microsoft_oid' => $oid,
                'microsoft_sub' => $sub,
                'tenant_id' => $tid,
                'email' => $email,
                'display_name' => $display_name,
                'created_at' => $now,
                'updated_at' => $now,
                'last_login_at' => $now,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }


    public static function upsert_microsoft_tokens(int $microsoft_account_id, int $user_id, array $token_response): void {
        if ($microsoft_account_id <= 0) {
            return;
        }

        global $wpdb;

        if ($user_id <= 0) {
            $account_table = $wpdb->prefix . self::TABLE_NAME;
            $user_id = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT wp_user_id FROM $account_table WHERE id = %d LIMIT 1",
                    $microsoft_account_id
                )
            );
        }

        if ($user_id <= 0) {
            return;
        }

        $table = $wpdb->prefix . self::TOKEN_TABLE_NAME;
        $now = current_time('mysql');

        $expires_in = isset($token_response['expires_in']) ? (int) $token_response['expires_in'] : null;
        $expires_at = $expires_in ? gmdate('Y-m-d H:i:s', time() + $expires_in) : null;

        $data = [
            'microsoft_account_id' => $microsoft_account_id,
            'wp_user_id' => $user_id,
            'access_token' => $token_response['access_token'] ?? null,
            'refresh_token' => $token_response['refresh_token'] ?? null,
            'id_token' => $token_response['id_token'] ?? null,
            'token_type' => $token_response['token_type'] ?? null,
            'scope' => $token_response['scope'] ?? null,
            'expires_in' => $expires_in,
            'expires_at' => $expires_at,
            'raw_token_response' => wp_json_encode($token_response),
            'updated_at' => $now,
        ];

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE microsoft_account_id = %d LIMIT 1",
                $microsoft_account_id
            )
        );

        if ($existing_id) {
            $wpdb->update($table, $data, ['id' => $existing_id]);
            return;
        }

        $data['created_at'] = $now;
        $wpdb->insert($table, $data);
    }


    public static function ensure_xbox_token_table(): void {
        global $wpdb;

        $table = $wpdb->prefix . self::XBOX_TOKEN_TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            microsoft_account_id BIGINT UNSIGNED NOT NULL,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            access_token LONGTEXT NULL,
            refresh_token LONGTEXT NULL,
            token_type VARCHAR(64) NULL,
            scope TEXT NULL,
            expires_in INT NULL,
            expires_at DATETIME NULL,
            raw_token_response LONGTEXT NULL,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY microsoft_account_id (microsoft_account_id),
            KEY wp_user_id (wp_user_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }


    public static function ensure_xbox_state_table(): void {
        global $wpdb;

        $table = $wpdb->prefix . self::XBOX_STATE_TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            state VARCHAR(128) NOT NULL,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            microsoft_account_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY state (state),
            KEY wp_user_id (wp_user_id),
            KEY microsoft_account_id (microsoft_account_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function save_xbox_state(string $state, int $user_id, int $microsoft_account_id): void {
        self::ensure_xbox_state_table();

        global $wpdb;

        $table = $wpdb->prefix . self::XBOX_STATE_TABLE_NAME;
        $now = current_time('mysql');
        $expires_at = gmdate('Y-m-d H:i:s', time() + 10 * MINUTE_IN_SECONDS);

        $wpdb->insert($table, [
            'state' => $state,
            'wp_user_id' => $user_id,
            'microsoft_account_id' => $microsoft_account_id,
            'created_at' => $now,
            'expires_at' => $expires_at,
            'consumed_at' => null,
        ]);
    }

    public static function consume_xbox_state(string $state): ?array {
        self::ensure_xbox_state_table();

        global $wpdb;

        $table = $wpdb->prefix . self::XBOX_STATE_TABLE_NAME;
        $now_gmt = current_time('mysql');

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE BINARY state = %s LIMIT 1",
                $state
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $wpdb->update(
            $table,
            ['consumed_at' => current_time('mysql')],
            ['id' => (int) $row['id']]
        );

        return $row;
    }


    public static function handle_xbox_start(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/entrar/'));
            exit;
        }

        $user_id = get_current_user_id();
        $account = self::get_microsoft_account_for_user($user_id);

        if (!$account || empty($account['id'])) {
            wp_die('DEBUG Xbox start: usuario logueado #' . esc_html((string) $user_id) . ' sin cuenta Microsoft vinculada.', 'Xbox Auth Error', ['response' => 400]);
        }

        $s = self::settings();
        if (empty($s['client_id']) || empty($s['client_secret'])) {
            wp_die('Falta configurar Client ID o Client Secret.', 'Xbox Auth Error', ['response' => 500]);
        }

        $state = wp_generate_password(32, false, false);
        self::save_xbox_state($state, $user_id, (int) $account['id']);

        $redirect_uri = home_url('/sml-xbox-callback/');

        $params = [
            'client_id' => $s['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'response_mode' => 'query',
            'scope' => 'XboxLive.SignIn XboxLive.offline_access',
            'state' => $state,
        ];

        $tenant = 'consumers';
        $authorize_url = 'https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/authorize?' . http_build_query($params);
        wp_redirect($authorize_url);
        exit;
    }


    public static function maybe_handle_xbox_pretty_callback(): void {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

        if (!in_array($path, ['/sml-xbox-callback/', '/sml-xbox-callback'], true)) {
            return;
        }

        self::handle_xbox_callback();
        exit;
    }

    public static function handle_xbox_callback(): void {
        self::ensure_xbox_token_table();

        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';

        if (!$code || !$state) {
            wp_die('Callback Xbox incompleto.', 'Xbox Auth Error', ['response' => 400]);
        }

        $state_data = self::consume_xbox_state($state);

        if (!$state_data || empty($state_data['wp_user_id']) || empty($state_data['microsoft_account_id'])) {
            global $wpdb;
            $states_table = $wpdb->prefix . self::XBOX_STATE_TABLE_NAME;
            $known_states = $wpdb->get_results(
                "SELECT id, state, wp_user_id, microsoft_account_id, created_at, expires_at, consumed_at FROM $states_table ORDER BY id DESC LIMIT 10",
                ARRAY_A
            );

            wp_die(
                '<h1>DEBUG State inválido</h1>' .
                '<p>State recibido: <code>' . esc_html($state) . '</code></p>' .
                '<pre>' . esc_html(print_r($known_states, true)) . '</pre>',
                'Xbox Auth Error',
                ['response' => 400]
            );
        }

        $token_response = self::exchange_code_for_xbox_tokens($code);

        if (is_wp_error($token_response)) {
            self::save_xbox_error(
                (int) $state_data['microsoft_account_id'],
                (int) $state_data['wp_user_id'],
                $token_response->get_error_message()
            );
            wp_die('<pre>' . esc_html($token_response->get_error_message()) . '</pre>', 'Xbox Token Error', ['response' => 500]);
        }

        self::upsert_xbox_tokens(
            (int) $state_data['microsoft_account_id'],
            (int) $state_data['wp_user_id'],
            $token_response
        );

        wp_safe_redirect(add_query_arg('xbox_connected', '1', home_url('/mi-cuenta-minecraft/')));
        exit;
    }

    public static function exchange_code_for_xbox_tokens(string $code) {
        $s = self::settings();

        $endpoint = 'https://login.microsoftonline.com/consumers/oauth2/v2.0/token';
        $redirect_uri = home_url('/sml-xbox-callback/');

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'body' => [
                'client_id' => $s['client_id'],
                'client_secret' => $s['client_secret'],
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri,
                'scope' => 'XboxLive.SignIn XboxLive.offline_access',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status < 200 || $status >= 300) {
            return new WP_Error('sml_xbox_token_error', 'HTTP ' . $status . "\n\n" . $body);
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return new WP_Error('sml_xbox_token_json_error', 'Respuesta JSON inválida: ' . $body);
        }

        return $json;
    }

    public static function upsert_xbox_tokens(int $microsoft_account_id, int $user_id, array $token_response): void {
        self::ensure_xbox_token_table();

        global $wpdb;

        $table = $wpdb->prefix . self::XBOX_TOKEN_TABLE_NAME;
        $now = current_time('mysql');

        $expires_in = isset($token_response['expires_in']) ? (int) $token_response['expires_in'] : null;
        $expires_at = $expires_in ? gmdate('Y-m-d H:i:s', time() + $expires_in) : null;

        $data = [
            'microsoft_account_id' => $microsoft_account_id,
            'wp_user_id' => $user_id,
            'access_token' => self::sml_lab_encrypt($token_response['access_token'] ?? ''),
            'refresh_token' => self::sml_lab_encrypt($token_response['refresh_token'] ?? ''),
            'token_type' => $token_response['token_type'] ?? null,
            'scope' => $token_response['scope'] ?? null,
            'expires_in' => $expires_in,
            'expires_at' => $expires_at,
            'raw_token_response' => self::sml_lab_encrypt(wp_json_encode($token_response)),
            'last_error' => null,
            'updated_at' => $now,
        ];

        $existing_id = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE microsoft_account_id = %d LIMIT 1", $microsoft_account_id)
        );

        if ($existing_id) {
            $wpdb->update($table, $data, ['id' => $existing_id]);
            return;
        }

        $data['created_at'] = $now;
        $wpdb->insert($table, $data);
    }

    public static function save_xbox_error(int $microsoft_account_id, int $user_id, string $error): void {
        self::ensure_xbox_token_table();

        global $wpdb;

        $table = $wpdb->prefix . self::XBOX_TOKEN_TABLE_NAME;
        $now = current_time('mysql');

        $existing_id = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE microsoft_account_id = %d LIMIT 1", $microsoft_account_id)
        );

        if ($existing_id) {
            $wpdb->update($table, [
                'wp_user_id' => $user_id,
                'last_error' => $error,
                'updated_at' => $now,
            ], ['id' => $existing_id]);
            return;
        }

        $wpdb->insert($table, [
            'microsoft_account_id' => $microsoft_account_id,
            'wp_user_id' => $user_id,
            'last_error' => $error,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private static function sml_lab_encrypt(string $value): ?string {
        if ($value === '') {
            return null;
        }

        if (!function_exists('openssl_encrypt')) {
            return $value;
        }

        $key_source = defined('AUTH_KEY') ? AUTH_KEY : wp_salt('auth');
        $key = hash('sha256', $key_source, true);
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            return $value;
        }

        return 'smlxbox:v1:' . base64_encode($iv . $ciphertext);
    }


    public static function exchange_code_for_tokens(string $code) {
        $s = self::settings();

        $endpoint = 'https://login.microsoftonline.com/' . rawurlencode($s['tenant_mode']) . '/oauth2/v2.0/token';

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'body' => [
                'client_id' => $s['client_id'],
                'client_secret' => $s['client_secret'],
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $s['redirect_uri'],
                'scope' => 'openid profile email offline_access User.Read',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            return new WP_Error('sml_token_error', 'HTTP ' . $status . "\n\n" . $body);
        }

        if (!is_array($json)) {
            return new WP_Error('sml_token_json_error', 'Respuesta JSON inválida: ' . $body);
        }

        return $json;
    }

    public static function call_graph_me(string $access_token) {
        $response = wp_remote_get('https://graph.microsoft.com/v1.0/me', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            return new WP_Error('sml_graph_error', 'HTTP ' . $status . "\n\n" . $body);
        }

        if (!is_array($json)) {
            return new WP_Error('sml_graph_json_error', 'Respuesta JSON inválida: ' . $body);
        }

        return $json;
    }

    public static function decode_jwt_payload(string $jwt): array {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }

        $payload = $parts[1];
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $decoded = base64_decode(strtr($payload, '-_', '+/'));

        if ($decoded === false) {
            return [];
        }

        $json = json_decode($decoded, true);
        return is_array($json) ? $json : [];
    }

    public static function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $s = self::settings();
        $login_url = self::build_login_url();

        echo '<div class="wrap">';
        echo '<h1>MC Azure Entra ID</h1>';
        echo '<p><strong>Versión:</strong> ' . esc_html(self::VERSION) . '</p>';

        echo '<form method="post" action="options.php">';
        settings_fields('sml_settings_group');

        echo '<table class="form-table" role="presentation">';

        echo '<tr><th scope="row">Tenant Mode</th><td>';
        echo '<input type="text" name="' . esc_attr(self::OPTION_NAME) . '[tenant_mode]" value="' . esc_attr($s['tenant_mode']) . '" class="regular-text">';
        echo '<p class="description">Usar <code>common</code> para aceptar todas las cuentas Microsoft.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Client ID</th><td>';
        echo '<input type="text" name="' . esc_attr(self::OPTION_NAME) . '[client_id]" value="' . esc_attr($s['client_id']) . '" class="regular-text">';
        echo '</td></tr>';

        echo '<tr><th scope="row">Client Secret</th><td>';
        echo '<input type="password" name="' . esc_attr(self::OPTION_NAME) . '[client_secret]" value="' . esc_attr($s['client_secret']) . '" class="regular-text">';
        echo '</td></tr>';

        echo '<tr><th scope="row">Redirect URI</th><td>';
        echo '<input type="url" name="' . esc_attr(self::OPTION_NAME) . '[redirect_uri]" value="' . esc_attr($s['redirect_uri']) . '" class="regular-text">';
        echo '</td></tr>';

        echo '<tr><th scope="row">Post Login Redirect</th><td>';
        echo '<input type="url" name="' . esc_attr(self::OPTION_NAME) . '[post_login_redirect]" value="' . esc_attr($s['post_login_redirect']) . '" class="regular-text">';
        echo '</td></tr>';

        echo '</table>';

        submit_button('Guardar configuración');
        echo '</form>';

        echo '<hr>';
        echo '<h2>Prueba OAuth + creación de usuario</h2>';

        if (empty($s['client_id'])) {
            echo '<p>Introduce primero el Client ID.</p>';
        } else {
            echo '<p><a class="button button-primary" href="' . esc_url($login_url) . '">Probar login Microsoft</a></p>';
        }

        echo '<hr>';
        echo '<h2>Shortcode</h2>';
        echo '<code>[solidario_microsoft_login]</code><br />';
        echo '<code>[solidario_microsoft_logout]</code><br />';
        echo '<code>[solidario_microsoft_profile]</code><br />';

        echo '</div>';
    }



    public static function maybe_handle_minecraft_profile_post(): void {
        if (
            empty($_POST['sml_action']) ||
            $_POST['sml_action'] !== 'save_minecraft_username'
        ) {
            return;
        }

        if (!is_user_logged_in()) {
            wp_die('Debes iniciar sesión.', 'Acceso denegado', ['response' => 403]);
        }

        if (
            empty($_POST['sml_minecraft_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['sml_minecraft_nonce'])),
                'sml_save_minecraft_username'
            )
        ) {
            wp_die('Nonce inválido.', 'Error de seguridad', ['response' => 400]);
        }

        $username = sanitize_text_field(wp_unslash($_POST['minecraft_username'] ?? ''));
        $username = trim($username);

        if (!preg_match('/^[A-Za-z0-9_]{3,16}$/', $username)) {
            wp_safe_redirect(add_query_arg('minecraft_error', 'invalid_username', home_url('/mi-cuenta-minecraft/')));
            exit;
        }

        $result = self::upsert_minecraft_account(get_current_user_id(), $username);

        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg('minecraft_error', rawurlencode($result->get_error_code()), home_url('/mi-cuenta-minecraft/')));
            exit;
        }

        wp_safe_redirect(add_query_arg('minecraft_saved', '1', home_url('/mi-cuenta-minecraft/')));
        exit;
    }

    public static function get_microsoft_account_for_user(int $user_id): ?array {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE_NAME;

        $account = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE wp_user_id = %d LIMIT 1", $user_id),
            ARRAY_A
        );

        return is_array($account) ? $account : null;
    }

    public static function get_minecraft_account_for_user(int $user_id): ?array {
        global $wpdb;

        $table = $wpdb->prefix . self::MC_TABLE_NAME;

        $account = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE wp_user_id = %d LIMIT 1", $user_id),
            ARRAY_A
        );

        return is_array($account) ? $account : null;
    }

    public static function upsert_minecraft_account(int $user_id, string $username) {
        global $wpdb;

        $mc_table = $wpdb->prefix . self::MC_TABLE_NAME;
        $now = current_time('mysql');

        $existing_for_username = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $mc_table WHERE minecraft_username = %s AND wp_user_id <> %d LIMIT 1",
                $username,
                $user_id
            ),
            ARRAY_A
        );

        if ($existing_for_username) {
            return new WP_Error('username_taken', 'Ese nombre de Minecraft ya está vinculado a otra cuenta.');
        }

        $microsoft_account = self::get_microsoft_account_for_user($user_id);
        $microsoft_account_id = $microsoft_account ? (int) $microsoft_account['id'] : null;

        $existing = self::get_minecraft_account_for_user($user_id);

        if ($existing) {
            $wpdb->update(
                $mc_table,
                [
                    'microsoft_account_id' => $microsoft_account_id,
                    'minecraft_username' => $username,
                    'minecraft_uuid' => null,
                    'verification_status' => 'pending',
                    'updated_at' => $now,
                ],
                ['id' => (int) $existing['id']],
                ['%d', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            return true;
        }

        $wpdb->insert(
            $mc_table,
            [
                'wp_user_id' => $user_id,
                'microsoft_account_id' => $microsoft_account_id,
                'minecraft_username' => $username,
                'minecraft_uuid' => null,
                'verification_status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        return true;
    }


    public static function render_profile_shortcode(): string {
        if (!is_user_logged_in()) {
            return '<p>No has iniciado sesión.</p>' . self::render_login_shortcode();
        }

        global $wpdb;

        $user_id = get_current_user_id();
        $table = $wpdb->prefix . self::TABLE_NAME;

        $account = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE wp_user_id = %d LIMIT 1", $user_id),
            ARRAY_A
        );

        if (!$account) {
            return '<p>Tu cuenta WordPress no está vinculada todavía a Microsoft.</p>' . self::render_login_shortcode();
        }

        $html = '<div class="solidario-microsoft-profile">';
        $html .= '<h2>Mi cuenta Minecraft</h2>';
        $html .= '<p><strong>Estado Microsoft:</strong> cuenta vinculada correctamente.</p>';
        $html .= '<table>';
        $html .= '<tr><th>Nombre</th><td>' . esc_html($account['display_name']) . '</td></tr>';
        $html .= '<tr><th>Email</th><td>' . esc_html($account['email']) . '</td></tr>';
        $html .= '<tr><th>Usuario WordPress técnico</th><td>#' . esc_html((string) $account['wp_user_id']) . '</td></tr>';
        $html .= '<tr><th>Microsoft OID</th><td><code>' . esc_html($account['microsoft_oid']) . '</code></td></tr>';
        $minecraft_account = self::get_minecraft_account_for_user($user_id);

        if (!empty($_GET['minecraft_saved'])) {
            $html .= '<p><strong>Nombre Minecraft guardado correctamente.</strong></p>';
        }

        if (!empty($_GET['minecraft_error'])) {
            $html .= '<p><strong>Error:</strong> no se pudo guardar el nombre Minecraft.</p>';
        }

        if ($minecraft_account) {
            $html .= '<tr><th>Usuario Minecraft</th><td>' . esc_html($minecraft_account['minecraft_username']) . '</td></tr>';
            $html .= '<tr><th>Estado Minecraft</th><td>' . esc_html($minecraft_account['verification_status']) . '</td></tr>';
        } else {
            $html .= '<tr><th>Estado Minecraft</th><td>No vinculado todavía</td></tr>';
        }
        $html .= '</table>';
        $current_username = $minecraft_account['minecraft_username'] ?? '';

                $html .= '<h3>Conexión Xbox / Minecraft</h3>';
        $html .= '<p>Conecta tu cuenta Xbox/Minecraft para intentar verificar automáticamente tu perfil Minecraft.</p>';
        $html .= '<form method="get" action="' . esc_url(home_url('/wp-admin/admin-post.php')) . '">';
        $html .= '<input type="hidden" name="action" value="sml_xbox_start">';
        $html .= '<p><button type="submit">Conectar Xbox / Minecraft</button></p>';
        $html .= '</form>';

        $html .= '<form method="post" action="' . esc_url(home_url('/wp-admin/admin-post.php')) . '">';
        $html .= '<input type="hidden" name="action" value="sml_test_minecraft_profile">';
        $html .= wp_nonce_field('sml_test_minecraft_profile', 'sml_test_minecraft_profile_nonce', true, false);
        $html .= '<p><button type="submit">Probar perfil Minecraft</button></p>';
        $html .= '</form>';

        if (!empty($_GET['xbox_connected'])) {
            $html .= '<p><strong>Xbox conectado:</strong> token Xbox guardado para diagnóstico Minecraft.</p>';
        }

        $html .= '<h3>Vincular usuario Minecraft</h3>';
        $html .= '<form method="post">';
        $html .= '<input type="hidden" name="sml_action" value="save_minecraft_username">';
        $html .= wp_nonce_field('sml_save_minecraft_username', 'sml_minecraft_nonce', true, false);
        $html .= '<p><label>Nombre de usuario Minecraft<br>';
        $html .= '<input type="text" name="minecraft_username" value="' . esc_attr($current_username) . '" pattern="[A-Za-z0-9_]{3,16}" maxlength="16" required>';
        $html .= '</label></p>';
        $html .= '<p><button type="submit">Guardar usuario Minecraft</button></p>';
        $html .= '</form>';
        $html .= '<p>Estado inicial: pendiente de verificación.</p>';
        $html .= '<p><a href="' . esc_url(home_url('/salir/')) . '">Cerrar sesión</a></p>';
        $html .= '</div>';

        return $html;
    }




    public static function handle_test_minecraft_profile(): void {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/entrar/'));
            exit;
        }

        if (
            empty($_POST['sml_test_minecraft_profile_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['sml_test_minecraft_profile_nonce'])),
                'sml_test_minecraft_profile'
            )
        ) {
            wp_die('Nonce inválido.', 'Minecraft Profile Test', ['response' => 403]);
        }

        $user_id = get_current_user_id();
        $account = self::get_microsoft_account_for_user($user_id);

        if (!$account || empty($account['id'])) {
            wp_die('No hay cuenta Microsoft vinculada.', 'Minecraft Profile Test', ['response' => 400]);
        }

        self::upsert_minecraft_verification((int) $account['id'], [
            'microsoft_ok' => 1,
            'xbox_ok' => 0,
            'xsts_ok' => 0,
            'minecraft_ok' => 0,
            'last_error' => 'DEBUG: handle_test_minecraft_profile ejecutado.',
            'raw_response_json' => null,
            'last_checked_at' => current_time('mysql'),
        ]);

        $result = self::run_minecraft_profile_test((int) $account['id'], $user_id);

        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg('minecraft_profile_error', rawurlencode($result->get_error_code()), home_url('/mi-cuenta-minecraft/')));
            exit;
        }

        wp_safe_redirect(add_query_arg('minecraft_profile_tested', '1', home_url('/mi-cuenta-minecraft/')));
        exit;
    }

    public static function run_minecraft_profile_test(int $microsoft_account_id, int $user_id) {
        global $wpdb;

        $verification_table = $wpdb->prefix . 'solidario_minecraft_verification';
        $xbox_table = $wpdb->prefix . self::XBOX_TOKEN_TABLE_NAME;

        $now = current_time('mysql');

        $xbox_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $xbox_table WHERE microsoft_account_id = %d LIMIT 1",
                $microsoft_account_id
            ),
            ARRAY_A
        );

        if (!$xbox_row || empty($xbox_row['access_token'])) {
            self::upsert_minecraft_verification($microsoft_account_id, [
                'microsoft_ok' => 1,
                'xbox_ok' => 0,
                'xsts_ok' => 0,
                'minecraft_ok' => 0,
                'last_error' => 'No hay token Xbox OAuth guardado.',
                'raw_response_json' => null,
                'last_checked_at' => $now,
            ]);
            return new WP_Error('no_xbox_token', 'No hay token Xbox OAuth guardado.');
        }

        $oauth_token = self::sml_lab_decrypt($xbox_row['access_token']);
        if (!$oauth_token) {
            self::upsert_minecraft_verification($microsoft_account_id, [
                'microsoft_ok' => 1,
                'xbox_ok' => 0,
                'xsts_ok' => 0,
                'minecraft_ok' => 0,
                'last_error' => 'No se pudo desencriptar el token Xbox OAuth.',
                'raw_response_json' => null,
                'last_checked_at' => $now,
            ]);
            return new WP_Error('xbox_token_decrypt_failed', 'No se pudo desencriptar el token Xbox OAuth.');
        }

        $xbl = self::minecraft_xbl_authenticate($oauth_token);
        if (is_wp_error($xbl)) {
            self::upsert_minecraft_verification($microsoft_account_id, [
                'microsoft_ok' => 1,
                'xbox_ok' => 0,
                'xsts_ok' => 0,
                'minecraft_ok' => 0,
                'last_error' => $xbl->get_error_message(),
                'raw_response_json' => null,
                'last_checked_at' => $now,
            ]);
            return $xbl;
        }

        $xbl_token = $xbl['Token'] ?? '';
        $uhs = $xbl['DisplayClaims']['xui'][0]['uhs'] ?? '';

        $xsts = self::minecraft_xsts_authorize($xbl_token);
        if (is_wp_error($xsts)) {
            self::upsert_minecraft_verification($microsoft_account_id, [
                'microsoft_ok' => 1,
                'xbox_ok' => 1,
                'xsts_ok' => 0,
                'minecraft_ok' => 0,
                'last_error' => $xsts->get_error_message(),
                'raw_response_json' => wp_json_encode(['xbl' => $xbl]),
                'last_checked_at' => $now,
            ]);
            return $xsts;
        }

        $xsts_token = $xsts['Token'] ?? '';
        $uhs = $xsts['DisplayClaims']['xui'][0]['uhs'] ?? $uhs;

        $mc_login = self::minecraft_login_with_xbox($uhs, $xsts_token);
        if (is_wp_error($mc_login)) {
            self::upsert_minecraft_verification($microsoft_account_id, [
                'microsoft_ok' => 1,
                'xbox_ok' => 1,
                'xsts_ok' => 1,
                'minecraft_ok' => 0,
                'last_error' => $mc_login->get_error_message(),
                'raw_response_json' => wp_json_encode(['xbl' => $xbl, 'xsts' => $xsts]),
                'last_checked_at' => $now,
            ]);
            return $mc_login;
        }

        $minecraft_access_token = $mc_login['access_token'] ?? '';
        $profile = self::minecraft_get_profile($minecraft_access_token);

        if (is_wp_error($profile)) {
            self::upsert_minecraft_verification($microsoft_account_id, [
                'microsoft_ok' => 1,
                'xbox_ok' => 1,
                'xsts_ok' => 1,
                'minecraft_ok' => 0,
                'last_error' => $profile->get_error_message(),
                'raw_response_json' => wp_json_encode(['xbl' => $xbl, 'xsts' => $xsts, 'mc_login' => $mc_login]),
                'last_checked_at' => $now,
            ]);
            return $profile;
        }

        $username = sanitize_text_field($profile['name'] ?? '');
        $uuid = sanitize_text_field($profile['id'] ?? '');

        self::upsert_minecraft_verification($microsoft_account_id, [
            'microsoft_ok' => 1,
            'xbox_ok' => 1,
            'xsts_ok' => 1,
            'minecraft_ok' => 1,
            'minecraft_username' => $username,
            'minecraft_uuid' => $uuid,
            'last_error' => null,
            'raw_response_json' => wp_json_encode(['profile' => $profile]),
            'last_checked_at' => $now,
        ]);

        self::upsert_verified_minecraft_account($user_id, $microsoft_account_id, $username, $uuid);

        return $profile;
    }

    public static function minecraft_xbl_authenticate(string $oauth_token) {
        $response = wp_remote_post('https://user.auth.xboxlive.com/user/authenticate', [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode([
                'Properties' => [
                    'AuthMethod' => 'RPS',
                    'SiteName' => 'user.auth.xboxlive.com',
                    'RpsTicket' => 'd=' . $oauth_token,
                ],
                'RelyingParty' => 'http://auth.xboxlive.com',
                'TokenType' => 'JWT',
            ]),
        ]);

        return self::json_response_or_error($response, 'xbl_auth_failed');
    }

    public static function minecraft_xsts_authorize(string $xbl_token) {
        $response = wp_remote_post('https://xsts.auth.xboxlive.com/xsts/authorize', [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode([
                'Properties' => [
                    'SandboxId' => 'RETAIL',
                    'UserTokens' => [$xbl_token],
                ],
                'RelyingParty' => 'rp://api.minecraftservices.com/',
                'TokenType' => 'JWT',
            ]),
        ]);

        return self::json_response_or_error($response, 'xsts_auth_failed');
    }

    public static function minecraft_login_with_xbox(string $uhs, string $xsts_token) {
        $response = wp_remote_post('https://api.minecraftservices.com/authentication/login_with_xbox', [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode([
                'identityToken' => 'XBL3.0 x=' . $uhs . ';' . $xsts_token,
            ]),
        ]);

        return self::json_response_or_error($response, 'minecraft_login_failed');
    }

    public static function minecraft_get_profile(string $minecraft_access_token) {
        $response = wp_remote_get('https://api.minecraftservices.com/minecraft/profile', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $minecraft_access_token,
                'Accept' => 'application/json',
            ],
        ]);

        return self::json_response_or_error($response, 'minecraft_profile_failed');
    }

    public static function json_response_or_error($response, string $code) {
        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            return new WP_Error($code, 'HTTP ' . $status . "\n\n" . $body);
        }

        if (!is_array($json)) {
            return new WP_Error($code . '_json', 'Respuesta JSON inválida: ' . $body);
        }

        return $json;
    }

    public static function upsert_minecraft_verification(int $microsoft_account_id, array $data): void {
        global $wpdb;

        $table = $wpdb->prefix . 'solidario_minecraft_verification';
        $now = current_time('mysql');

        $existing_id = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE microsoft_account_id = %d LIMIT 1", $microsoft_account_id)
        );

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

        $data = array_merge($defaults, $data);

        if ($existing_id) {
            $wpdb->update($table, $data, ['id' => (int) $existing_id]);
            return;
        }

        $data['microsoft_account_id'] = $microsoft_account_id;
        $data['created_at'] = $now;

        $wpdb->insert($table, $data);
    }

    public static function upsert_verified_minecraft_account(int $user_id, int $microsoft_account_id, string $username, string $uuid): void {
        global $wpdb;

        if (!$username || !$uuid) {
            return;
        }

        $table = $wpdb->prefix . self::MC_TABLE_NAME;
        $now = current_time('mysql');

        $existing_id = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE wp_user_id = %d LIMIT 1", $user_id)
        );

        $data = [
            'wp_user_id' => $user_id,
            'microsoft_account_id' => $microsoft_account_id,
            'minecraft_username' => $username,
            'minecraft_uuid' => $uuid,
            'verification_status' => 'verified',
            'updated_at' => $now,
        ];

        if ($existing_id) {
            $wpdb->update($table, $data, ['id' => (int) $existing_id]);
            return;
        }

        $data['created_at'] = $now;
        $wpdb->insert($table, $data);
    }

    public static function sml_lab_decrypt(string $value): ?string {
        if ($value === '') {
            return null;
        }

        if (strpos($value, 'smlxbox:v1:') !== 0) {
            return $value;
        }

        $raw = base64_decode(substr($value, strlen('smlxbox:v1:')));
        if ($raw === false || strlen($raw) <= 16) {
            return null;
        }

        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);

        $key_source = defined('AUTH_KEY') ? AUTH_KEY : wp_salt('auth');
        $key = hash('sha256', $key_source, true);

        $plain = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return $plain === false ? null : $plain;
    }


    public static function render_logout_shortcode(): string {
        if (is_user_logged_in()) {
            wp_logout();
        }

        wp_safe_redirect(home_url('/entrar/'));
        exit;
    }


    public static function render_login_shortcode(): string {
        $s = self::settings();

        if (empty($s['client_id'])) {
            return '<p>Login Microsoft no configurado.</p>';
        }

        return '<p><a class="button" href="' . esc_url(self::build_login_url()) . '">Entrar con Microsoft</a></p>';
    }
}

Solidario_Microsoft_Login::init();
