<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Core;

use OptiGrid\MCManagerServer\Contracts\GatewayClientInterface;
use OptiGrid\MCManagerServer\Gateway\GatewayClient;
use OptiGrid\MCManagerServer\Modules\Server\ServerModule;
use OptiGrid\MCManagerServer\Modules\Summary\SummaryModule;
use OptiGrid\MCManagerServer\Updates\GitHubPluginUpdater;
use OptiGrid\MCManagerServer\Updates\GitHubUpdateSettings;
use OptiGrid\MCManagerServer\Updates\GitHubUpdateAdminPage;

final class Plugin
{
    private const DEFAULT_GATEWAY_BASE_URL = 'http://minecraft-gateway:8000';

    private static ?self $instance = null;

    private bool $booted = false;

    private ?GatewayClientInterface $gatewayClient = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        load_plugin_textdomain(
            'mc-manager-server',
            false,
            dirname(plugin_basename(MC_MANAGER_SERVER_FILE)) . '/languages'
        );

        // Los módulos nativos utilizan la misma API pública que las extensiones.
        add_filter('mc_manager_server_modules', [$this, 'registerCoreModules']);

        $moduleManager = new ModuleManager();
        $assetManager = new AssetManager();
        $dashboard = new Dashboard($moduleManager, $assetManager);

        $dashboard->registerHooks();

        $githubUpdateSettings = new GitHubUpdateSettings();
        $githubUpdater = new GitHubPluginUpdater(
            $githubUpdateSettings
        );
        $githubUpdater->registerHooks();

        $githubUpdateAdminPage = new GitHubUpdateAdminPage(
            $githubUpdateSettings,
            $githubUpdater
        );
        $githubUpdateAdminPage->registerHooks();

        /**
         * Se dispara cuando el Dashboard Host está inicializado y los plugins
         * pueden registrar integraciones adicionales.
         */
        do_action('mc_manager_server_ready', $this);
    }

    /**
     * Cliente HTTP compartido para el Minecraft Gateway.
     *
     * La instancia puede sustituirse mediante el filtro público para pruebas,
     * instrumentación o implementaciones compatibles de terceros.
     */
    public function gatewayClient(): GatewayClientInterface
    {
        if ($this->gatewayClient === null) {
            $baseUrl = defined('MC_MANAGER_SERVER_GATEWAY_URL')
                ? (string) MC_MANAGER_SERVER_GATEWAY_URL
                : self::DEFAULT_GATEWAY_BASE_URL;

            /** @var string $baseUrl */
            $baseUrl = apply_filters('mc_manager_server_gateway_base_url', $baseUrl);

            $client = new GatewayClient($baseUrl);

            /** @var GatewayClientInterface $client */
            $client = apply_filters('mc_manager_server_gateway_client', $client, $baseUrl);

            $this->gatewayClient = $client;
        }

        return $this->gatewayClient;
    }

    /**
     * @param array<int, mixed> $modules
     * @return array<int, mixed>
     */
    public function registerCoreModules(array $modules): array
    {
        $modules[] = new SummaryModule();
        $modules[] = new ServerModule();

        return $modules;
    }
}
