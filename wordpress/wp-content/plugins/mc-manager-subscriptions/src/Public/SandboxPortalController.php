<?php

declare(strict_types=1); if (!defined('ABSPATH')) { exit; }
final class OptiGrid_Subscriptions_Sandbox_Portal_Controller
{
    private const PAGE_ACTION='optigrid_sandbox_portal';
    private const PROCESS_ACTION='optigrid_sandbox_process';
    private OptiGrid_Subscriptions_Payment_Order_Repository $orders;
    private OptiGrid_Subscriptions_Plan_Repository $plans;
    private OptiGrid_Subscriptions_Checkout_Service $checkout;
    public function __construct($orders,$plans,$checkout){$this->orders=$orders;$this->plans=$plans;$this->checkout=$checkout;}
    public function register():void{add_action('admin_post_'.self::PAGE_ACTION,[$this,'render']);add_action('admin_post_'.self::PROCESS_ACTION,[$this,'process']);}
    public static function portal_url(string $public_id):string{return add_query_arg(['action'=>self::PAGE_ACTION,'order'=>$public_id,'token'=>wp_create_nonce('sandbox_portal_'.$public_id)],admin_url('admin-post.php'));}
    public function render():void
    {
        if(!is_user_logged_in()){auth_redirect();}
        $public_id=isset($_GET['order'])?sanitize_text_field(wp_unslash($_GET['order'])):'';
        $token=isset($_GET['token'])?sanitize_text_field(wp_unslash($_GET['token'])):'';
        if(!wp_verify_nonce($token,'sandbox_portal_'.$public_id)){wp_die('Enlace Sandbox no válido.');}
        $order=$this->orders->find_by_public_id_for_user($public_id,get_current_user_id());
        if($order===null||$order['gateway']!=='sandbox'){wp_die('Orden Sandbox no encontrada.');}
        $plan=$this->plans->find((int)$order['plan_id']);
        $result=isset($_GET['result'])?sanitize_key(wp_unslash($_GET['result'])):'';
        require OPTIGRID_SUBSCRIPTIONS_DIR.'templates/public/sandbox-portal.php'; exit;
    }
    public function process():void
    {
        if(!is_user_logged_in()){auth_redirect();}
        $public_id=isset($_POST['order'])?sanitize_text_field(wp_unslash($_POST['order'])):'';
        check_admin_referer('sandbox_process_'.$public_id);
        $order=$this->orders->find_by_public_id_for_user($public_id,get_current_user_id());
        if($order===null||$order['gateway']!=='sandbox'){wp_die('Orden Sandbox no encontrada.');}
        $scenario=isset($_POST['scenario'])?sanitize_key(wp_unslash($_POST['scenario'])):'pending';
        $allowed=['approved','rejected','pending','cancelled','technical_error'];
        if(!in_array($scenario,$allowed,true)){$scenario='pending';}
        $this->checkout->process_order($public_id,$scenario);
        wp_safe_redirect(add_query_arg(['action'=>self::PAGE_ACTION,'order'=>$public_id,'token'=>wp_create_nonce('sandbox_portal_'.$public_id),'result'=>$scenario],admin_url('admin-post.php')));exit;
    }
    public static function process_action():string{return self::PROCESS_ACTION;}
}
