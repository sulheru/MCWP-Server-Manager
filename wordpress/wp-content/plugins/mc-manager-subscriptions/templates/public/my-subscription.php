<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
?>
<div class="optigrid-public">
    <header class="optigrid-public__header"><h2><?php echo esc_html__('Mi suscripción', 'optigrid-subscriptions'); ?></h2></header>

    <section class="optigrid-public-section">
        <h3><?php echo esc_html__('Estado actual', 'optigrid-subscriptions'); ?></h3>
        <?php if ($active_subscriptions === []) : ?>
            <div class="optigrid-public-notice optigrid-public-notice--info"><p><?php echo esc_html__('No tienes una suscripción activa.', 'optigrid-subscriptions'); ?></p></div>
        <?php else : ?>
            <div class="optigrid-subscription-list">
                <?php foreach ($active_subscriptions as $subscription) : ?>
                    <article class="optigrid-subscription-card">
                        <h4><?php echo esc_html((string) $subscription['plan_name']); ?></h4>
                        <p><strong><?php echo esc_html__('Estado:', 'optigrid-subscriptions'); ?></strong> <?php echo esc_html((string) $subscription['status']); ?></p>
                        <p><strong><?php echo esc_html__('Inicio:', 'optigrid-subscriptions'); ?></strong> <?php echo esc_html(get_date_from_gmt((string) $subscription['starts_at'], get_option('date_format'))); ?></p>
                        <p><strong><?php echo esc_html__('Finalización:', 'optigrid-subscriptions'); ?></strong> <?php echo esc_html(get_date_from_gmt((string) $subscription['ends_at'], get_option('date_format'))); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p><strong><?php echo esc_html__('Acceso Minecraft:', 'optigrid-subscriptions'); ?></strong>
            <?php if ($active_entitlements !== []) : ?>
                <span class="optigrid-state optigrid-state--active"><?php echo esc_html__('Derecho activo', 'optigrid-subscriptions'); ?></span>
            <?php else : ?>
                <span class="optigrid-state optigrid-state--inactive"><?php echo esc_html__('Sin derecho activo', 'optigrid-subscriptions'); ?></span>
            <?php endif; ?>
        </p>
    </section>

    <section class="optigrid-public-section">
        <h3><?php echo esc_html__('Órdenes recientes', 'optigrid-subscriptions'); ?></h3>
        <?php if ($recent_orders === []) : ?>
            <p><?php echo esc_html__('Todavía no tienes órdenes.', 'optigrid-subscriptions'); ?></p>
        <?php else : ?>
            <div class="optigrid-responsive-table"><table><thead><tr>
                <th><?php echo esc_html__('Fecha', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Estado', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Importe', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Pasarela', 'optigrid-subscriptions'); ?></th>
            </tr></thead><tbody>
            <?php foreach ($recent_orders as $recent_order) : ?>
                <tr>
                    <td><?php echo esc_html(get_date_from_gmt((string) $recent_order['created_at'], get_option('date_format') . ' ' . get_option('time_format'))); ?></td>
                    <td><?php echo esc_html((string) $recent_order['status']); ?></td>
                    <td><?php echo esc_html(number_format_i18n((float) $recent_order['amount'], 2) . ' ' . strtoupper((string) $recent_order['currency'])); ?></td>
                    <td><?php echo esc_html((string) $recent_order['gateway']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
</div>
