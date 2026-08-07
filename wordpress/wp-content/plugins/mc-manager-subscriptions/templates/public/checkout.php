<?php
declare(strict_types=1); if (!defined('ABSPATH')) { exit; }
?>
<div class="optigrid-public" data-optigrid-checkout
    data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
    data-nonce="<?php echo esc_attr(wp_create_nonce('optigrid_public_checkout_ajax')); ?>"
    data-poll-ms="3000"
    data-label-opening="<?php echo esc_attr__('Abriendo la pasarela…','optigrid-subscriptions'); ?>"
    data-label-waiting="<?php echo esc_attr__('Esperando confirmación del pago…','optigrid-subscriptions'); ?>"
    data-label-popup-blocked="<?php echo esc_attr__('El navegador bloqueó la nueva pestaña. Permite ventanas emergentes e inténtalo otra vez.','optigrid-subscriptions'); ?>"
    data-label-error="<?php echo esc_attr__('No se pudo iniciar el pago.','optigrid-subscriptions'); ?>">
<header class="optigrid-public__header"><h2><?php echo esc_html__('Suscripción al servidor','optigrid-subscriptions'); ?></h2><p><?php echo esc_html__('Selecciona un plan y después el método de pago.','optigrid-subscriptions'); ?></p></header>
<div class="optigrid-checkout-status" data-checkout-status hidden></div>
<?php if ($gateways===[]) : ?>
<div class="optigrid-public-notice optigrid-public-notice--warning"><p><?php echo esc_html__('No hay una pasarela disponible.','optigrid-subscriptions'); ?></p></div>
<?php elseif ($plans===[]) : ?>
<div class="optigrid-public-notice optigrid-public-notice--warning"><p><?php echo esc_html__('No hay planes activos disponibles.','optigrid-subscriptions'); ?></p></div>
<?php else : ?>
<div class="optigrid-plan-grid">
<?php foreach ($plans as $plan) : ?>
<article class="optigrid-plan-card">
<h3><?php echo esc_html((string)$plan['name']); ?></h3>
<?php if (!empty($plan['description'])) : ?><p><?php echo esc_html((string)$plan['description']); ?></p><?php endif; ?>
<p class="optigrid-plan-card__price"><strong><?php echo esc_html(number_format_i18n((float)$plan['price'],2).' '.strtoupper((string)$plan['currency'])); ?></strong></p>
<p><?php printf(esc_html__('Acceso durante %d días.','optigrid-subscriptions'),(int)$plan['duration_days']); ?></p>
<button type="button" class="button button-primary" data-select-plan data-plan-id="<?php echo esc_attr((string)$plan['id']); ?>" data-plan-name="<?php echo esc_attr((string)$plan['name']); ?>" data-idempotency="<?php echo esc_attr(sprintf('public:%d:%d:%s',get_current_user_id(),(int)$plan['id'],wp_generate_uuid4())); ?>"><?php echo esc_html__('Suscribirme','optigrid-subscriptions'); ?></button>
</article>
<?php endforeach; ?>
</div>
<div class="optigrid-public-notice optigrid-public-notice--warning"><p><strong><?php echo esc_html__('Entorno de pruebas:','optigrid-subscriptions'); ?></strong> <?php echo esc_html__('Sandbox no procesa dinero real, pero reproduce el ciclo completo de una pasarela.','optigrid-subscriptions'); ?></p></div>
<?php endif; ?>

<div class="optigrid-modal" data-gateway-modal hidden aria-hidden="true">
<div class="optigrid-modal__backdrop" data-close-modal></div>
<div class="optigrid-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="optigrid-gateway-title">
<button type="button" class="optigrid-modal__close" data-close-modal aria-label="Cerrar">×</button>
<h3 id="optigrid-gateway-title"><?php echo esc_html__('Selecciona la pasarela','optigrid-subscriptions'); ?></h3>
<p data-selected-plan></p>
<div class="optigrid-gateway-list">
<?php foreach ($gateways as $gateway) : ?>
<button type="button" class="optigrid-gateway-option" data-gateway="<?php echo esc_attr($gateway->get_id()); ?>">
<strong><?php echo esc_html($gateway->get_name()); ?></strong>
<span><?php echo esc_html($gateway->get_description()); ?></span>
</button>
<?php endforeach; ?>
</div>
</div>
</div>
</div>
