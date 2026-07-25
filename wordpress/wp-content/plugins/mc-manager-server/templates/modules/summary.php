<?php

use OptiGrid\MCManagerServer\Components\Alert;
use OptiGrid\MCManagerServer\Components\Badge;
use OptiGrid\MCManagerServer\Components\Card;
use OptiGrid\MCManagerServer\Modules\Summary\SummarySnapshot;

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($snapshot) || !$snapshot instanceof SummarySnapshot) {
    return;
}

$values = $snapshot->cards();
$cards = [
    ['key' => 'server', 'icon' => 'yes-alt', 'title' => __('Estado del servidor', 'mc-manager-server')],
    ['key' => 'players', 'icon' => 'groups', 'title' => __('Jugadores conectados', 'mc-manager-server')],
    ['key' => 'world', 'icon' => 'admin-site-alt3', 'title' => __('Mundos', 'mc-manager-server')],
    ['key' => 'performance', 'icon' => 'performance', 'title' => __('Rendimiento', 'mc-manager-server')],
    ['key' => 'alerts', 'icon' => 'warning', 'title' => __('Alertas', 'mc-manager-server')],
    ['key' => 'configuration', 'icon' => 'admin-generic', 'title' => __('Configuración observada', 'mc-manager-server')],
];

$timestamp = strtotime($snapshot->checkedAt());
$checkedAt = $timestamp !== false
    ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)
    : $snapshot->checkedAt();
?>
<section class="mcms-summary" aria-labelledby="mcms-summary-title">
    <div class="mcms-module-heading">
        <div>
            <h2 id="mcms-summary-title"><?php echo esc_html__('Resumen', 'mc-manager-server'); ?></h2>
            <p><?php echo esc_html__('Visión global basada en datos reales del Minecraft Gateway.', 'mc-manager-server'); ?></p>
        </div>
        <?php Badge::render(sprintf(__('Actualizado: %s', 'mc-manager-server'), $checkedAt)); ?>
    </div>

    <div class="mcms-card-grid">
        <?php foreach ($cards as $card) : ?>
            <?php
            $data = $values[$card['key']] ?? [];
            Card::render(
                $card['title'],
                isset($data['value']) ? (string) $data['value'] : '—',
                isset($data['description']) ? (string) $data['description'] : '',
                $card['icon'],
                isset($data['status']) ? (string) $data['status'] : ''
            );
            ?>
        <?php endforeach; ?>
    </div>

    <?php if (!$snapshot->gatewayAvailable()) : ?>
        <?php Alert::render(
            __('No se ha podido obtener ningún snapshot del Gateway. El Dashboard permanece operativo y volverá a intentarlo al recargar la página.', 'mc-manager-server'),
            Alert::TYPE_ERROR,
            __('Gateway no disponible', 'mc-manager-server')
        ); ?>
    <?php elseif ($snapshot->warnings() !== []) : ?>
        <?php Alert::render(
            implode(' · ', array_slice($snapshot->warnings(), 0, 3)),
            Alert::TYPE_WARNING,
            __('Snapshot parcial', 'mc-manager-server')
        ); ?>
    <?php else : ?>
        <?php Alert::render(
            __('Lectura completada correctamente. Esta iteración es exclusivamente de consulta y no ejecuta operaciones de escritura.', 'mc-manager-server'),
            Alert::TYPE_SUCCESS,
            __('Gateway conectado', 'mc-manager-server')
        ); ?>
    <?php endif; ?>
</section>
