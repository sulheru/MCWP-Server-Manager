<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_PayPal_Reconciler
{
    private const HOOK='optigrid_subscriptions_paypal_reconcile';
    private const SCHEDULE='optigrid_five_minutes';

    public function __construct(
        private OptiGrid_Subscriptions_Payment_Order_Repository $orders,
        private OptiGrid_Subscriptions_Checkout_Service $checkout,
        private OptiGrid_Subscriptions_PayPal_Gateway $paypal
    ) {}

    public function register(): void
    {
        add_filter('cron_schedules',[self::class,'cron_schedules']);
        add_action(self::HOOK,[$this,'run']);
        if(!wp_next_scheduled(self::HOOK)){
            wp_schedule_event(time()+60,self::SCHEDULE,self::HOOK);
        }
    }

    public static function cron_schedules(array $schedules): array
    {
        $schedules[self::SCHEDULE]=['interval'=>5*MINUTE_IN_SECONDS,'display'=>'OptiGrid cada 5 minutos'];
        return $schedules;
    }

    public static function unschedule(): void
    {
        $timestamp=wp_next_scheduled(self::HOOK);
        while($timestamp!==false){
            wp_unschedule_event($timestamp,self::HOOK);
            $timestamp=wp_next_scheduled(self::HOOK);
        }
    }

    public function run(): void
    {
        if(!OptiGrid_Subscriptions_Gateway_Settings::is_enabled('paypal')){return;}
        foreach($this->orders->paypal_pending(25) as $order){
            try{
                $raw=$this->paypal->reconcile($order);
                if($raw===null){continue;}
                $this->checkout->process_external_result((string)$order['public_id'],$raw);
            } catch(Throwable $e){
                error_log('[OptiGrid PayPal Reconcile] order='.(string)$order['id'].' '.$e->getMessage());
            }
        }
    }
}
