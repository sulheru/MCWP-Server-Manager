<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Updates;

final class GitHubUpdateAdminPage
{
    private const PAGE_SLUG = 'optigrid-github-updates';
    private const SAVE_ACTION = 'optigrid_save_github_update_settings';
    private const CHECK_ACTION = 'optigrid_check_github_updates';

    private GitHubUpdateSettings $settings;
    private GitHubPluginUpdater $updater;

    public function __construct(
        GitHubUpdateSettings $settings,
        GitHubPluginUpdater $updater
    ) {
        $this->settings = $settings;
        $this->updater = $updater;
    }

    public function registerHooks(): void
    {
        add_action(
            'admin_menu',
            [$this, 'registerMenu'],
            60
        );

        add_action(
            'admin_post_' . self::SAVE_ACTION,
            [$this, 'handleSave']
        );

        add_action(
            'admin_post_' . self::CHECK_ACTION,
            [$this, 'handleCheck']
        );
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'gestor-mc-srv',
            'Actualizaciones',
            'Actualizaciones',
            'update_plugins',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $this->requireCapability();

        $configuration = $this->settings->all();
        $statuses = $this->updater->statuses();

        $notice = isset($_GET['update_notice'])
            ? sanitize_key(wp_unslash($_GET['update_notice']))
            : '';

        $error = isset($_GET['update_error'])
            ? sanitize_text_field(
                rawurldecode(wp_unslash($_GET['update_error']))
            )
            : '';

        require MC_MANAGER_SERVER_PATH
            . 'templates/updates/github-settings.php';
    }

    public function handleSave(): void
    {
        $this->requireCapability();
        check_admin_referer('optigrid_save_github_update_settings');

        try {
            $this->settings->save($_POST);
            $this->settings->clearCache();

            $this->redirect(['update_notice' => 'saved']);
        } catch (\Throwable $exception) {
            $this->redirect(
                [
                    'update_error' => rawurlencode(
                        $exception->getMessage()
                    ),
                ]
            );
        }
    }

    public function handleCheck(): void
    {
        $this->requireCapability();
        check_admin_referer('optigrid_check_github_updates');

        $this->settings->clearCache();
        wp_update_plugins();

        $this->redirect(['update_notice' => 'checked']);
    }

    private function requireCapability(): void
    {
        if (!current_user_can('update_plugins')) {
            wp_die(
                esc_html__(
                    'No tienes permisos para gestionar actualizaciones.',
                    'mc-manager-server'
                )
            );
        }
    }

    private function redirect(array $args = []): void
    {
        wp_safe_redirect(
            add_query_arg(
                array_merge(
                    ['page' => self::PAGE_SLUG],
                    $args
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    public static function saveAction(): string
    {
        return self::SAVE_ACTION;
    }

    public static function checkAction(): string
    {
        return self::CHECK_ACTION;
    }
}
