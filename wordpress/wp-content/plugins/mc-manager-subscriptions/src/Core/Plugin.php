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
    }

    public function run(): void
    {
        add_action('admin_init',['OptiGrid_Subscriptions_Database','maybe_upgrade'],5);
        $this->admin_menu->register();
        $this->gateway_settings_controller->register();
        $this->sandbox_checkout_controller->register();
        $this->public_checkout_controller->register();
        do_action('optigrid_subscriptions_gateways_ready',$this->gateway_registry);
    }
}
