<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$is_new = $plan === null;

$values = $is_new
    ? [
        'id'            => 0,
        'code'          => $default_code,
        'name'          => '',
        'description'   => '',
        'price'         => '0.00',
        'currency'      => 'EUR',
        'duration_days' => 30,
        'is_active'     => 0,
        'is_visible'    => 0,
        'sort_order'    => 0,
    ]
    : $plan;

$notice_messages = [
    'created'    => __('Plan creado.', 'optigrid-subscriptions'),
    'updated'    => __('Plan actualizado.', 'optigrid-subscriptions'),
    'duplicated' => __('Plan duplicado. La copia está inactiva.', 'optigrid-subscriptions'),
];
?>

<div class="wrap optigrid-subscriptions">
    <h1>
        <?php
        echo esc_html(
            $is_new
                ? __('Añadir plan de suscripción', 'optigrid-subscriptions')
                : __('Editar plan de suscripción', 'optigrid-subscriptions')
        );
        ?>
    </h1>

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

    <?php if (!$is_new) : ?>
        <div class="notice notice-info inline">
            <p>
                <?php
                printf(
                    esc_html__(
                        'Uso histórico: %1$d suscripciones y %2$d órdenes.',
                        'optigrid-subscriptions'
                    ),
                    (int) $usage['subscriptions'],
                    (int) $usage['orders']
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <form
        method="post"
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
    >
        <input
            type="hidden"
            name="action"
            value="<?php
            echo esc_attr(
                OptiGrid_Subscriptions_Plan_Manager_Controller::save_action()
            );
            ?>"
        >

        <input
            type="hidden"
            name="plan_id"
            value="<?php echo esc_attr((string) $values['id']); ?>"
        >

        <?php wp_nonce_field('optigrid_subscriptions_save_plan'); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="optigrid-plan-code">
                            <?php echo esc_html__('Código interno', 'optigrid-subscriptions'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            id="optigrid-plan-code"
                            name="code"
                            type="text"
                            class="regular-text"
                            required
                            maxlength="100"
                            value="<?php echo esc_attr((string) $values['code']); ?>"
                        >
                        <p class="description">
                            <?php
                            echo esc_html__(
                                'Se genera automáticamente como UUID. Puedes cambiarlo si necesitas un identificador técnico distinto.',
                                'optigrid-subscriptions'
                            );
                            ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="optigrid-plan-name">
                            <?php echo esc_html__('Nombre público', 'optigrid-subscriptions'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            id="optigrid-plan-name"
                            name="name"
                            type="text"
                            class="regular-text"
                            required
                            maxlength="190"
                            value="<?php echo esc_attr((string) $values['name']); ?>"
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="optigrid-plan-description">
                            <?php echo esc_html__('Descripción', 'optigrid-subscriptions'); ?>
                        </label>
                    </th>
                    <td>
                        <textarea
                            id="optigrid-plan-description"
                            name="description"
                            rows="5"
                            class="large-text"
                        ><?php echo esc_textarea((string) $values['description']); ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="optigrid-plan-price">
                            <?php echo esc_html__('Precio', 'optigrid-subscriptions'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            id="optigrid-plan-price"
                            name="price"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            value="<?php echo esc_attr((string) $values['price']); ?>"
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="optigrid-plan-currency">
                            <?php echo esc_html__('Moneda', 'optigrid-subscriptions'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            id="optigrid-plan-currency"
                            name="currency"
                            type="text"
                            maxlength="3"
                            size="4"
                            required
                            value="<?php echo esc_attr((string) $values['currency']); ?>"
                        >
                        <p class="description">
                            <?php echo esc_html__('Código ISO, por ejemplo EUR.', 'optigrid-subscriptions'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="optigrid-plan-duration">
                            <?php echo esc_html__('Duración en días', 'optigrid-subscriptions'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            id="optigrid-plan-duration"
                            name="duration_days"
                            type="number"
                            min="1"
                            step="1"
                            required
                            value="<?php echo esc_attr((string) $values['duration_days']); ?>"
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="optigrid-plan-sort">
                            <?php echo esc_html__('Orden de aparición', 'optigrid-subscriptions'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            id="optigrid-plan-sort"
                            name="sort_order"
                            type="number"
                            step="1"
                            value="<?php echo esc_attr((string) $values['sort_order']); ?>"
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <?php echo esc_html__('Disponibilidad', 'optigrid-subscriptions'); ?>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input
                                    name="is_active"
                                    type="checkbox"
                                    value="1"
                                    <?php checked(!empty($values['is_active'])); ?>
                                >
                                <?php
                                echo esc_html__(
                                    'Activo: permite nuevas órdenes y asignaciones administrativas',
                                    'optigrid-subscriptions'
                                );
                                ?>
                            </label>

                            <br>

                            <label>
                                <input
                                    name="is_visible"
                                    type="checkbox"
                                    value="1"
                                    <?php checked(!empty($values['is_visible'])); ?>
                                >
                                <?php
                                echo esc_html__(
                                    'Visible: aparece en el checkout público',
                                    'optigrid-subscriptions'
                                );
                                ?>
                            </label>

                            <p class="description">
                                <?php
                                echo esc_html__(
                                    'Un plan activo y oculto solo puede gestionarse o asignarse desde administración. Un plan visible debe estar activo.',
                                    'optigrid-subscriptions'
                                );
                                ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php
        submit_button(
            $is_new
                ? __('Crear plan', 'optigrid-subscriptions')
                : __('Guardar cambios', 'optigrid-subscriptions')
        );
        ?>

        <a
            class="button"
            href="<?php
            echo esc_url(
                add_query_arg(
                    [
                        'page' => OptiGrid_Subscriptions_Plan_Manager_Controller::page_slug(),
                    ],
                    admin_url('admin.php')
                )
            );
            ?>"
        >
            <?php echo esc_html__('Volver al listado', 'optigrid-subscriptions'); ?>
        </a>
    </form>
</div>
