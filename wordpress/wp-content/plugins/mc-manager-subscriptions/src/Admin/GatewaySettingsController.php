<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Gateway_Settings_Controller
{
    private const ACTION='optigrid_subscriptions_save_gateways';
    private const NONCE_ACTION='optigrid_subscriptions_gateways';
    private const NONCE_NAME='optigrid_subscriptions_gateways_nonce';

    public function __construct(private OptiGrid_Subscriptions_Gateway_Registry $registry) {}

    public function register(): void
    {
        add_action('admin_post_'.self::ACTION,[$this,'handle_save']);
    }

    public function handle_save(): void
    {
        if(!current_user_can('manage_options')){wp_die('Permisos insuficientes.');}
        check_admin_referer(self::NONCE_ACTION,self::NONCE_NAME);
        $gateway_id=isset($_POST['gateway_id'])?sanitize_key(wp_unslash($_POST['gateway_id'])):'';
        if(!$this->registry->has($gateway_id)){$this->redirect('unknown_gateway',$gateway_id);}

        $settings=['enabled'=>isset($_POST['enabled'])];

        if($gateway_id==='sandbox'){
            $scenario=isset($_POST['default_scenario'])?sanitize_key(wp_unslash($_POST['default_scenario'])):'approved';
            $allowed=['approved','rejected','pending','cancelled','technical_error'];
            $settings['default_scenario']=in_array($scenario,$allowed,true)?$scenario:'approved';
        }

        if($gateway_id==='paypal'){
            $current=OptiGrid_Subscriptions_Gateway_Settings::for_gateway('paypal');
            $environment=isset($_POST['environment'])?sanitize_key(wp_unslash($_POST['environment'])):'sandbox';
            if(!in_array($environment,['sandbox','live'],true)){$environment='sandbox';}

            $settings=[
                'enabled'=>isset($_POST['enabled']),
                'environment'=>$environment,
                'sandbox'=>$this->paypal_env_from_post('sandbox',is_array($current['sandbox']??null)?$current['sandbox']:[]),
                'live'=>$this->paypal_env_from_post('live',is_array($current['live']??null)?$current['live']:[]),
            ];

            if(!$this->complete($settings[$environment])){
                $this->redirect('paypal_environment_incomplete',$gateway_id);
            }
        }

        OptiGrid_Subscriptions_Gateway_Settings::save_gateway($gateway_id,$settings);
        $safe=$settings;
        if($gateway_id==='paypal'){
            foreach(['sandbox','live'] as $env){
                $configured=!empty($safe[$env]['client_secret']);
                unset($safe[$env]['client_secret']);
                $safe[$env]['client_secret_configured']=$configured;
            }
        }
        do_action('optigrid_subscriptions_gateway_settings_saved',$gateway_id,$safe);
        $this->redirect('saved',$gateway_id);
    }

    private function paypal_env_from_post(string $env,array $current): array
    {
        $prefix='paypal_'.$env.'_';
        $out=[];
        foreach(['client_id','client_secret','webhook_id'] as $k){
            $name=$prefix.$k;
            $value=isset($_POST[$name])?sanitize_text_field(wp_unslash($_POST[$name])):'';
            if($value===''){$value=(string)($current[$k]??'');}
            $out[$k]=$value;
        }
        return $out;
    }

    private function complete(array $s): bool
    {
        return trim((string)($s['client_id']??''))!=='' && trim((string)($s['client_secret']??''))!=='' && trim((string)($s['webhook_id']??''))!=='';
    }

    private function redirect(
        string $status,
        string $gateway_id = ''
    ): void {
        $args = [
            'page' => 'optigrid-subscriptions',
            'gateway_updated' => sanitize_key($status),
        ];

        $gateway_id = sanitize_key($gateway_id);

        if ($gateway_id !== '') {
            $args['gateway'] = $gateway_id;
        }

        wp_safe_redirect(
            add_query_arg(
                $args,
                admin_url('admin.php')
            )
        );

        exit;
    }

    public static function nonce_action(): string{return self::NONCE_ACTION;}
    public static function nonce_name(): string{return self::NONCE_NAME;}
    public static function form_action(): string{return self::ACTION;}
}
