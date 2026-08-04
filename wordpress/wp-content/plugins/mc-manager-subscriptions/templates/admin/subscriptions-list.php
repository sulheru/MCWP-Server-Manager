<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap optigrid-subscriptions">
    <h1><?php echo esc_html__('Gestionar suscripciones', 'optigrid-subscriptions'); ?></h1>

    <form method="get" class="optigrid-subscription-filters">
        <input type="hidden" name="page" value="optigrid-subscriptions-manage">

        <input
            type="search"
            name="s"
            value="<?php echo esc_attr((string) $filters['search']); ?>"
            placeholder="<?php echo esc_attr__('Usuario, correo, plan o ID', 'optigrid-subscriptions'); ?>"
        >

        <select name="status">
            <option value=""><?php echo esc_html__('Todos los estados', 'optigrid-subscriptions'); ?></option>
            <?php foreach (['pending', 'active', 'cancelled', 'expired'] as $status) : ?>
                <option value="<?php echo esc_attr($status); ?>" <?php selected($filters['status'], $status); ?>>
                    <?php echo esc_html(ucfirst($status)); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="plan_id">
            <option value="0"><?php echo esc_html__('Todos los planes', 'optigrid-subscriptions'); ?></option>
            <?php foreach ($plans as $plan) : ?>
                <option value="<?php echo esc_attr((string) $plan['id']); ?>" <?php selected((int) $filters['plan_id'], (int) $plan['id']); ?>>
                    <?php echo esc_html((string) $plan['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="button"><?php echo esc_html__('Filtrar', 'optigrid-subscriptions'); ?></button>
    </form>

    <p>
        <?php
        printf(
            esc_html__('%d suscripciones encontradas.', 'optigrid-subscriptions'),
            (int) $result['total']
        );
        ?>
    </p>

    <table class="widefat striped optigrid-subscriptions-table">
        <thead>
            <tr>
                <th>ID</th>
                <th><?php echo esc_html__('Usuario', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Plan', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Estado', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Inicio', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Fin', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Entitlement', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Acciones', 'optigrid-subscriptions'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result['items'] === []) : ?>
                <tr>
                    <td colspan="8"><?php echo esc_html__('No hay suscripciones que coincidan con los filtros.', 'optigrid-subscriptions'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($result['items'] as $subscription) : ?>
                    <tr>
                        <td>#<?php echo esc_html((string) $subscription['id']); ?></td>
                        <td>
                            <strong><?php echo esc_html((string) $subscription['user_login']); ?></strong><br>
                            <small><?php echo esc_html((string) $subscription['user_email']); ?></small>
                        </td>
                        <td><?php echo esc_html((string) $subscription['plan_name']); ?></td>
                        <td><code><?php echo esc_html((string) $subscription['status']); ?></code></td>
                        <td><?php echo esc_html((string) ($subscription['starts_at'] ?? '-')); ?></td>
                        <td><?php echo esc_html((string) ($subscription['ends_at'] ?? '-')); ?></td>
                        <td>
                            <?php if (!empty($subscription['entitlement_id'])) : ?>
                                <code><?php echo esc_html((string) $subscription['entitlement_status']); ?></code>
                            <?php else : ?>
                                <?php echo esc_html__('No creado', 'optigrid-subscriptions'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => 'optigrid-subscriptions-manage', 'subscription_id' => (int) $subscription['id']], admin_url('admin.php'))); ?>">
                                <?php echo esc_html__('Editar', 'optigrid-subscriptions'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ((int) $result['pages'] > 1) : ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo wp_kses_post(
                    paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'current' => (int) $result['page'],
                        'total' => (int) $result['pages'],
                    ])
                );
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
