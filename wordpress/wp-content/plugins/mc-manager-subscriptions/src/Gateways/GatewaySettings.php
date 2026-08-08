<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Gateway_Settings
{
    public const OPTION_NAME='optigrid_subscriptions_gateways';
    private const PAYPAL_ENVIRONMENTS=['sandbox','live'];

    public static function all(): array
    {
        $stored=get_option(self::OPTION_NAME,[]);
        return is_array($stored)?$stored:[];
    }

    public static function for_gateway(string $gateway_id): array
    {
        $all=self::all();
        $settings=$all[sanitize_key($gateway_id)]??[];
        return is_array($settings)?$settings:[];
    }

    public static function is_enabled(string $gateway_id): bool
    {
        return !empty(self::for_gateway($gateway_id)['enabled']);
    }

    public static function save_gateway(string $gateway_id,array $settings): bool
    {
        $gateway_id=sanitize_key($gateway_id);
        if($gateway_id===''){return false;}
        $all=self::all();
        $all[$gateway_id]=$settings;
        return update_option(self::OPTION_NAME,$all,false);
    }

    public static function paypal_environment(): string
    {
        $env=sanitize_key((string)(self::for_gateway('paypal')['environment']??'sandbox'));
        return in_array($env,self::PAYPAL_ENVIRONMENTS,true)?$env:'sandbox';
    }

    public static function paypal_environment_settings(?string $environment=null): array
    {
        $paypal=self::for_gateway('paypal');
        $environment=sanitize_key($environment??self::paypal_environment());
        if(!in_array($environment,self::PAYPAL_ENVIRONMENTS,true)){$environment='sandbox';}
        $s=$paypal[$environment]??[];
        if(!is_array($s)){$s=[];}
        return [
            'client_id'=>(string)($s['client_id']??''),
            'client_secret'=>(string)($s['client_secret']??''),
            'webhook_id'=>(string)($s['webhook_id']??''),
        ];
    }

    public static function paypal_environment_complete(?string $environment=null): bool
    {
        $s=self::paypal_environment_settings($environment);
        return trim($s['client_id'])!=='' && trim($s['client_secret'])!=='' && trim($s['webhook_id'])!=='';
    }

    public static function ensure_defaults(): void
    {
        $all=self::all();
        $changed=false;

        if(!isset($all['sandbox'])||!is_array($all['sandbox'])){
            $all['sandbox']=['enabled'=>false,'default_scenario'=>'approved'];
            $changed=true;
        }

        if(!isset($all['paypal'])||!is_array($all['paypal'])){
            $all['paypal']=[
                'enabled'=>false,
                'environment'=>'sandbox',
                'sandbox'=>['client_id'=>'','client_secret'=>'','webhook_id'=>''],
                'live'=>['client_id'=>'','client_secret'=>'','webhook_id'=>''],
            ];
            $changed=true;
        } else {
            $paypal=$all['paypal'];

            $legacy=array_key_exists('client_id',$paypal)||array_key_exists('client_secret',$paypal)||array_key_exists('webhook_id',$paypal);
            if($legacy){
                if(!isset($paypal['sandbox'])||!is_array($paypal['sandbox'])){$paypal['sandbox']=[];}
                foreach(['client_id','client_secret','webhook_id'] as $k){
                    if(empty($paypal['sandbox'][$k])&&!empty($paypal[$k])){$paypal['sandbox'][$k]=(string)$paypal[$k];}
                    unset($paypal[$k]);
                }
                $paypal['environment']='sandbox';
                $changed=true;
            }

            foreach(self::PAYPAL_ENVIRONMENTS as $env){
                if(!isset($paypal[$env])||!is_array($paypal[$env])){$paypal[$env]=[];$changed=true;}
                foreach(['client_id','client_secret','webhook_id'] as $k){
                    if(!array_key_exists($k,$paypal[$env])){$paypal[$env][$k]='';$changed=true;}
                }
            }

            $env=sanitize_key((string)($paypal['environment']??'sandbox'));
            if(!in_array($env,self::PAYPAL_ENVIRONMENTS,true)){$paypal['environment']='sandbox';$changed=true;}
            if(!array_key_exists('enabled',$paypal)){$paypal['enabled']=false;$changed=true;}
            $all['paypal']=$paypal;
        }

        if($changed){update_option(self::OPTION_NAME,$all,false);}
    }
}
