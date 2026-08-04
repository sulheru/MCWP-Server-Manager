<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$to_local_input = static function (?string $value): string {
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value . ' UTC');
    if ($timestamp === false) {
        return '';
    }

    return wp_date('Y-m-d\TH:i', $timestamp);
};
?>
<div class="wrap optigrid-subscriptions">
    <h1>
        <?php
        printf(
            esc_html__('Editar suscripción #%d', 'optigrid-subscriptions'),
            (int) $subscription['id']
        );
        ?>
    </h1>

    <p>
        <a href="<?php echo esc_url(add_query_arg(['page' => 'optigrid-subscriptions-manage'], admin_url('admin.php'))); ?>">
            ← <?php echo esc_html__('Volver al listado', 'optigrid-subscriptions'); ?>
        </a>
    </p>

    <?php if ($message === 'saved') : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Suscripción y entitlement actualizados.', 'optigrid-subscriptions'); ?></p></div>
    <?php elseif ($error !== '') : ?>
        <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>

    <div class="optigrid-subscription-editor-grid">
        <section class="optigrid-subscriptions-card">
            <h2><?php echo esc_html__('Datos de la suscripción', 'optigrid-subscriptions'); ?></h2>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(OptiGrid_Subscriptions_Subscription_Manager_Controller::action_save()); ?>">
                <input type="hidden" name="subscription_id" value="<?php echo esc_attr((string) $subscription['id']); ?>">

                <?php
                wp_nonce_field(
                    OptiGrid_Subscriptions_Subscription_Manager_Controller::nonce_action(),
                    OptiGrid_Subscriptions_Subscription_Manager_Controller::nonce_name()
                );
                ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Usuario', 'optigrid-subscriptions'); ?></th>
                        <td>
                            <strong><?php echo esc_html((string) $subscription['user_login']); ?></strong><br>
                            <?php echo esc_html((string) $subscription['user_email']); ?>
                            <p><a href="<?php echo esc_url(get_edit_user_link((int) $subscription['user_id'])); ?>"><?php echo esc_html__('Abrir usuario de WordPress', 'optigrid-subscriptions'); ?></a></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Plan', 'optigrid-subscriptions'); ?></th>
                        <td><?php echo esc_html((string) $subscription['plan_name']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="optigrid-status"><?php echo esc_html__('Estado', 'optigrid-subscriptions'); ?></label></th>
                        <td>
                            <select id="optigrid-status" name="status">
                                <?php foreach (['pending', 'active', 'cancelled', 'expired'] as $status) : ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected((string) $subscription['status'], $status); ?>><?php echo esc_html(ucfirst($status)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="optigrid-starts-at"><?php echo esc_html__('Inicio', 'optigrid-subscriptions'); ?></label></th>
                        <td><input id="optigrid-starts-at" type="datetime-local" name="starts_at" value="<?php echo esc_attr($to_local_input($subscription['starts_at'])); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="optigrid-ends-at"><?php echo esc_html__('Finalización', 'optigrid-subscriptions'); ?></label></th>
                        <td><input id="optigrid-ends-at" type="datetime-local" name="ends_at" value="<?php echo esc_attr($to_local_input($subscription['ends_at'])); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="optigrid-cancellation-reason"><?php echo esc_html__('Motivo de cancelación', 'optigrid-subscriptions'); ?></label></th>
                        <td>
                            <textarea id="optigrid-cancellation-reason" name="cancellation_reason" rows="4" class="large-text"><?php echo esc_textarea((string) ($subscription['cancellation_reason'] ?? '')); ?></textarea>
                            <p class="description"><?php echo esc_html__('Se utilizará también como motivo de revocación del entitlement.', 'optigrid-subscriptions'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Guardar cambios', 'optigrid-subscriptions')); ?>
            </form>
        </section>

        <aside class="optigrid-subscriptions-card">
            <h2><?php echo esc_html__('Relaciones', 'optigrid-subscriptions'); ?></h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th><?php echo esc_html__('Orden', 'optigrid-subscriptions'); ?></th>
                        <td><?php echo !empty($subscription['order_id']) ? '#' . esc_html((string) $subscription['order_id']) : '-'; ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Estado de orden', 'optigrid-subscriptions'); ?></th>
                        <td><?php echo esc_html((string) ($subscription['order_status'] ?? '-')); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Entitlement', 'optigrid-subscriptions'); ?></th>
                        <td><?php echo !empty($subscription['entitlement_id']) ? '#' . esc_html((string) $subscription['entitlement_id']) : '-'; ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Estado entitlement', 'optigrid-subscriptions'); ?></th>
                        <td><?php echo esc_html((string) ($subscription['entitlement_status'] ?? '-')); ?></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Fin entitlement', 'optigrid-subscriptions'); ?></th>
                        <td><?php echo esc_html((string) ($subscription['entitlement_ends_at'] ?? '-')); ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="notice notice-info inline">
                <p><?php echo esc_html__('Al guardar, el entitlement se recalcula automáticamente. El sync_worker aplicará después el acceso efectivo en Minecraft.', 'optigrid-subscriptions'); ?></p>
            </div>
        </aside>
    </div>
</div>
