<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Core;

final class AssetManager
{
    public const PAGE_HOOK = 'toplevel_page_gestor-mc-srv';

    public function enqueue(string $hookSuffix): void
    {
        if ($hookSuffix !== self::PAGE_HOOK) {
            return;
        }

        wp_enqueue_style(
            'mc-manager-server-admin',
            MC_MANAGER_SERVER_URL . 'assets/css/admin.css',
            [],
            MC_MANAGER_SERVER_VERSION
        );

        wp_enqueue_script(
            'mc-manager-server-admin',
            MC_MANAGER_SERVER_URL . 'assets/js/admin.js',
            [],
            MC_MANAGER_SERVER_VERSION,
            true
        );

        wp_localize_script(
            'mc-manager-server-admin',
            'mcManagerServer',
            [
                'version' => MC_MANAGER_SERVER_VERSION,
                'screen'  => 'gestor-mc-srv',
            ]
        );

        /**
         * Permite a módulos cargar recursos exclusivamente en la pantalla Host.
         */
        do_action('mc_manager_server_enqueue_assets', $hookSuffix);
    }
}
