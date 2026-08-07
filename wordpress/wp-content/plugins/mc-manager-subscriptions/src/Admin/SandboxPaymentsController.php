<?php

declare(strict_types=1); if(!defined('ABSPATH')){exit;}
final class OptiGrid_Subscriptions_Sandbox_Payments_Controller
{
    private const PAGE='optigrid-sandbox-payments'; private const ACTION='optigrid_sandbox_admin_process';
    private OptiGrid_Subscriptions_Payment_Order_Repository $orders; private OptiGrid_Subscriptions_Checkout_Service $checkout;
    public function __construct($orders,$checkout){$this->orders=$orders;$this->checkout=$checkout;}
    public function register():void{add_action('admin_menu',[$this,'menu'],50);add_action('admin_post_'.self::ACTION,[$this,'process']);}
    public function menu():void{add_submenu_page('gestor-mc-srv','Pagos Sandbox','Pagos Sandbox','manage_options',self::PAGE,[$this,'render'],50);}
    public function render():void{if(!current_user_can('manage_options')){wp_die('Sin permisos.');}$status=isset($_GET['status'])?sanitize_key(wp_unslash($_GET['status'])):'pending';if(!in_array($status,['pending','paid','failed','cancelled','all'],true)){$status='pending';}$orders=$this->orders->sandbox_orders($status,200);require OPTIGRID_SUBSCRIPTIONS_DIR.'templates/admin/sandbox-payments.php';}
    public function process():void{if(!current_user_can('manage_options')){wp_die('Sin permisos.');}$public_id=isset($_POST['order'])?sanitize_text_field(wp_unslash($_POST['order'])):'';check_admin_referer('sandbox_admin_'.$public_id);$scenario=isset($_POST['scenario'])?sanitize_key(wp_unslash($_POST['scenario'])):'pending';$this->checkout->process_order($public_id,$scenario);wp_safe_redirect(add_query_arg(['page'=>self::PAGE,'status'=>'pending','updated'=>'1'],admin_url('admin.php')));exit;}
    public static function action():string{return self::ACTION;}
}
