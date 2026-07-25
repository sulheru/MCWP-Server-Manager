<?php
/**
 * Plugin Name: Gestor MC - Gestión de Usuarios
 * Description: Módulo de Gestor MC para administrar usuarios de Minecraft, whitelist, estados de acceso y modos de juego.
 * Version: 0.7.0
 * Author: OptiGrid IT
 * Author URI: https://optigrid-it.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gestor-mc-users
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Update URI: false
 */

if (!defined('ABSPATH')) exit;

class Solidario_MC_Access {
    const TABLE = 'solidario_mc_access';
    const GAMEMODES = ['survival', 'creative', 'adventure', 'spectator'];

    public function __construct() {
        add_action('init', [$this, 'maybe_migrate']);

        add_action('show_user_profile', [$this, 'render_profile_fields']);
        add_action('edit_user_profile', [$this, 'render_profile_fields']);
        add_action('personal_options_update', [$this, 'save_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_profile_fields']);

        add_action('admin_menu', [$this, 'admin_menu'], 20);
add_action('admin_post_solidario_mc_save_all', [$this, 'handle_save_all']);
        add_action('admin_post_solidario_mc_sync_all', [$this, 'handle_sync_all']);

        add_shortcode('solidario_mc_access_status', [$this, 'shortcode_status']);
    }

    private function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    public function maybe_migrate() {
        global $wpdb;
        $table = $this->table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            user_id BIGINT(20) UNSIGNED NOT NULL,
            minecraft_uuid CHAR(36) NULL,
            minecraft_username VARCHAR(32) NULL,
            gamemode VARCHAR(16) NOT NULL DEFAULT 'survival',
            gamemode_status VARCHAR(16) NOT NULL DEFAULT 'none',
            gamemode_message TEXT NULL,
            gamemode_updated_at DATETIME NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            is_blacklisted TINYINT(1) NOT NULL DEFAULT 0,
            banned_until DATETIME NULL,
            ban_status VARCHAR(20) NOT NULL DEFAULT 'none',
            ban_message TEXT NULL,
            ban_updated_at DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            KEY minecraft_username_idx (minecraft_username)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private function gateway_url() {
        return rtrim(getenv('SOLIDARIO_MC_GATEWAY_URL') ?: 'http://minecraft-gateway:8080', '/');
    }

    private function gateway_post($endpoint, $payload) {
        $response = wp_remote_post(
            $this->gateway_url() . $endpoint,
            [
                'timeout' => 5,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => wp_json_encode($payload),
            ]
        );

        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);
        $message = is_array($body) && isset($body['result']) ? $body['result'] : $body_raw;

        if ($code < 200 || $code >= 300) {
            return ['ok' => false, 'message' => $message];
        }

        if (stripos($message, 'does not exist') !== false || stripos($message, 'no player was found') !== false) {
            return ['ok' => false, 'message' => $message];
        }

        return ['ok' => true, 'message' => $message, 'body' => $body];
    }

    private function normalize_mc_username($value) {
        $value = trim(sanitize_text_field($value));
        if ($value === '') return '';
        if (!preg_match('/^[A-Za-z0-9_]{3,16}$/', $value)) return false;
        return $value;
    }

    private function normalize_gamemode($value) {
        $value = sanitize_key($value);
        return in_array($value, self::GAMEMODES, true) ? $value : 'survival';
    }

    private function normalize_banned_until($value) {
        $value = trim(sanitize_text_field($value));
        if ($value === '') return null;

        $local = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, wp_timezone());
        $errors = DateTimeImmutable::getLastErrors();
        if (!$local || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return false;
        }

        return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function banned_until_for_input($value) {
        if (empty($value)) return '';
        try {
            $utc = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $utc->setTimezone(wp_timezone())->format('Y-m-d\TH:i');
        } catch (Exception $e) {
            return '';
        }
    }

    private function banned_until_for_display($value) {
        if (empty($value)) return '-';
        try {
            $utc = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $utc->setTimezone(wp_timezone())->format('Y-m-d H:i');
        } catch (Exception $e) {
            return '-';
        }
    }

    private function get_access_row($user_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name()} WHERE user_id=%d", (int)$user_id),
            ARRAY_A
        ) ?: [];
    }

    private function username_exists_elsewhere($username, $user_id) {
        if ($username === '') return false;
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$this->table_name()} WHERE minecraft_username=%s AND user_id<>%d LIMIT 1",
            $username,
            (int)$user_id
        ));
        return !empty($found);
    }

    private function upsert($user_id, $uuid, $username, $active, $gamemode = 'survival', $discipline = null) {
        global $wpdb;
        $table = $this->table_name();

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id=%d",
            (int)$user_id
        ));

        $data = [
            'minecraft_uuid' => $uuid !== '' ? $uuid : null,
            'minecraft_username' => $username !== '' ? $username : null,
            'gamemode' => $this->normalize_gamemode($gamemode),
            'is_active' => (int)$active,
            'updated_at' => current_time('mysql'),
        ];
        $formats = ['%s','%s','%s','%d','%s'];

        if (is_array($discipline)) {
            $data['is_blacklisted'] = !empty($discipline['is_blacklisted']) ? 1 : 0;
            $data['banned_until'] = $discipline['banned_until'] ?: null;
            $formats[] = '%d';
            $formats[] = '%s';
        }

        if ($exists) {
            return $wpdb->update($table, $data, ['user_id' => (int)$user_id], $formats, ['%d']);
        }

        $data['user_id'] = (int)$user_id;
        $formats[] = '%d';
        return $wpdb->insert($table, $data, $formats);
    }

    private function sync_user_whitelist($user_id) {
        $row = $this->get_access_row($user_id);
        $username = $row['minecraft_username'] ?? '';

        if ($username === '') {
            return ['ok' => false, 'message' => 'Usuario sin nombre Minecraft'];
        }

        $endpoint = !empty($row['is_active']) ? '/whitelist/add' : '/whitelist/remove';
        return $this->gateway_post($endpoint, ['username' => $username]);
    }

    private function sync_user_gamemode($user_id) {
        $row = $this->get_access_row($user_id);
        $username = $row['minecraft_username'] ?? '';
        $gamemode = $this->normalize_gamemode($row['gamemode'] ?? 'survival');

        if ($username === '') {
            return ['ok' => false, 'message' => 'Usuario sin nombre Minecraft'];
        }

        return $this->gateway_post('/player/gamemode', [
            'username' => $username,
            'gamemode' => $gamemode,
        ]);
    }

    private function temporary_ban_is_active($banned_until) {
        if (empty($banned_until)) return false;

        try {
            $until = new DateTimeImmutable($banned_until, new DateTimeZone('UTC'));
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            return $until > $now;
        } catch (Exception $e) {
            return false;
        }
    }

    private function update_ban_status($user_id, $status, $message) {
        global $wpdb;

        $wpdb->update(
            $this->table_name(),
            [
                'ban_status' => sanitize_key($status),
                'ban_message' => sanitize_textarea_field((string)$message),
                'ban_updated_at' => current_time('mysql'),
            ],
            ['user_id' => (int)$user_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    private function apply_blacklist_change($user_id, $enabled) {
        $row = $this->get_access_row($user_id);
        $username = $row['minecraft_username'] ?? '';

        if ($username === '') {
            $result = ['ok' => false, 'message' => 'Usuario sin nombre Minecraft'];
            $this->update_ban_status($user_id, 'error', $result['message']);
            return $result;
        }

        if ($enabled) {
            $ban = $this->gateway_post('/player/ban', [
                'username' => $username,
                'reason' => 'Blacklist administrativa',
            ]);

            $remove = $this->gateway_post('/whitelist/remove', [
                'username' => $username,
            ]);

            $ok = !empty($ban['ok']) && !empty($remove['ok']);
            $message = 'Ban: '.($ban['message'] ?? 'sin respuesta')
                .' | Whitelist remove: '.($remove['message'] ?? 'sin respuesta');

            $this->update_ban_status($user_id, $ok ? 'applied' : 'error', $message);
            return ['ok' => $ok, 'message' => $message];
        }

        if ($this->temporary_ban_is_active($row['banned_until'] ?? null)) {
            $message = 'Blacklist retirada. Se conserva el baneo temporal vigente.';
            $this->update_ban_status($user_id, 'temporary', $message);
            return ['ok' => true, 'message' => $message];
        }

        $pardon = $this->gateway_post('/player/pardon', [
            'username' => $username,
        ]);

        $whitelist = !empty($row['is_active'])
            ? $this->gateway_post('/whitelist/add', ['username' => $username])
            : $this->gateway_post('/whitelist/remove', ['username' => $username]);

        $ok = !empty($pardon['ok']) && !empty($whitelist['ok']);
        $message = 'Pardon: '.($pardon['message'] ?? 'sin respuesta')
            .' | Whitelist: '.($whitelist['message'] ?? 'sin respuesta');

        $this->update_ban_status($user_id, $ok ? 'none' : 'error', $message);
        return ['ok' => $ok, 'message' => $message];
    }

    public function render_profile_fields($user) {
        $row = $this->get_access_row($user->ID);
        $uuid = esc_attr($row['minecraft_uuid'] ?? '');
        $username = esc_attr($row['minecraft_username'] ?? '');
        $active = !empty($row['is_active']);
        $gamemode = $this->normalize_gamemode($row['gamemode'] ?? 'survival');
        $blacklisted = !empty($row['is_blacklisted']);
        $banned_until = esc_attr($this->banned_until_for_input($row['banned_until'] ?? null));

        echo '<h3>Solidario Minecraft Access</h3><table class="form-table">';
        echo '<tr><th><label for="solidario_mc_username">Minecraft username</label></th><td>';
        echo '<input type="text" name="solidario_mc_username" id="solidario_mc_username" value="'.$username.'" class="regular-text" />';
        echo '<p class="description">Letras, números y guion bajo. 3-16 caracteres.</p></td></tr>';

        echo '<tr><th><label for="solidario_mc_uuid">Minecraft UUID</label></th><td>';
        echo '<input type="text" name="solidario_mc_uuid" id="solidario_mc_uuid" value="'.$uuid.'" class="regular-text" /></td></tr>';

        echo '<tr><th><label for="solidario_mc_gamemode">Gamemode</label></th><td>';
        echo '<select name="solidario_mc_gamemode" id="solidario_mc_gamemode">';
        foreach (self::GAMEMODES as $mode) {
            echo '<option value="'.esc_attr($mode).'" '.selected($gamemode, $mode, false).'>'.esc_html($mode).'</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th>Acceso MC</th><td><label>';
        echo '<input type="checkbox" name="solidario_mc_active" value="1" '.checked($active, true, false).' /> Activo';
        echo '</label></td></tr>';

        echo '<tr><th>Blacklist</th><td><label>';
        echo '<input type="checkbox" name="solidario_mc_blacklisted" value="1" '.checked($blacklisted, true, false).' /> Bloqueo permanente';
        echo '</label><p class="description">Se aplica inmediatamente mediante el Gateway. La base de datos continúa siendo la fuente de verdad.</p></td></tr>';

        echo '<tr><th><label for="solidario_mc_banned_until">Baneado hasta</label></th><td>';
        echo '<input type="datetime-local" name="solidario_mc_banned_until" id="solidario_mc_banned_until" value="'.$banned_until.'" />';
        echo '<p class="description">Baneo temporal. Déjalo vacío para eliminarlo. Su aplicación automática corresponde a la siguiente entrega.</p></td></tr>';
        echo '</table>';
    }

    public function save_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) return false;

        $before = $this->get_access_row($user_id);
        $was_blacklisted = !empty($before['is_blacklisted']);

        $username = $this->normalize_mc_username($_POST['solidario_mc_username'] ?? '');
        if ($username === false) return false;
        if ($this->username_exists_elsewhere($username, $user_id)) return false;

        $uuid = sanitize_text_field($_POST['solidario_mc_uuid'] ?? '');
        $active = isset($_POST['solidario_mc_active']) ? 1 : 0;
        $gamemode = $this->normalize_gamemode($_POST['solidario_mc_gamemode'] ?? 'survival');
        $blacklisted = isset($_POST['solidario_mc_blacklisted']) ? 1 : 0;
        $banned_until = $this->normalize_banned_until($_POST['solidario_mc_banned_until'] ?? '');
        if ($banned_until === false) return false;

        $saved = $this->upsert($user_id, $uuid, $username, $active, $gamemode, [
            'is_blacklisted' => $blacklisted,
            'banned_until' => $banned_until,
        ]);

        if ($saved === false) return false;

        if ($was_blacklisted !== (bool)$blacklisted) {
            $this->update_ban_status(
                $user_id,
                'pending',
                $blacklisted
                    ? 'Blacklist guardada. Aplicación inmediata pendiente.'
                    : 'Retirada de blacklist guardada. Aplicación inmediata pendiente.'
            );
            $this->apply_blacklist_change($user_id, (bool)$blacklisted);
        }

        return true;
    }

    public function admin_menu() {
        add_submenu_page(
            'gestor-mc-srv',
            'Gestión de Usuarios',
            'Gestión de Usuarios',
            'manage_options',
            'solidario-mc-access',
            [$this, 'admin_page']
        );
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) return;

        global $wpdb;
        $filter = sanitize_key($_GET['mc_filter'] ?? 'all');

        $where = 'WHERE 1=1';
        if ($filter === 'with_mc') $where .= " AND a.minecraft_username IS NOT NULL AND a.minecraft_username <> ''";
        if ($filter === 'without_mc') $where .= " AND (a.minecraft_username IS NULL OR a.minecraft_username = '')";
        if ($filter === 'active') $where .= " AND a.is_active = 1";
        if ($filter === 'inactive') $where .= " AND (a.is_active IS NULL OR a.is_active = 0)";

        $ms_table = $wpdb->prefix . 'solidario_microsoft_accounts';

        $rows = $wpdb->get_results("
            SELECT u.ID, u.user_login, u.user_email, u.display_name,
                   ms.email AS microsoft_email,
                   ms.display_name AS microsoft_display_name,
                   ms.last_login_at AS microsoft_last_login_at,
                   a.minecraft_uuid, a.minecraft_username, a.gamemode,
                   a.gamemode_status, a.gamemode_message, a.gamemode_updated_at,
                   a.is_active, a.is_blacklisted, a.banned_until,
                   a.ban_status, a.ban_message, a.ban_updated_at, a.updated_at
            FROM {$ms_table} ms
            JOIN {$wpdb->users} u ON u.ID = ms.wp_user_id
            LEFT JOIN {$this->table_name()} a ON a.user_id = u.ID
            {$where}
            ORDER BY u.ID ASC
        ", ARRAY_A);

        echo '<div class="wrap"><h1>MC Users</h1>';

        if (isset($_GET['updated'])) echo '<div class="notice notice-success"><p>Cambios guardados.</p></div>';
        if (isset($_GET['sync_ok'])) echo '<div class="notice notice-success"><p>Sync whitelist: '.esc_html($_GET['sync_ok']).'</p></div>';
        if (isset($_GET['sync_error'])) echo '<div class="notice notice-error"><p>Sync whitelist error: '.esc_html($_GET['sync_error']).'</p></div>';
        if (isset($_GET['error'])) echo '<div class="notice notice-error"><p>'.esc_html($_GET['error']).'</p></div>';

        $filters = [
            'all' => 'Todos',
            'with_mc' => 'Con nombre MC',
            'without_mc' => 'Sin nombre MC',
            'active' => 'Activos',
            'inactive' => 'Inactivos',
        ];

        echo '<p class="subsubsub">';
        $links = [];
        foreach ($filters as $key => $label) {
            $class = $filter === $key ? ' class="current"' : '';
            $url = admin_url('admin.php?page=solidario-mc-access&mc_filter='.$key);
            $links[] = '<a'.$class.' href="'.esc_url($url).'">'.esc_html($label).'</a>';
        }
        echo implode(' | ', $links);
        echo '</p><br class="clear"/>';

        echo '<p><input type="text" id="solidario-mc-search" class="regular-text" placeholder="Buscar / regex en filas..." style="width:420px; max-width:100%;" /></p>';

        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin-right:8px;">';
        echo '<input type="hidden" name="action" value="solidario_mc_sync_all" />';
        wp_nonce_field('solidario_mc_sync_all_nonce', 'nonce');
        submit_button('Sincronizar whitelist', 'secondary', 'submit', false);
        echo '</form>';

        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="solidario_mc_save_all" />';
        wp_nonce_field('solidario_mc_save_all_nonce', 'nonce');

        submit_button('Guardar todos los cambios', 'primary', 'submit', false);

        echo '<table id="solidario-mc-table" class="wp-list-table widefat fixed striped users" style="margin-top:12px;">';
        echo '<thead><tr>';
        echo '<th>WP User</th><th>Microsoft</th><th>Activo</th><th>Gamemode</th><th>Gamemode status</th><th>Restricción</th><th>Baneado hasta</th><th>Actualizado</th>';
        echo '</tr></thead><tbody>';

        if (!$rows) {
            echo '<tr><td colspan="8">No hay usuarios.</td></tr>';
        }

        foreach ($rows as $row) {
            $user_id = (int)$row['ID'];
            $active = !empty($row['is_active']);
            $gamemode = $this->normalize_gamemode($row['gamemode'] ?? 'survival');
            $blacklisted = !empty($row['is_blacklisted']);
            $banned_until_display = $this->banned_until_for_display($row['banned_until'] ?? null);
            $restriction = $blacklisted ? 'Blacklist' : (!empty($row['banned_until']) ? 'Baneo temporal' : 'Sin restricciones');

            $search = strtolower(
                ($row['user_login'] ?? '') . ' ' .
                ($row['user_email'] ?? '') . ' ' .
                ($row['microsoft_email'] ?? '') . ' ' .
                ($row['microsoft_display_name'] ?? '') . ' ' .
                ($row['minecraft_username'] ?? '') . ' ' .
                ($row['minecraft_uuid'] ?? '') . ' ' .
                ($active ? 'activo active' : 'inactivo inactive') . ' ' .
                $gamemode
            );

            echo '<tr data-search="'.esc_attr($search).'">';
            echo '<td><strong><a href="'.esc_url(get_edit_user_link($user_id)).'">'.esc_html($row['user_login']).'</a></strong><br/><span>ID: '.esc_html($user_id).'</span></td>';
            echo '<td>'.esc_html($row['microsoft_email'] ?: $row['user_email']).'<br/><small>Último login MS: '.esc_html($row['microsoft_last_login_at'] ?? '-').'</small></td>';
            echo '<td><label><input type="checkbox" name="users['.$user_id.'][is_active]" value="1" '.checked($active, true, false).' /> Activo</label></td>';
            $gm_status = esc_html($row['gamemode_status'] ?? 'none');
            $gm_message = esc_html($row['gamemode_message'] ?? '');
            $gm_updated = esc_html($row['gamemode_updated_at'] ?? '');

            echo '<td><select name="users['.$user_id.'][gamemode]">';
            foreach (self::GAMEMODES as $mode) {
                echo '<option value="'.esc_attr($mode).'" '.selected($gamemode, $mode, false).'>'.esc_html($mode).'</option>';
            }
            echo '</select></td>';
            echo '<td><strong>'.$gm_status.'</strong><br/><small>'.$gm_message.'</small><br/><small>'.$gm_updated.'</small></td>';
            echo '<td><strong>'.esc_html($restriction).'</strong></td>';
            echo '<td>'.esc_html($banned_until_display).'</td>';
            echo '<td>'.esc_html($row['updated_at'] ?? '-').'</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<p style="margin-top:12px;">';
        submit_button('Guardar todos los cambios', 'primary', 'submit', false);
        echo '</p>';

        echo '</form>';

        echo '<script>
        (function(){
            const input = document.getElementById("solidario-mc-search");
            const rows = Array.from(document.querySelectorAll("#solidario-mc-table tbody tr[data-search]"));
            if (!input) return;

            input.addEventListener("input", function(){
                const q = input.value.trim();
                let re = null;
                let regexOk = true;

                if (q.length > 0) {
                    try {
                        re = new RegExp(q, "i");
                    } catch(e) {
                        regexOk = false;
                    }
                }

                rows.forEach(function(row){
                    const text = row.getAttribute("data-search") || "";
                    let show = true;

                    if (q.length > 0) {
                        show = regexOk ? re.test(text) : text.includes(q.toLowerCase());
                    }

                    row.style.display = show ? "" : "none";
                });
            });
        })();
        </script>';

        echo '</div>';
    }



    private function mark_gamemode_saved($user_id) {
        global $wpdb;
        $table = $this->table_name();

        $wpdb->update(
            $table,
            [
                'gamemode_status' => 'saved',
                'gamemode_message' => 'Guardado en WordPress. Pendiente de sincronización.',
                'gamemode_updated_at' => current_time('mysql'),
            ],
            ['user_id' => (int)$user_id],
            ['%s','%s','%s'],
            ['%d']
        );
    }

    private function update_gamemode_status($user_id, $result) {
        global $wpdb;
        $table = $this->table_name();

        $status = 'error';
        $message = $result['message'] ?? '';

        if (!empty($result['ok'])) {
            $body = $result['body'] ?? [];

            if (is_array($body) && array_key_exists('applied_now', $body)) {
                $status = !empty($body['applied_now']) ? 'done' : 'pending';
                $message = $body['result'] ?? $message;
            } else {
                $status = 'done';
            }
        }

        $wpdb->update(
            $table,
            [
                'gamemode_status' => $status,
                'gamemode_message' => $message,
                'gamemode_updated_at' => current_time('mysql'),
            ],
            ['user_id' => (int)$user_id],
            ['%s','%s','%s'],
            ['%d']
        );
    }

    public function handle_save_all() {
        if (!current_user_can('manage_options')) wp_die('no');
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'solidario_mc_save_all_nonce')) wp_die('bad nonce');

        $users = $_POST['users'] ?? [];
        $errors = [];

        foreach ($users as $user_id_raw => $data) {
            $user_id = (int)$user_id_raw;

            if ($user_id <= 0 || !get_user_by('id', $user_id)) {
                $errors[] = 'Usuario no válido: '.$user_id;
                continue;
            }

            $existing = $this->get_access_row($user_id);
            $username = $this->normalize_mc_username($existing['minecraft_username'] ?? '');
            if ($username === false) {
                $errors[] = 'Nombre MC almacenado no válido para user_id '.$user_id;
                continue;
            }
            $uuid = sanitize_text_field($existing['minecraft_uuid'] ?? '');
            $active = isset($data['is_active']) ? 1 : 0;
            $gamemode = $this->normalize_gamemode($data['gamemode'] ?? 'survival');

            $this->upsert($user_id, $uuid, $username, $active, $gamemode);

            if ($username !== '') {
                $this->mark_gamemode_saved($user_id);
            }
        }

        if (!empty($errors)) {
            wp_redirect(admin_url('admin.php?page=solidario-mc-access&error='.rawurlencode(implode(' | ', $errors))));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=solidario-mc-access&updated=1'));
        exit;
    }

    public function handle_sync_all() {
        if (!current_user_can('manage_options')) wp_die('no');
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'solidario_mc_sync_all_nonce')) wp_die('bad nonce');

        global $wpdb;
        $rows = $wpdb->get_results("
            SELECT user_id, minecraft_username
            FROM {$this->table_name()}
            WHERE minecraft_username IS NOT NULL AND minecraft_username <> ''
            ORDER BY user_id ASC
        ", ARRAY_A);

        $ok = 0;
        $errors = [];

        foreach ($rows as $row) {
            $user_id = (int)$row['user_id'];
            $username = $row['minecraft_username'] ?? '';

            $result = $this->sync_user_whitelist($user_id);

            if (!empty($result['ok'])) {
                $ok++;

                $access_row = $this->get_access_row($user_id);
                if (!empty($access_row['is_active'])) {
                    $gm_result = $this->sync_user_gamemode($user_id);
                    $this->update_gamemode_status($user_id, $gm_result);
                }
            } else {
                $errors[] = $username.': '.($result['message'] ?? 'error desconocido');
            }
        }

        if (!empty($errors)) {
            $msg = $ok.' OK | Errores: '.implode(' | ', $errors);
            wp_redirect(admin_url('admin.php?page=solidario-mc-access&sync_error='.rawurlencode($msg)));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=solidario-mc-access&sync_ok='.rawurlencode($ok.' usuarios sincronizados')));
        exit;
    }

    public function shortcode_status() {
        $user_id = get_current_user_id();
        if (!$user_id) return 'Not logged in.';

        $row = $this->get_access_row($user_id);
        $username = esc_html($row['minecraft_username'] ?? '');
        $uuid = esc_html($row['minecraft_uuid'] ?? '');
        $active = !empty($row['is_active']) ? 'YES' : 'NO';
        $gamemode = esc_html($row['gamemode'] ?? 'survival');

        return "<div><strong>MC access:</strong> {$active}<br/><strong>Username:</strong> {$username}<br/><strong>UUID:</strong> {$uuid}<br/><strong>Gamemode:</strong> {$gamemode}</div>";
    }
}

new Solidario_MC_Access();
