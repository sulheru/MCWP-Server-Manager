<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Core;

use OptiGrid\MCManagerServer\Contracts\ModuleInterface;

final class Dashboard
{
    private ModuleManager $moduleManager;
    private AssetManager $assetManager;

    public function __construct(ModuleManager $moduleManager, AssetManager $assetManager)
    {
        $this->moduleManager = $moduleManager;
        $this->assetManager = $assetManager;
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this->assetManager, 'enqueue']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('Gestor MC', 'mc-manager-server'),
            __('Gestor MC-SRV', 'mc-manager-server'),
            'manage_options',
            'gestor-mc-srv',
            [$this, 'renderDashboard'],
            'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAxMDI0IDEwMjQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZmlsbD0iYmxhY2siIGQ9Ik0xNzAuNjY2NjY3IDg1LjMzMzMzM2g2ODIuNjY2NjY2YTg1LjMzMzMzMyA4NS4zMzMzMzMgMCAwIDEgODUuMzMzMzM0IDg1LjMzMzMzNHY2ODIuNjY2NjY2YTg1LjMzMzMzMyA4NS4zMzMzMzMgMCAwIDEtODUuMzMzMzM0IDg1LjMzMzMzNEgxNzAuNjY2NjY3YTg1LjMzMzMzMyA4NS4zMzMzMzMgMCAwIDEtODUuMzMzMzM0LTg1LjMzMzMzNFYxNzAuNjY2NjY3YTg1LjMzMzMzMyA4NS4zMzMzMzMgMCAwIDEgODUuMzMzMzM0LTg1LjMzMzMzNG04NS4zMzMzMzMgMTcwLjY2NjY2N3YxNzAuNjY2NjY3aDE3MC42NjY2Njd2ODUuMzMzMzMzSDM0MS4zMzMzMzN2MjU2aDg1LjMzMzMzNHYtODUuMzMzMzMzaDE3MC42NjY2NjZ2ODUuMzMzMzMzaDg1LjMzMzMzNHYtMjU2aC04NS4zMzMzMzR2LTg1LjMzMzMzM2gxNzAuNjY2NjY3VjI1NmgtMTcwLjY2NjY2N3YxNzAuNjY2NjY3aC0xNzAuNjY2NjY2VjI1NkgyNTZ6Ii8+PC9zdmc+Cg==',
            58
        );

        add_submenu_page(
            'gestor-mc-srv',
            __('Dashboard', 'mc-manager-server'),
            __('Dashboard', 'mc-manager-server'),
            'manage_options',
            'gestor-mc-srv',
            [$this, 'renderDashboard']
        );
    }

    public function renderDashboard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('No tienes permisos para acceder a esta pantalla.', 'mc-manager-server'),
                esc_html__('Acceso denegado', 'mc-manager-server'),
                ['response' => 403]
            );
        }

        $modules = array_filter(
            $this->moduleManager->all(),
            fn (ModuleInterface $module): bool => $this->moduleManager->isAccessible($module)
        );

        $activeModule = $this->resolveActiveModule($modules);
        $template = MC_MANAGER_SERVER_PATH . 'templates/dashboard.php';

        if (!is_readable($template)) {
            wp_die(esc_html__('No se encuentra la plantilla del Dashboard.', 'mc-manager-server'));
        }

        /**
         * Contexto compartido para la plantilla Host.
         *
         * @var array<string, ModuleInterface> $modules
         * @var ModuleInterface|null $activeModule
         */
        require $template;
    }

    /**
     * @param array<string, ModuleInterface> $modules
     */
    private function resolveActiveModule(array $modules): ?ModuleInterface
    {
        if ($modules === []) {
            return null;
        }

        $requested = isset($_GET['module'])
            ? strtolower(trim(sanitize_text_field(wp_unslash((string) $_GET['module']))))
            : '';

        if ($requested !== '' && !$this->moduleManager->isValidId($requested)) {
            $requested = '';
        }

        if ($requested !== '' && isset($modules[$requested])) {
            return $modules[$requested];
        }

        return reset($modules) ?: null;
    }
}
