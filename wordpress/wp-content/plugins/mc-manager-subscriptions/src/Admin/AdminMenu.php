<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra la integración del plugin en wp-admin.
 */
final class OptiGrid_Subscriptions_Admin_Menu
{
    private const PARENT_SLUG = 'gestor-mc-srv';
    private const PAGE_SLUG   = 'optigrid-subscriptions';

    private OptiGrid_Subscriptions_Admin_Page $admin_page;

    public function __construct(
        OptiGrid_Subscriptions_Admin_Page $admin_page
    ) {
        $this->admin_page = $admin_page;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu'], 40);
        add_action('admin_notices', [$this, 'maybe_show_dependency_notice']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            __('Suscripciones', 'optigrid-subscriptions'),
            __('Suscripciones', 'optigrid-subscriptions'),
            'manage_options',
            self::PAGE_SLUG,
            [$this->admin_page, 'render'],
            40
        );
    }

    public function enqueue_assets(string $hook_suffix): void
    {
        if (!$this->is_plugin_page_request()) {
            return;
        }

        wp_enqueue_style(
            'optigrid-subscriptions-admin',
            OPTIGRID_SUBSCRIPTIONS_URL . 'assets/css/admin.css',
            [],
            OPTIGRID_SUBSCRIPTIONS_VERSION
        );

        wp_enqueue_script(
            'optigrid-subscriptions-admin',
            OPTIGRID_SUBSCRIPTIONS_URL . 'assets/js/admin.js',
            [],
            OPTIGRID_SUBSCRIPTIONS_VERSION,
            true
        );
    }

    public function maybe_show_dependency_notice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!$this->is_plugin_page_request()) {
            return;
        }

        global $menu;

        if (!is_array($menu)) {
            return;
        }

        foreach ($menu as $item) {
            if (isset($item[2]) && $item[2] === self::PARENT_SLUG) {
                return;
            }
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__(
            'OptiGrid Subscriptions está activo, pero el menú Gestor MC-SRV no está disponible. Comprueba que MC Manager Server está activo.',
            'optigrid-subscriptions'
        );
        echo '</p></div>';
    }

    private function is_plugin_page_request(): bool
    {
        $page = isset($_GET['page'])
            ? sanitize_key(wp_unslash($_GET['page']))
            : '';

        return $page === self::PAGE_SLUG;
    }
}
