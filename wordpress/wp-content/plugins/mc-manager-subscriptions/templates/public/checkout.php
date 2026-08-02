<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

$return_url = remove_query_arg(
    ['optigrid_order', 'optigrid_checkout_error'],
    home_url(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'))
);
?>
<div class="optigrid-public">
    <header class="optigrid-public__header">
        <h2><?php echo esc_html__('Suscripción al servidor', 'optigrid-subscriptions'); ?></h2>
        <p><?php echo esc_html__('Selecciona un plan para obtener acceso al servidor Minecraft.', 'optigrid-subscriptions'); ?></p>
    </header>

    <?php if ($checkout_error !== '') : ?>
        <div class="optigrid-public-notice optigrid-public-notice--error"><p><?php echo esc_html($checkout_error); ?></p></div>
    <?php endif; ?>

    <?php if ($order !== null) : ?>
        <?php
        $labels = [
            'paid' => __('Pago aprobado', 'optigrid-subscriptions'),
            'pending' => __('Pago pendiente', 'optigrid-subscriptions'),
            'failed' => __('Pago rechazado o fallido', 'optigrid-subscriptions'),
            'cancelled' => __('Pago cancelado', 'optigrid-subscriptions'),
        ];
        $order_status = sanitize_key((string) ($order['status'] ?? 'pending'));
        ?>
        <section class="optigrid-public-result optigrid-public-result--<?php echo esc_attr($order_status); ?>">
            <h3><?php echo esc_html($labels[$order_status] ?? ucfirst($order_status)); ?></h3>
            <p><strong><?php echo esc_html__('Orden:', 'optigrid-subscriptions'); ?></strong> <code><?php echo esc_html((string) $order['public_id']); ?></code></p>
            <p><strong><?php echo esc_html__('Importe:', 'optigrid-subscriptions'); ?></strong> <?php echo esc_html(number_format_i18n((float) $order['amount'], 2) . ' ' . strtoupper((string) $order['currency'])); ?></p>
            <?php if ($order_status === 'paid') : ?>
                <p><?php echo esc_html__('La suscripción y el derecho de acceso están activos. La sincronización con Minecraft puede tardar unos segundos.', 'optigrid-subscriptions'); ?></p>
            <?php elseif ($order_status === 'pending') : ?>
                <p><?php echo esc_html__('La orden existe, pero todavía no ha sido confirmada.', 'optigrid-subscriptions'); ?></p>
            <?php else : ?>
                <p><?php echo esc_html__('No se ha creado una suscripción activa ni un entitlement.', 'optigrid-subscriptions'); ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!$sandbox_enabled) : ?>
        <div class="optigrid-public-notice optigrid-public-notice--warning"><p><?php echo esc_html__('No hay una pasarela disponible en este momento.', 'optigrid-subscriptions'); ?></p></div>
    <?php elseif ($plans === []) : ?>
        <div class="optigrid-public-notice optigrid-public-notice--warning"><p><?php echo esc_html__('No hay planes activos disponibles.', 'optigrid-subscriptions'); ?></p></div>
    <?php else : ?>
        <div class="optigrid-plan-grid">
            <?php foreach ($plans as $plan) : ?>
                <?php $idempotency_key = sprintf('public:%d:%d:%s', get_current_user_id(), (int) $plan['id'], wp_generate_uuid4()); ?>
                <article class="optigrid-plan-card">
                    <h3><?php echo esc_html((string) $plan['name']); ?></h3>
                    <?php if (!empty($plan['description'])) : ?><p><?php echo esc_html((string) $plan['description']); ?></p><?php endif; ?>
                    <p class="optigrid-plan-card__price"><strong><?php echo esc_html(number_format_i18n((float) $plan['price'], 2) . ' ' . strtoupper((string) $plan['currency'])); ?></strong></p>
                    <p><?php printf(esc_html__('Acceso durante %d días.', 'optigrid-subscriptions'), (int) $plan['duration_days']); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(OptiGrid_Subscriptions_Public_Checkout_Controller::action()); ?>">
                        <input type="hidden" name="plan_id" value="<?php echo esc_attr((string) $plan['id']); ?>">
                        <input type="hidden" name="idempotency_key" value="<?php echo esc_attr($idempotency_key); ?>">
                        <input type="hidden" name="return_url" value="<?php echo esc_url($return_url); ?>">
                        <?php wp_nonce_field(OptiGrid_Subscriptions_Public_Checkout_Controller::nonce_action(), OptiGrid_Subscriptions_Public_Checkout_Controller::nonce_name()); ?>
                        <button type="submit" class="button button-primary"><?php echo esc_html__('Suscribirme', 'optigrid-subscriptions'); ?></button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="optigrid-public-notice optigrid-public-notice--warning"><p><strong><?php echo esc_html__('Entorno Sandbox:', 'optigrid-subscriptions'); ?></strong> <?php echo esc_html__('no procesa dinero real. El resultado es el configurado por el administrador.', 'optigrid-subscriptions'); ?></p></div>
    <?php endif; ?>
</div>
