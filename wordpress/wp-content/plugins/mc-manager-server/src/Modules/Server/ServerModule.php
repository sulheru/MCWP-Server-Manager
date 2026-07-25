<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Modules\Server;

use OptiGrid\MCManagerServer\Contracts\ModuleInterface;
use OptiGrid\MCManagerServer\Core\Plugin;

final class ServerModule implements ModuleInterface
{
    public function id(): string
    {
        return 'core.server';
    }

    public function label(): string
    {
        return __('Servidor', 'mc-manager-server');
    }

    public function icon(): string
    {
        return 'admin-generic';
    }

    public function priority(): int
    {
        return 20;
    }

    public function capability(): string
    {
        return 'manage_options';
    }

    public function render(): void
    {
        $template = MC_MANAGER_SERVER_PATH . 'templates/modules/server.php';

        if (!is_readable($template)) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('No se encuentra la plantilla del módulo Servidor.', 'mc-manager-server');
            echo '</p></div>';
            return;
        }

        $snapshot = ServerSnapshot::collect(
            Plugin::instance()->gatewayClient()
        );

        require $template;
    }
}
