<?php
/**
 * Plugin Name: OptiGrid Subscriptions
 * Plugin URI:  https://github.com/sulheru/MCWP-Server-Manager
 * Description: Gestión de planes, suscripciones, pagos y derechos de acceso para OptiGrid.
 * Version:     0.10.0
 * Author:      OptiGrid
 * Text Domain: optigrid-subscriptions
 */

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

define('OPTIGRID_SUBSCRIPTIONS_VERSION', '0.10.0');
define('OPTIGRID_SUBSCRIPTIONS_DB_VERSION', '1.1.0');
define('OPTIGRID_SUBSCRIPTIONS_FILE', __FILE__);
define('OPTIGRID_SUBSCRIPTIONS_DIR', plugin_dir_path(__FILE__));
define('OPTIGRID_SUBSCRIPTIONS_URL', plugin_dir_url(__FILE__));

$requires = [
'src/Core/Database.php','src/Core/Activator.php',
'src/Gateways/PaymentGatewayInterface.php','src/Gateways/GatewaySettings.php','src/Gateways/GatewayRegistry.php','src/Gateways/SandboxGateway.php','src/Gateways/PayPal/PayPalClient.php','src/Gateways/PayPal/PayPalGateway.php',
'src/Domain/Plans/PlanRepository.php','src/Domain/Plans/PlanAdminRepository.php','src/Domain/Payments/PaymentResult.php','src/Domain/Payments/PaymentOrderRepository.php',
'src/Domain/Payments/PaymentTransactionRepository.php','src/Domain/Payments/PaymentEventRepository.php',
'src/Domain/Subscriptions/SubscriptionRepository.php','src/Domain/Subscriptions/SubscriptionAdminRepository.php','src/Domain/Subscriptions/SubscriptionAdminService.php','src/Domain/Entitlements/EntitlementRepository.php',
'src/Domain/Payments/PaymentResultProcessor.php','src/Domain/Payments/CheckoutService.php',
'src/Admin/GatewaySettingsController.php','src/Admin/SandboxCheckoutController.php','src/Admin/SubscriptionManagerController.php','src/Admin/PlanManagerController.php','src/Public/PublicCheckoutController.php','src/Public/SandboxPortalController.php','src/Public/PayPalReturnController.php','src/Admin/SandboxPaymentsController.php','src/Admin/AdminPage.php','src/Admin/AdminMenu.php','src/Core/Plugin.php',
];
foreach ($requires as $file) { require_once OPTIGRID_SUBSCRIPTIONS_DIR . $file; }
register_activation_hook(OPTIGRID_SUBSCRIPTIONS_FILE, ['OptiGrid_Subscriptions_Activator','activate']);
function optigrid_subscriptions_bootstrap(): void { (new OptiGrid_Subscriptions_Plugin())->run(); }
add_action('plugins_loaded','optigrid_subscriptions_bootstrap');
