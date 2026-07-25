<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Modules\Summary;

use OptiGrid\MCManagerServer\Contracts\ModuleInterface;
use OptiGrid\MCManagerServer\Core\Plugin;

final class SummaryModule implements ModuleInterface
{
    public function id(): string
    {
        return 'core.summary';
    }

    public function label(): string
    {
        return __('Resumen', 'mc-manager-server');
    }

    public function icon(): string
    {
        return 'dashboard';
    }

    public function priority(): int
    {
        return 10;
    }

    public function capability(): string
    {
        return 'manage_options';
    }

    public function render(): void
    {
        $template = MC_MANAGER_SERVER_PATH . 'templates/modules/summary.php';

        if (!is_readable($template)) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('No se encuentra la plantilla del módulo Resumen.', 'mc-manager-server');
            echo '</p></div>';
            return;
        }

        $snapshot = SummarySnapshot::collect(Plugin::instance()->gatewayClient());
        require $template;
    }
}
