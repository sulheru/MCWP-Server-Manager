<?php
/**
 * @var array<string, \OptiGrid\MCManagerServer\Contracts\ModuleInterface> $modules
 * @var \OptiGrid\MCManagerServer\Contracts\ModuleInterface|null $activeModule
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap mcms-dashboard">
    <header class="mcms-dashboard__header">
        <div>
            <h1><?php echo esc_html__('MC Manager Server', 'mc-manager-server'); ?></h1>
            <p class="description">
                <?php echo esc_html__('Dashboard Host de administración de OptiGrid.', 'mc-manager-server'); ?>
            </p>
        </div>
        <span class="mcms-version">
            <?php echo esc_html(sprintf(__('Versión %s', 'mc-manager-server'), MC_MANAGER_SERVER_VERSION)); ?>
        </span>
    </header>

    <?php do_action('mc_manager_server_dashboard_before', $modules, $activeModule); ?>

    <?php if ($modules === [] || $activeModule === null) : ?>
        <section class="mcms-empty-state" role="status">
            <span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
            <h2><?php echo esc_html__('No hay módulos disponibles.', 'mc-manager-server'); ?></h2>
            <p>
                <?php echo esc_html__('El Dashboard Host está operativo y preparado para recibir módulos mediante hooks.', 'mc-manager-server'); ?>
            </p>
        </section>
    <?php else : ?>
        <nav class="nav-tab-wrapper mcms-navigation" aria-label="<?php echo esc_attr__('Módulos del Dashboard', 'mc-manager-server'); ?>">
            <?php foreach ($modules as $module) : ?>
                <?php
                $url = add_query_arg(
                    [
                        'page'   => 'gestor-mc-srv',
                        'module' => $module->id(),
                    ],
                    admin_url('admin.php')
                );
                $isActive = $activeModule->id() === $module->id();
                ?>
                <a
                    class="nav-tab<?php echo $isActive ? ' nav-tab-active' : ''; ?>"
                    href="<?php echo esc_url($url); ?>"
                    <?php echo $isActive ? 'aria-current="page"' : ''; ?>
                >
                    <span class="dashicons dashicons-<?php echo esc_attr($module->icon()); ?>" aria-hidden="true"></span>
                    <?php echo esc_html($module->label()); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <main class="mcms-module" data-module="<?php echo esc_attr($activeModule->id()); ?>">
            <?php do_action('mc_manager_server_module_before', $activeModule); ?>
            <?php $activeModule->render(); ?>
            <?php do_action('mc_manager_server_module_after', $activeModule); ?>
        </main>
    <?php endif; ?>

    <?php do_action('mc_manager_server_dashboard_after', $modules, $activeModule); ?>
</div>
