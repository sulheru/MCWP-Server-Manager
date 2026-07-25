<?php

use OptiGrid\MCManagerServer\Components\Alert;
use OptiGrid\MCManagerServer\Components\Badge;
use OptiGrid\MCManagerServer\Components\Button;
use OptiGrid\MCManagerServer\Components\Section;
use OptiGrid\MCManagerServer\Components\Toolbar;
use OptiGrid\MCManagerServer\Modules\Server\ServerSnapshot;

if (!defined('ABSPATH')) {
    exit;
}

/** @var ServerSnapshot $snapshot */

$difficultyLabels = [
    'peaceful' => __('Pacífica', 'mc-manager-server'),
    'easy' => __('Fácil', 'mc-manager-server'),
    'normal' => __('Normal', 'mc-manager-server'),
    'hard' => __('Difícil', 'mc-manager-server'),
    'unknown' => __('No disponible', 'mc-manager-server'),
];

$gamemodeLabels = [
    'survival' => __('Supervivencia', 'mc-manager-server'),
    'creative' => __('Creativo', 'mc-manager-server'),
    'adventure' => __('Aventura', 'mc-manager-server'),
    'spectator' => __('Espectador', 'mc-manager-server'),
    'unknown' => __('No disponible', 'mc-manager-server'),
];

$statusLabel = in_array($snapshot->status(), ['online', 'running', 'operational'], true)
    ? __('Operativo', 'mc-manager-server')
    : ucfirst($snapshot->status());

$playersText = $snapshot->playersOnline() !== null && $snapshot->maxPlayers() !== null
    ? sprintf('%d / %d', $snapshot->playersOnline(), $snapshot->maxPlayers())
    : __('No disponible', 'mc-manager-server');

$worldsText = $snapshot->worldCount() !== null
    ? (string) $snapshot->worldCount()
    : __('No disponible', 'mc-manager-server');

$chunksText = $snapshot->loadedChunks() !== null
    ? (string) $snapshot->loadedChunks()
    : __('No disponible', 'mc-manager-server');
?>
<section class="mcms-server" aria-labelledby="mcms-server-title">
    <div class="mcms-module-heading">
        <div>
            <h2 id="mcms-server-title"><?php echo esc_html__('Servidor', 'mc-manager-server'); ?></h2>
            <p><?php echo esc_html__('Lectura directa del estado y de la configuración activa de PaperMC mediante el Minecraft Gateway.', 'mc-manager-server'); ?></p>
        </div>
        <?php Badge::render(__('Módulo core.server', 'mc-manager-server')); ?>
    </div>

    <?php if ($snapshot->gatewayAvailable()) : ?>
        <?php Alert::render(
            __('Configuración activa leída correctamente. La dificultad y el modo de juego predeterminado pueden modificarse en caliente mediante el Gateway.', 'mc-manager-server'),
            Alert::TYPE_SUCCESS
        ); ?>
    <?php else : ?>
        <?php Alert::render(
            __('No se pudo obtener el estado del servidor desde el Minecraft Gateway.', 'mc-manager-server'),
            Alert::TYPE_ERROR
        ); ?>
    <?php endif; ?>

    <?php foreach ($snapshot->warnings() as $warning) : ?>
        <?php Alert::render($warning, Alert::TYPE_WARNING); ?>
    <?php endforeach; ?>

    <?php Section::open(
        __('Estado de configuración', 'mc-manager-server'),
        __('La fuente oficial es el servidor. WordPress no conserva una copia permanente de estos valores.', 'mc-manager-server'),
        'mcms-server-configuration-status'
    ); ?>
        <div class="mcms-status-grid">
            <div class="mcms-status-item">
                <span><?php echo esc_html__('Estado del servidor', 'mc-manager-server'); ?></span>
                <?php Badge::render($statusLabel, $snapshot->gatewayAvailable() ? Badge::VARIANT_SUCCESS : Badge::VARIANT_NEUTRAL); ?>
            </div>
            <div class="mcms-status-item">
                <span><?php echo esc_html__('Jugadores', 'mc-manager-server'); ?></span>
                <strong><?php echo esc_html($playersText); ?></strong>
            </div>
            <div class="mcms-status-item">
                <span><?php echo esc_html__('Última comprobación', 'mc-manager-server'); ?></span>
                <strong><?php echo esc_html($snapshot->checkedAt()); ?></strong>
            </div>
        </div>
    <?php Section::close(); ?>

    <?php Section::open(
        __('Configuración activa', 'mc-manager-server'),
        __('Valores observados mediante el Gateway. Los cambios se aplican inmediatamente mediante RCON, sin modificar server.properties ni reiniciar PaperMC.', 'mc-manager-server'),
        'mcms-server-hot-configuration'
    ); ?>
        <?php
        $mcmsCanWriteRuntime = $snapshot->gatewayAvailable() && current_user_can('manage_options');
        $mcmsCurrentDifficulty = $snapshot->difficulty();
        $mcmsCurrentGamemode = $snapshot->defaultGamemode();
        $mcmsNoticeType = isset($_GET['mcms_notice'])
            ? sanitize_key(wp_unslash((string) $_GET['mcms_notice']))
            : '';
        $mcmsNoticeMessage = isset($_GET['mcms_message'])
            ? sanitize_text_field(wp_unslash((string) $_GET['mcms_message']))
            : '';
        ?>

        <?php if ($mcmsNoticeMessage !== '') : ?>
            <div class="notice <?php echo $mcmsNoticeType === 'success' ? 'notice-success' : 'notice-error'; ?> inline">
                <p><?php echo esc_html($mcmsNoticeMessage); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="mcms_server_apply_runtime">
            <?php wp_nonce_field('mcms_server_apply_runtime'); ?>

            <div class="mcms-form-grid">
                <div class="mcms-field">
                    <label for="mcms-server-difficulty"><?php echo esc_html__('Dificultad', 'mc-manager-server'); ?></label>
                    <select
                        id="mcms-server-difficulty"
                        name="difficulty"
                        <?php disabled(!$mcmsCanWriteRuntime); ?>
                    >
                        <?php foreach ($difficultyLabels as $mcmsValue => $mcmsLabel) : ?>
                            <option
                                value="<?php echo esc_attr((string) $mcmsValue); ?>"
                                <?php selected($mcmsCurrentDifficulty, $mcmsValue); ?>
                            >
                                <?php echo esc_html($mcmsLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p><?php echo esc_html__('El Gateway verifica por RCON que la dificultad aplicada coincide con la solicitada.', 'mc-manager-server'); ?></p>
                </div>

                <div class="mcms-field">
                    <label for="mcms-server-gamemode"><?php echo esc_html__('Modo de juego por defecto', 'mc-manager-server'); ?></label>
                    <select
                        id="mcms-server-gamemode"
                        name="default_gamemode"
                        required
                        <?php disabled(!$mcmsCanWriteRuntime); ?>
                    >
                        <option value="">
                            <?php echo esc_html__('Selecciona un modo', 'mc-manager-server'); ?>
                        </option>
                        <?php foreach ($gamemodeLabels as $mcmsValue => $mcmsLabel) : ?>
                            <option
                                value="<?php echo esc_attr((string) $mcmsValue); ?>"
                                <?php selected($mcmsCurrentGamemode, $mcmsValue); ?>
                            >
                                <?php echo esc_html($mcmsLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p><?php echo esc_html__('RCON confirma la aceptación del comando; el valor no dispone de lectura posterior fiable.', 'mc-manager-server'); ?></p>
                </div>

                <div class="mcms-field">
                    <label><?php echo esc_html__('Mundos cargados', 'mc-manager-server'); ?></label>
                    <input type="text" value="<?php echo esc_attr($worldsText); ?>" disabled>
                    <p><?php echo esc_html__('Número de mundos devueltos por /world/state.', 'mc-manager-server'); ?></p>
                </div>

                <div class="mcms-field">
                    <label><?php echo esc_html__('Chunks cargados', 'mc-manager-server'); ?></label>
                    <input type="text" value="<?php echo esc_attr($chunksText); ?>" disabled>
                    <p><?php echo esc_html__('Carga global observada en el momento de la consulta.', 'mc-manager-server'); ?></p>
                </div>
            </div>

            <?php Toolbar::open(__('Acciones de configuración', 'mc-manager-server')); ?>
                <button
                    type="submit"
                    class="button button-primary"
                    <?php disabled(!$mcmsCanWriteRuntime); ?>
                >
                    <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                    <?php echo esc_html__('Aplicar cambios inmediatos', 'mc-manager-server'); ?>
                </button>
                <?php Button::render(
                    __('Recargar valores', 'mc-manager-server'),
                    remove_query_arg(['mcms_notice', 'mcms_message']),
                    'secondary',
                    'update'
                ); ?>
            <?php Toolbar::close(); ?>
        </form>
    <?php Section::close(); ?>

    <?php Section::open(
        __('Configuración persistente', 'mc-manager-server'),
        __('La lectura y escritura de server.properties se incorporará cuando exista el contrato transaccional correspondiente en el Gateway.', 'mc-manager-server'),
        'mcms-server-cold-configuration'
    ); ?>
        <div class="mcms-empty-inline">
            <span class="dashicons dashicons-lock" aria-hidden="true"></span>
            <div>
                <strong><?php echo esc_html__('Escritura persistente todavía no habilitada', 'mc-manager-server'); ?></strong>
                <p><?php echo esc_html__('Máximo de jugadores, distancias y MOTD permanecerán bloqueados hasta disponer de lectura persistente, validación, rollback y reinicio seguro.', 'mc-manager-server'); ?></p>
            </div>
        </div>
    <?php Section::close(); ?>
</section>
