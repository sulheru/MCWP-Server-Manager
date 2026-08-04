<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$noticeMessages = [
    'saved' => 'Configuración guardada.',
    'checked' => 'Comprobación completada.',
];
?>

<div class="wrap">
    <h1>Actualizaciones de OptiGrid</h1>

    <?php if ($notice !== '' && isset($noticeMessages[$notice])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($noticeMessages[$notice]); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error !== '') : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($error); ?></p>
        </div>
    <?php endif; ?>

    <h2>Canal de distribución</h2>

    <form
        method="post"
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
    >
        <input
            type="hidden"
            name="action"
            value="<?php
            echo esc_attr(
                OptiGrid\MCManagerServer\Updates\GitHubUpdateAdminPage::saveAction()
            );
            ?>"
        >

        <?php
        wp_nonce_field(
            'optigrid_save_github_update_settings'
        );
        ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="optigrid-github-owner">
                            Propietario
                        </label>
                    </th>
                    <td>
                        <input
                            id="optigrid-github-owner"
                            name="owner"
                            type="text"
                            class="regular-text"
                            required
                            value="<?php
                            echo esc_attr($configuration['owner']);
                            ?>"
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="optigrid-github-repository">
                            Repositorio
                        </label>
                    </th>
                    <td>
                        <input
                            id="optigrid-github-repository"
                            name="repository"
                            type="text"
                            class="regular-text"
                            required
                            value="<?php
                            echo esc_attr(
                                $configuration['repository']
                            );
                            ?>"
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="optigrid-github-channel">
                            Rama, etiqueta o canal
                        </label>
                    </th>
                    <td>
                        <input
                            id="optigrid-github-channel"
                            name="channel"
                            type="text"
                            class="regular-text"
                            required
                            value="<?php
                            echo esc_attr($configuration['channel']);
                            ?>"
                        >
                        <p class="description">
                            Laboratorio y piloto pueden usar main.
                            Producción definitiva debería usar una etiqueta
                            o una rama de releases.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="optigrid-github-frequency">
                            Frecuencia
                        </label>
                    </th>
                    <td>
                        <select
                            id="optigrid-github-frequency"
                            name="frequency"
                        >
                            <?php
                            $frequencies = [
                                'hourly' => 'Cada hora',
                                'twicedaily' => 'Dos veces al día',
                                'daily' => 'Una vez al día',
                                'manual' => 'Solo manual',
                            ];

                            foreach ($frequencies as $value => $label) :
                                ?>
                                <option
                                    value="<?php echo esc_attr($value); ?>"
                                    <?php
                                    selected(
                                        $configuration['frequency'],
                                        $value
                                    );
                                    ?>
                                >
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <p class="description">
                            Esta versión conserva el mecanismo periódico
                            nativo de WordPress. El valor queda almacenado
                            para la siguiente etapa de planificación.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="optigrid-github-token">
                            Token opcional
                        </label>
                    </th>
                    <td>
                        <input
                            id="optigrid-github-token"
                            name="token"
                            type="password"
                            class="regular-text"
                            autocomplete="new-password"
                            value=""
                        >

                        <p class="description">
                            Déjalo vacío para conservar el token actual.
                            Un repositorio público no necesita token.
                            También puede definirse mediante
                            OPTIGRID_GITHUB_TOKEN en wp-config.php.
                        </p>

                        <label>
                            <input
                                name="clear_token"
                                type="checkbox"
                                value="1"
                            >
                            Borrar el token almacenado
                        </label>

                        <p>
                            Estado:
                            <strong>
                                <?php
                                echo esc_html(
                                    $configuration['token'] !== ''
                                        ? 'configurado'
                                        : 'no configurado'
                                );
                                ?>
                            </strong>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button('Guardar configuración'); ?>
    </form>

    <hr>

    <h2>Estado de plugins</h2>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Componente</th>
                <th>Instalada</th>
                <th>GitHub</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($statuses as $status) : ?>
                <tr>
                    <td>
                        <strong>
                            <?php echo esc_html($status['name']); ?>
                        </strong>
                    </td>
                    <td>
                        <?php
                        echo esc_html(
                            $status['local'] !== ''
                                ? $status['local']
                                : '—'
                        );
                        ?>
                    </td>
                    <td>
                        <?php
                        echo esc_html(
                            $status['remote'] !== ''
                                ? $status['remote']
                                : '—'
                        );
                        ?>
                    </td>
                    <td>
                        <?php echo esc_html($status['message']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p>
        <a
            class="button button-primary"
            href="<?php
            echo esc_url(
                wp_nonce_url(
                    add_query_arg(
                        [
                            'action' => OptiGrid\MCManagerServer\Updates\GitHubUpdateAdminPage::checkAction(),
                        ],
                        admin_url('admin-post.php')
                    ),
                    'optigrid_check_github_updates'
                )
            );
            ?>"
        >
            Comprobar ahora
        </a>

        <a
            class="button"
            href="<?php echo esc_url(admin_url('plugins.php')); ?>"
        >
            Abrir Plugins
        </a>
    </p>

    <div class="notice notice-info inline">
        <p>
            Gateway y sync-worker continúan actualizándose mediante
            Git y reconstrucción de Docker.
        </p>
    </div>
</div>
