<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Plugin
{
    private OptiGrid_Subscriptions_Gateway_Registry $gateway_registry;
    private OptiGrid_Subscriptions_Admin_Menu $admin_menu;
    private OptiGrid_Subscriptions_Gateway_Settings_Controller $gateway_settings_controller;
    private OptiGrid_Subscriptions_Sandbox_Checkout_Controller $sandbox_checkout_controller;
    private OptiGrid_Subscriptions_Public_Checkout_Controller $public_checkout_controller;
    private OptiGrid_Subscriptions_Subscription_Manager_Controller $subscription_manager_controller;
    private OptiGrid_Subscriptions_Plan_Manager_Controller $plan_manager_controller;
    private OptiGrid_Subscriptions_Sandbox_Portal_Controller $sandbox_portal_controller;
    private OptiGrid_Subscriptions_Sandbox_Payments_Controller $sandbox_payments_controller;

    public function __construct()
    {
        OptiGrid_Subscriptions_Gateway_Settings::ensure_defaults();
        $this->gateway_registry=new OptiGrid_Subscriptions_Gateway_Registry();
        $this->gateway_registry->register(new OptiGrid_Subscriptions_Sandbox_Gateway());
        $this->gateway_registry->register_extensions();

        $plans=new OptiGrid_Subscriptions_Plan_Repository();
        $orders=new OptiGrid_Subscriptions_Payment_Order_Repository();
        $transactions=new OptiGrid_Subscriptions_Payment_Transaction_Repository();
        $events=new OptiGrid_Subscriptions_Payment_Event_Repository();
        $subscriptions=new OptiGrid_Subscriptions_Subscription_Repository();
        $entitlements=new OptiGrid_Subscriptions_Entitlement_Repository();
        $processor=new OptiGrid_Subscriptions_Payment_Result_Processor($orders,$transactions,$events,$subscriptions,$entitlements);
        $checkout=new OptiGrid_Subscriptions_Checkout_Service($this->gateway_registry,$plans,$orders,$processor);

        $admin_page=new OptiGrid_Subscriptions_Admin_Page($this->gateway_registry,$plans);
        $this->admin_menu=new OptiGrid_Subscriptions_Admin_Menu($admin_page);
        $this->gateway_settings_controller=new OptiGrid_Subscriptions_Gateway_Settings_Controller($this->gateway_registry);
        $this->sandbox_checkout_controller=new OptiGrid_Subscriptions_Sandbox_Checkout_Controller($checkout);
        $this->public_checkout_controller=new OptiGrid_Subscriptions_Public_Checkout_Controller($this->gateway_registry,$plans,$orders,$subscriptions,$entitlements,$checkout);
        $subscription_admin_repository=new OptiGrid_Subscriptions_Subscription_Admin_Repository();
        $subscription_admin_service=new OptiGrid_Subscriptions_Subscription_Admin_Service();
        $this->subscription_manager_controller=new OptiGrid_Subscriptions_Subscription_Manager_Controller($subscription_admin_repository,$subscription_admin_service);
        $this->plan_manager_controller=new OptiGrid_Subscriptions_Plan_Manager_Controller(new OptiGrid_Subscriptions_Plan_Admin_Repository());
        $this->sandbox_portal_controller=new OptiGrid_Subscriptions_Sandbox_Portal_Controller($orders,$plans,$checkout);
        $this->sandbox_payments_controller=new OptiGrid_Subscriptions_Sandbox_Payments_Controller($orders,$checkout);
    }

    public function run(): void
    {
        add_action('admin_init',['OptiGrid_Subscriptions_Database','maybe_upgrade'],5);
        $this->admin_menu->register();
        $this->gateway_settings_controller->register();
        $this->sandbox_checkout_controller->register();
        $this->public_checkout_controller->register();
        $this->subscription_manager_controller->register();
        $this->plan_manager_controller->register();
        $this->sandbox_portal_controller->register();
        $this->sandbox_payments_controller->register();
        do_action('optigrid_subscriptions_gateways_ready',$this->gateway_registry);
    }
}
