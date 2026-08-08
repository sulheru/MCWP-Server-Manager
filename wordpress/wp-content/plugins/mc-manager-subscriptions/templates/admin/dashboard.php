<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$database_status = OptiGrid_Subscriptions_Database::status();
$gateways = $gateway_registry->all();
$enabled_gateways = $gateway_registry->enabled();

$gateway_update = isset($_GET['gateway_updated'])
    ? sanitize_key(wp_unslash($_GET['gateway_updated']))
    : '';
?>

<div class="wrap optigrid-subscriptions">
    <h1>
        <?php echo esc_html__('Suscripciones', 'optigrid-subscriptions'); ?>
    </h1>

    <p>
        <?php
        echo esc_html__(
            'Gestión de planes, suscripciones, pagos y derechos de acceso de OptiGrid.',
            'optigrid-subscriptions'
        );
        ?>
    </p>

    <?php if ($gateway_update === 'saved') : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                echo esc_html__(
                    'La configuración de la pasarela se ha guardado.',
                    'optigrid-subscriptions'
                );
                ?>
            </p>
        </div>
    <?php elseif ($gateway_update === 'paypal_live_incomplete') : ?>
        <div class="notice notice-error"><p><strong>PayPal Live no se ha activado.</strong> Completa Client ID, Client Secret y Webhook ID Live. El entorno permanece en Sandbox.</p></div>
    <?php elseif ($gateway_update === 'unknown_gateway') : ?>
        <div class="notice notice-error">
            <p>
                <?php
                echo esc_html__(
                    'No se pudo identificar la pasarela solicitada.',
                    'optigrid-subscriptions'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($database_status['error'] !== '') : ?>
        <div class="notice notice-error inline">
            <p>
                <strong>
                    <?php
                    echo esc_html__(
                        'Error de base de datos:',
                        'optigrid-subscriptions'
                    );
                    ?>
                </strong>
                <?php echo esc_html($database_status['error']); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="optigrid-subscriptions-grid">
        <section class="optigrid-subscriptions-card">
            <h2>
                <?php
                echo esc_html__(
                    'Estado del módulo',
                    'optigrid-subscriptions'
                );
                ?>
            </h2>

            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th scope="row">
                            <?php
                            echo esc_html__(
                                'Versión del plugin',
                                'optigrid-subscriptions'
                            );
                            ?>
                        </th>
                        <td>
                            <code>
                                <?php
                                echo esc_html(
                                    OPTIGRID_SUBSCRIPTIONS_VERSION
                                );
                                ?>
                            </code>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php
                            echo esc_html__(
                                'Base de datos',
                                'optigrid-subscriptions'
                            );
                            ?>
                        </th>
                        <td>
                            <strong>
                                <?php
                                echo esc_html(
                                    $database_status['all_present']
                                        ? __(
                                            'Esquema operativo',
                                            'optigrid-subscriptions'
                                        )
                                        : __(
                                            'Esquema incompleto',
                                            'optigrid-subscriptions'
                                        )
                                );
                                ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php
                            echo esc_html__(
                                'Pasarelas registradas',
                                'optigrid-subscriptions'
                            );
                            ?>
                        </th>
                        <td>
                            <strong>
                                <?php echo esc_html((string) count($gateways)); ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php
                            echo esc_html__(
                                'Pasarelas activas',
                                'optigrid-subscriptions'
                            );
                            ?>
                        </th>
                        <td>
                            <strong>
                                <?php
                                echo esc_html(
                                    (string) count($enabled_gateways)
                                );
                                ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php
                            echo esc_html__(
                                'Integración Minecraft',
                                'optigrid-subscriptions'
                            );
                            ?>
                        </th>
                        <td>
                            <?php
                            echo esc_html__(
                                'Preparada mediante entitlements.',
                                'optigrid-subscriptions'
                            );
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="optigrid-subscriptions-card">
            <h2>
                <?php
                echo esc_html__(
                    'Extensiones de pago',
                    'optigrid-subscriptions'
                );
                ?>
            </h2>

            <p>
                <?php
                echo esc_html__(
                    'Las pasarelas son extensiones internas que pueden activarse o desactivarse sin perder su configuración ni su historial.',
                    'optigrid-subscriptions'
                );
                ?>
            </p>

            <?php foreach ($gateways as $gateway) : ?>
                <?php
                $status = $gateway->get_status();
                $gateway_id = $gateway->get_id();
                ?>
                <article class="optigrid-gateway">
                    <div class="optigrid-gateway__header">
                        <div>
                            <h3>
                                <?php echo esc_html($gateway->get_name()); ?>
                            </h3>
                            <p>
                                <?php
                                echo esc_html(
                                    $gateway->get_description()
                                );
                                ?>
                            </p>
                        </div>

                        <span class="optigrid-badge <?php echo !empty($status['enabled']) ? 'is-active' : 'is-inactive'; ?>">
                            <?php
                            echo esc_html(
                                !empty($status['enabled'])
                                    ? __(
                                        'Activada',
                                        'optigrid-subscriptions'
                                    )
                                    : __(
                                        'Desactivada',
                                        'optigrid-subscriptions'
                                    )
                            );
                            ?>
                        </span>
                    </div>

                    <?php if ($gateway->is_test_gateway()) : ?>
                        <div class="notice notice-warning inline">
                            <p>
                                <strong>
                                    <?php
                                    echo esc_html__(
                                        'Solo para pruebas.',
                                        'optigrid-subscriptions'
                                    );
                                    ?>
                                </strong>
                                <?php
                                echo esc_html__(
                                    'No procesa dinero real.',
                                    'optigrid-subscriptions'
                                );
                                ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <form
                        method="post"
                        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                        class="optigrid-gateway__form"
                    >
                        <input
                            type="hidden"
                            name="action"
                            value="<?php
                            echo esc_attr(
                                OptiGrid_Subscriptions_Gateway_Settings_Controller::form_action()
                            );
                            ?>"
                        >

                        <input
                            type="hidden"
                            name="gateway_id"
                            value="<?php echo esc_attr($gateway_id); ?>"
                        >

                        <?php
                        wp_nonce_field(
                            OptiGrid_Subscriptions_Gateway_Settings_Controller::nonce_action(),
                            OptiGrid_Subscriptions_Gateway_Settings_Controller::nonce_name()
                        );
                        ?>

                        <label class="optigrid-toggle">
                            <input
                                type="checkbox"
                                name="enabled"
                                value="1"
                                <?php checked(!empty($status['enabled'])); ?>
                            >
                            <span>
                                <?php
                                echo esc_html__(
                                    'Permitir nuevas operaciones con esta pasarela',
                                    'optigrid-subscriptions'
                                );
                                ?>
                            </span>
                        </label>

                        <?php if ($gateway_id === 'sandbox') : ?>
                            <label for="sandbox-default-scenario">
                                <strong>
                                    <?php
                                    echo esc_html__(
                                        'Resultado predeterminado',
                                        'optigrid-subscriptions'
                                    );
                                    ?>
                                </strong>
                            </label>

                            <select
                                id="sandbox-default-scenario"
                                name="default_scenario"
                            >
                                <?php
                                $scenarios = [
                                    'approved' => __(
                                        'Pago aprobado',
                                        'optigrid-subscriptions'
                                    ),
                                    'rejected' => __(
                                        'Pago rechazado',
                                        'optigrid-subscriptions'
                                    ),
                                    'pending' => __(
                                        'Pago pendiente',
                                        'optigrid-subscriptions'
                                    ),
                                    'cancelled' => __(
                                        'Pago cancelado',
                                        'optigrid-subscriptions'
                                    ),
                                    'technical_error' => __(
                                        'Error técnico',
                                        'optigrid-subscriptions'
                                    ),
                                ];

                                foreach ($scenarios as $value => $label) :
                                    ?>
                                    <option
                                        value="<?php echo esc_attr($value); ?>"
                                        <?php
                                        selected(
                                            $status['default_scenario'],
                                            $value
                                        );
                                        ?>
                                    >
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if ($gateway_id === 'paypal') : ?>
                            <?php
                            $paypal_environment=OptiGrid_Subscriptions_Gateway_Settings::paypal_environment();
                            $paypal_sandbox=OptiGrid_Subscriptions_Gateway_Settings::paypal_environment_settings('sandbox');
                            $paypal_live=OptiGrid_Subscriptions_Gateway_Settings::paypal_environment_settings('live');
                            $paypal_sandbox_ready=OptiGrid_Subscriptions_Gateway_Settings::paypal_environment_complete('sandbox');
                            $paypal_live_ready=OptiGrid_Subscriptions_Gateway_Settings::paypal_environment_complete('live');
                            $paypal_webhook_url=OptiGrid_Subscriptions_PayPal_Webhook_Controller::endpoint_url();
                            ?>
                            <div class="notice <?php echo $paypal_environment === 'live' ? 'notice-error' : 'notice-info'; ?> inline">
                                <p><strong>Entorno activo: <?php echo esc_html($paypal_environment === 'live' ? 'LIVE' : 'SANDBOX'); ?></strong> — <?php echo esc_html($paypal_environment === 'live' ? 'Las nuevas operaciones procesan dinero real.' : 'Las nuevas operaciones son de prueba.'); ?></p>
                            </div>
                            <p><label for="paypal-environment"><strong>Entorno activo</strong></label><br>
                                <select id="paypal-environment" name="environment">
                                    <option value="sandbox" <?php selected($paypal_environment,'sandbox'); ?>>Sandbox — pruebas</option>
                                    <option value="live" <?php selected($paypal_environment,'live'); ?> <?php disabled(!$paypal_live_ready); ?>>Live — dinero real<?php echo !$paypal_live_ready ? ' (configuración incompleta)' : ''; ?></option>
                                </select>
                            </p>
                            <hr><h4>PayPal Sandbox</h4>
                            <p>Estado: <strong><?php echo esc_html($paypal_sandbox_ready ? 'Configurado' : 'Incompleto'); ?></strong></p>
                            <p><label><strong>Client ID Sandbox</strong></label><br><input name="paypal_sandbox_client_id" type="text" class="regular-text" autocomplete="off" value="<?php echo esc_attr($paypal_sandbox['client_id']); ?>"></p>
                            <p><label><strong>Client Secret Sandbox</strong></label><br><input name="paypal_sandbox_client_secret" type="password" class="regular-text" autocomplete="new-password" value="" placeholder="<?php echo esc_attr($paypal_sandbox['client_secret'] !== '' ? 'Configurado — dejar vacío para conservar' : 'Introduce el Client Secret Sandbox'); ?>"></p>
                            <p><label><strong>Webhook ID Sandbox</strong></label><br><input name="paypal_sandbox_webhook_id" type="text" class="regular-text" autocomplete="off" value="<?php echo esc_attr($paypal_sandbox['webhook_id']); ?>"></p>
                            <hr><h4>PayPal Live</h4>
                            <p>Estado: <strong><?php echo esc_html($paypal_live_ready ? 'Configurado' : 'Sin configurar'); ?></strong></p>
                            <p><label><strong>Client ID Live</strong></label><br><input name="paypal_live_client_id" type="text" class="regular-text" autocomplete="off" value="<?php echo esc_attr($paypal_live['client_id']); ?>"></p>
                            <p><label><strong>Client Secret Live</strong></label><br><input name="paypal_live_client_secret" type="password" class="regular-text" autocomplete="new-password" value="" placeholder="<?php echo esc_attr($paypal_live['client_secret'] !== '' ? 'Configurado — dejar vacío para conservar' : 'Introduce el Client Secret Live'); ?>"></p>
                            <p><label><strong>Webhook ID Live</strong></label><br><input name="paypal_live_webhook_id" type="text" class="regular-text" autocomplete="off" value="<?php echo esc_attr($paypal_live['webhook_id']); ?>"></p>
                            <hr>
                            <p><label><strong>Webhook URL de esta instalación</strong></label><br><input type="text" class="large-text code" readonly value="<?php echo esc_attr($paypal_webhook_url); ?>"></p>
                            <p class="description">Sandbox y Live conservan configuraciones independientes. Cambiar de entorno no borra el otro. Live no puede seleccionarse hasta que esté completamente configurado.</p>
                        <?php endif; ?>

                        <p>
                            <button
                                type="submit"
                                class="button button-primary"
                            >
                                <?php
                                echo esc_html__(
                                    'Guardar configuración',
                                    'optigrid-subscriptions'
                                );
                                ?>
                            </button>
                        </p>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    </div>

    <section class="optigrid-subscriptions-card">
        <h2>
            <?php
            echo esc_html__(
                'Tablas del módulo',
                'optigrid-subscriptions'
            );
            ?>
        </h2>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>
                        <?php
                        echo esc_html__(
                            'Componente',
                            'optigrid-subscriptions'
                        );
                        ?>
                    </th>
                    <th>
                        <?php
                        echo esc_html__(
                            'Tabla',
                            'optigrid-subscriptions'
                        );
                        ?>
                    </th>
                    <th>
                        <?php
                        echo esc_html__(
                            'Estado',
                            'optigrid-subscriptions'
                        );
                        ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach (
                    $database_status['tables']
                    as $logical_name => $table
                ) :
                    ?>
                    <tr>
                        <td>
                            <code>
                                <?php echo esc_html($logical_name); ?>
                            </code>
                        </td>
                        <td>
                            <code><?php echo esc_html($table['name']); ?></code>
                        </td>
                        <td>
                            <strong>
                                <?php
                                echo esc_html(
                                    $table['exists']
                                        ? __(
                                            'Disponible',
                                            'optigrid-subscriptions'
                                        )
                                        : __(
                                            'No encontrada',
                                            'optigrid-subscriptions'
                                        )
                                );
                                ?>
                            </strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="optigrid-subscriptions-card optigrid-sandbox-test">
        <h2><?php echo esc_html__('Prueba de ciclo Sandbox', 'optigrid-subscriptions'); ?></h2>
        <p><?php echo esc_html__('Ejecuta el recorrido completo sin dinero real: orden, transacción, suscripción, entitlement y posterior sincronización con Minecraft.', 'optigrid-subscriptions'); ?></p>

        <?php if (is_array($sandbox_result)) : ?>
            <?php if (!empty($sandbox_result['ok'])) : $result = $sandbox_result['data']; ?>
                <div class="notice notice-success inline"><p><strong><?php echo esc_html__('Ciclo ejecutado.', 'optigrid-subscriptions'); ?></strong> <?php echo esc_html((string)$result['message']); ?></p></div>
                <table class="widefat striped optigrid-result-table"><tbody>
                    <tr><th><?php echo esc_html__('Orden', 'optigrid-subscriptions'); ?></th><td>#<?php echo esc_html((string)$result['order_id']); ?> <code><?php echo esc_html((string)$result['public_id']); ?></code></td></tr>
                    <tr><th><?php echo esc_html__('Estado de orden', 'optigrid-subscriptions'); ?></th><td><strong><?php echo esc_html((string)$result['order_status']); ?></strong></td></tr>
                    <tr><th><?php echo esc_html__('Estado del pago', 'optigrid-subscriptions'); ?></th><td><strong><?php echo esc_html((string)$result['payment_status']); ?></strong></td></tr>
                    <tr><th><?php echo esc_html__('Transacción externa', 'optigrid-subscriptions'); ?></th><td><code><?php echo esc_html((string)$result['transaction_external_id']); ?></code></td></tr>
                    <tr><th><?php echo esc_html__('Suscripción', 'optigrid-subscriptions'); ?></th><td><?php echo !empty($result['subscription_id']) ? '#' . esc_html((string)$result['subscription_id']) : esc_html__('No creada', 'optigrid-subscriptions'); ?></td></tr>
                    <tr><th><?php echo esc_html__('Entitlement', 'optigrid-subscriptions'); ?></th><td><?php echo !empty($result['entitlement_id']) ? '#' . esc_html((string)$result['entitlement_id']) : esc_html__('No creado', 'optigrid-subscriptions'); ?></td></tr>
                    <tr><th><?php echo esc_html__('Idempotencia', 'optigrid-subscriptions'); ?></th><td><?php echo !empty($result['idempotent']) ? esc_html__('Solicitud repetida controlada', 'optigrid-subscriptions') : esc_html__('Operación nueva', 'optigrid-subscriptions'); ?></td></tr>
                </tbody></table>
            <?php else : ?>
                <div class="notice notice-error inline"><p><strong><?php echo esc_html__('No se pudo ejecutar el ciclo:', 'optigrid-subscriptions'); ?></strong> <?php echo esc_html((string)($sandbox_result['error'] ?? 'Error desconocido')); ?></p></div>
            <?php endif; ?>
        <?php endif; ?>

        <?php $sandbox_enabled = $gateway_registry->is_enabled('sandbox'); ?>
        <?php if (!$sandbox_enabled) : ?>
            <div class="notice notice-warning inline"><p><?php echo esc_html__('Activa primero la pasarela Sandbox en la sección Extensiones de pago.', 'optigrid-subscriptions'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="optigrid-sandbox-form">
            <input type="hidden" name="action" value="<?php echo esc_attr(OptiGrid_Subscriptions_Sandbox_Checkout_Controller::form_action()); ?>">
            <input type="hidden" name="idempotency_key" value="<?php echo esc_attr('admin-sandbox-' . wp_generate_uuid4()); ?>">
            <?php wp_nonce_field(OptiGrid_Subscriptions_Sandbox_Checkout_Controller::nonce_action(), OptiGrid_Subscriptions_Sandbox_Checkout_Controller::nonce_name()); ?>

            <label><strong><?php echo esc_html__('Usuario', 'optigrid-subscriptions'); ?></strong>
                <select name="user_id" required>
                    <option value=""><?php echo esc_html__('Selecciona un usuario', 'optigrid-subscriptions'); ?></option>
                    <?php foreach ($users as $user) : ?>
                        <option value="<?php echo esc_attr((string)$user->ID); ?>"><?php echo esc_html($user->display_name . ' — ' . $user->user_email . ' (#' . $user->ID . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label><strong><?php echo esc_html__('Plan', 'optigrid-subscriptions'); ?></strong>
                <select name="plan_id" required>
                    <?php foreach ($plans as $plan) : ?>
                        <option value="<?php echo esc_attr((string)$plan['id']); ?>"><?php echo esc_html($plan['name'] . ' — ' . number_format_i18n((float)$plan['price'], 2) . ' ' . $plan['currency'] . ' / ' . $plan['duration_days'] . ' días'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label><strong><?php echo esc_html__('Escenario', 'optigrid-subscriptions'); ?></strong>
                <select name="scenario" required>
                    <option value="approved"><?php echo esc_html__('Aprobado', 'optigrid-subscriptions'); ?></option>
                    <option value="rejected"><?php echo esc_html__('Rechazado', 'optigrid-subscriptions'); ?></option>
                    <option value="pending"><?php echo esc_html__('Pendiente', 'optigrid-subscriptions'); ?></option>
                    <option value="cancelled"><?php echo esc_html__('Cancelado', 'optigrid-subscriptions'); ?></option>
                    <option value="technical_error"><?php echo esc_html__('Error técnico', 'optigrid-subscriptions'); ?></option>
                </select>
            </label>

            <?php submit_button(__('Ejecutar prueba Sandbox', 'optigrid-subscriptions'), 'primary', 'submit', false, $sandbox_enabled ? [] : ['disabled'=>'disabled']); ?>
        </form>
    </section>
</div>
