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
            'dashicons-shield',
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
