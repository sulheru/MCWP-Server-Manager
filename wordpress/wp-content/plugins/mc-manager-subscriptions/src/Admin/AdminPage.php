<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Admin_Page
{
    private OptiGrid_Subscriptions_Gateway_Registry $gateway_registry;
    private OptiGrid_Subscriptions_Plan_Repository $plans;
    public function __construct($gateway_registry,$plans) { $this->gateway_registry=$gateway_registry; $this->plans=$plans; }

    public function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die(esc_html__('No tienes permisos suficientes para acceder a esta página.','optigrid-subscriptions')); }
        $this->plans->ensure_sandbox_plan();
        $template=OPTIGRID_SUBSCRIPTIONS_DIR . 'templates/admin/dashboard.php';
        if (!is_readable($template)) { wp_die(esc_html__('No se encuentra la plantilla administrativa de Suscripciones.','optigrid-subscriptions')); }
        $gateway_registry=$this->gateway_registry;
        $plans=$this->plans->active();
        $users=get_users(['number'=>200,'orderby'=>'display_name','order'=>'ASC','fields'=>['ID','display_name','user_email']]);
        $sandbox_result=get_transient('optigrid_sandbox_result_' . get_current_user_id());
        if ($sandbox_result !== false) { delete_transient('optigrid_sandbox_result_' . get_current_user_id()); }
        require $template;
    }
}
