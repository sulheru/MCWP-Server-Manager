<?php
/**
 * Plugin Name: MC Manager Server
 * Plugin URI:  https://optigrid-it.com/
 * Description: Dashboard Host extensible para la administración del servidor Minecraft de OptiGrid.
 * Version:     1.6.6
 * Author:      OptiGrid
 * Text Domain: mc-manager-server
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('MC_MANAGER_SERVER_VERSION', '1.6.6');
define('MC_MANAGER_SERVER_FILE', __FILE__);
define('MC_MANAGER_SERVER_PATH', plugin_dir_path(__FILE__));
define('MC_MANAGER_SERVER_URL', plugin_dir_url(__FILE__));

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'OptiGrid\\MCManagerServer\\';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = MC_MANAGER_SERVER_PATH . 'src/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        // BEGIN OPTIGRID E5.8.3.2 WRITE CONTROLLER
        (new \OptiGrid\MCManagerServer\Modules\Server\ServerWriteController())->registerHooks();
        // END OPTIGRID E5.8.3.2 WRITE CONTROLLER
        \OptiGrid\MCManagerServer\Core\Plugin::instance()->boot();
    }
);
