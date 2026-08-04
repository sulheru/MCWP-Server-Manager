<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$notice_messages = [
    'created'     => __('Plan creado.', 'optigrid-subscriptions'),
    'updated'     => __('Plan actualizado.', 'optigrid-subscriptions'),
    'duplicated'  => __('Plan duplicado como borrador inactivo.', 'optigrid-subscriptions'),
    'activated'   => __('Plan activado.', 'optigrid-subscriptions'),
    'deactivated' => __('Plan desactivado.', 'optigrid-subscriptions'),
];
?>

<div class="wrap optigrid-subscriptions">
    <h1 class="wp-heading-inline">
        <?php
        echo esc_html__(
            'Planes de suscripción',
            'optigrid-subscriptions'
        );
        ?>
    </h1>

    <a
        href="<?php
        echo esc_url(
            add_query_arg(
                [
                    'page' => OptiGrid_Subscriptions_Plan_Manager_Controller::page_slug(),
                    'mode' => 'new',
                ],
                admin_url('admin.php')
            )
        );
        ?>"
        class="page-title-action"
    >
        <?php
        echo esc_html__(
            'Añadir nuevo',
            'optigrid-subscriptions'
        );
        ?>
    </a>

    <hr class="wp-header-end">

    <?php if ($notice !== '' && isset($notice_messages[$notice])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($notice_messages[$notice]); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error !== '') : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($error); ?></p>
        </div>
    <?php endif; ?>

    <p>
        <?php
        echo esc_html__(
            'Los planes activos aparecen automáticamente en el checkout público. Los planes utilizados se conservan y se desactivan en lugar de eliminarse.',
            'optigrid-subscriptions'
        );
        ?>
    </p>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php echo esc_html__('Orden', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Nombre', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Código', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Precio', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Duración', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Activo', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Visible', 'optigrid-subscriptions'); ?></th>
                <th><?php echo esc_html__('Acciones', 'optigrid-subscriptions'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($plans === []) : ?>
                <tr>
                    <td colspan="8">
                        <?php
                        echo esc_html__(
                            'No existen planes.',
                            'optigrid-subscriptions'
                        );
                        ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($plans as $item) : ?>
                    <?php
                    $edit_url = add_query_arg(
                        [
                            'page'    => OptiGrid_Subscriptions_Plan_Manager_Controller::page_slug(),
                            'plan_id' => (int) $item['id'],
                        ],
                        admin_url('admin.php')
                    );

                    $duplicate_url = wp_nonce_url(
                        add_query_arg(
                            [
                                'action'  => OptiGrid_Subscriptions_Plan_Manager_Controller::duplicate_action(),
                                'plan_id' => (int) $item['id'],
                            ],
                            admin_url('admin-post.php')
                        ),
                        'optigrid_subscriptions_duplicate_plan_'
                            . (int) $item['id']
                    );

                    $new_active = empty($item['is_active']) ? 1 : 0;

                    $toggle_url = wp_nonce_url(
                        add_query_arg(
                            [
                                'action'  => OptiGrid_Subscriptions_Plan_Manager_Controller::toggle_action(),
                                'plan_id' => (int) $item['id'],
                                'active'  => $new_active,
                            ],
                            admin_url('admin-post.php')
                        ),
                        'optigrid_subscriptions_toggle_plan_'
                            . (int) $item['id']
                    );
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) $item['sort_order']); ?></td>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url($edit_url); ?>">
                                    <?php echo esc_html((string) $item['name']); ?>
                                </a>
                            </strong>
                        </td>
                        <td><code><?php echo esc_html((string) $item['code']); ?></code></td>
                        <td>
                            <?php
                            echo esc_html(
                                number_format_i18n(
                                    (float) $item['price'],
                                    2
                                )
                                . ' '
                                . strtoupper((string) $item['currency'])
                            );
                            ?>
                        </td>
                        <td>
                            <?php
                            printf(
                                esc_html__('%d días', 'optigrid-subscriptions'),
                                (int) $item['duration_days']
                            );
                            ?>
                        </td>
                        <td>
                            <?php if (!empty($item['is_active'])) : ?>
                                <strong><?php echo esc_html__('Sí', 'optigrid-subscriptions'); ?></strong>
                            <?php else : ?>
                                <?php echo esc_html__('No', 'optigrid-subscriptions'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($item['is_visible'])) : ?>
                                <strong><?php echo esc_html__('Público', 'optigrid-subscriptions'); ?></strong>
                            <?php elseif (!empty($item['is_active'])) : ?>
                                <?php echo esc_html__('Solo administración', 'optigrid-subscriptions'); ?>
                            <?php else : ?>
                                <?php echo esc_html__('No', 'optigrid-subscriptions'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>">
                                <?php echo esc_html__('Editar', 'optigrid-subscriptions'); ?>
                            </a>
                            |
                            <a href="<?php echo esc_url($duplicate_url); ?>">
                                <?php echo esc_html__('Duplicar', 'optigrid-subscriptions'); ?>
                            </a>
                            |
                            <a href="<?php echo esc_url($toggle_url); ?>">
                                <?php
                                echo esc_html(
                                    !empty($item['is_active'])
                                        ? __('Desactivar', 'optigrid-subscriptions')
                                        : __('Activar', 'optigrid-subscriptions')
                                );
                                ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
