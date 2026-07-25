<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Modules\Server;

use OptiGrid\MCManagerServer\Core\Plugin;
use Throwable;

final class ServerWriteController
{
    private const CAPABILITY = 'manage_options';
    private const PAGE_SLUG = 'gestor-mc-srv';
    private const MODULE_ID = 'core.server';

    public function registerHooks(): void
    {
        add_action(
            'admin_post_mcms_server_apply_runtime',
            [$this, 'handleApplyRuntime']
        );
    }

    public function handleApplyRuntime(): void
    {
        $this->authorize();

        $difficulty = $this->postedValue('difficulty');
        $gamemode = $this->postedValue('default_gamemode');

        if (!in_array($difficulty, ['peaceful', 'easy', 'normal', 'hard'], true)) {
            $this->redirect(
                'error',
                __('La dificultad seleccionada no es válida.', 'mc-manager-server')
            );
        }

        if (!in_array($gamemode, ['survival', 'creative', 'adventure', 'spectator'], true)) {
            $this->redirect(
                'error',
                __('El modo de juego seleccionado no es válido.', 'mc-manager-server')
            );
        }

        try {
            $gateway = Plugin::instance()->gatewayClient();

            $gateway->setDifficulty($difficulty);
            $gateway->setDefaultGamemode($gamemode);

            $this->redirect(
                'success',
                sprintf(
                    __(
                        'Cambios aplicados: dificultad %1$s y modo predeterminado %2$s.',
                        'mc-manager-server'
                    ),
                    $difficulty,
                    $gamemode
                )
            );
        } catch (Throwable $exception) {
            $message = trim($exception->getMessage());

            $this->redirect(
                'error',
                $message === ''
                    ? __('No se pudieron aplicar los cambios inmediatos.', 'mc-manager-server')
                    : sprintf(
                        __('No se pudieron aplicar los cambios inmediatos: %s', 'mc-manager-server'),
                        $message
                    )
            );
        }
    }

    private function authorize(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(
                esc_html__('No tienes permisos para modificar el servidor.', 'mc-manager-server'),
                esc_html__('Acceso denegado', 'mc-manager-server'),
                ['response' => 403]
            );
        }

        check_admin_referer('mcms_server_apply_runtime');
    }

    private function postedValue(string $key): string
    {
        if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) {
            return '';
        }

        return strtolower(
            sanitize_text_field(
                wp_unslash((string) $_POST[$key])
            )
        );
    }

    private function redirect(string $type, string $message): never
    {
        $url = add_query_arg(
            [
                'page' => self::PAGE_SLUG,
                'module' => self::MODULE_ID,
                'mcms_notice' => $type === 'success' ? 'success' : 'error',
                'mcms_message' => $message,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
