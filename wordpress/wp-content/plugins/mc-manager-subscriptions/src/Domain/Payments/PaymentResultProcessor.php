<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Payment_Result_Processor
{
    private OptiGrid_Subscriptions_Payment_Order_Repository $orders;
    private OptiGrid_Subscriptions_Payment_Transaction_Repository $transactions;
    private OptiGrid_Subscriptions_Payment_Event_Repository $events;
    private OptiGrid_Subscriptions_Subscription_Repository $subscriptions;
    private OptiGrid_Subscriptions_Entitlement_Repository $entitlements;

    public function __construct($orders,$transactions,$events,$subscriptions,$entitlements)
    {
        $this->orders=$orders; $this->transactions=$transactions; $this->events=$events; $this->subscriptions=$subscriptions; $this->entitlements=$entitlements;
    }

    public function process(array $order, array $plan, OptiGrid_Subscriptions_Payment_Result $result): array
    {
        global $wpdb;
        $existing_event=$this->events->find($result->gateway, $result->event_id());
        if ($existing_event !== null && $existing_event['status'] === 'processed') {
            return $this->summary((int)$order['id'], $result, true);
        }

        $wpdb->query('START TRANSACTION');
        $event_id=0;
        try {
            if ($existing_event === null) { $event_id=$this->events->create($result); }
            else { $event_id=(int)$existing_event['id']; }

            if ($this->transactions->find_by_external($result->gateway, $result->external_id) === null) {
                $this->transactions->create((int)$order['id'], $result);
            }

            $order_status = [
                'approved'=>'paid','rejected'=>'failed','pending'=>'pending','cancelled'=>'cancelled','error'=>'failed'
            ][$result->status];

            $subscription_id=null;
            $entitlement_id=null;
            if ($result->status === 'approved') {
                if (!empty($order['subscription_id'])) {
                    $subscription_id=(int)$order['subscription_id'];
                } else {
                    $subscription=$this->subscriptions->create_active((int)$order['user_id'], (int)$order['plan_id'], (int)$plan['duration_days']);
                    $subscription_id=(int)$subscription['id'];
                    $entitlement_id=$this->entitlements->ensure_for_subscription((int)$order['user_id'], $subscription_id, $subscription['starts_at'], $subscription['ends_at']);
                }
            }

            $this->orders->update_result((int)$order['id'], $order_status, $subscription_id);
            $this->events->mark_processed($event_id);
            $wpdb->query('COMMIT');

            return [
                'order_id'=>(int)$order['id'], 'public_id'=>$order['public_id'], 'order_status'=>$order_status,
                'payment_status'=>$result->status, 'transaction_external_id'=>$result->external_id,
                'subscription_id'=>$subscription_id, 'entitlement_id'=>$entitlement_id,
                'message'=>$result->message, 'idempotent'=>false,
            ];
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            if ($event_id > 0) { $this->events->mark_failed($event_id, $e->getMessage()); }
            throw $e;
        }
    }

    private function summary(int $order_id, OptiGrid_Subscriptions_Payment_Result $result, bool $idempotent): array
    {
        $order=$this->orders->find($order_id);
        return [
            'order_id'=>$order_id,'public_id'=>$order['public_id'] ?? '','order_status'=>$order['status'] ?? '',
            'payment_status'=>$result->status,'transaction_external_id'=>$result->external_id,
            'subscription_id'=>isset($order['subscription_id']) ? (int)$order['subscription_id'] : null,
            'entitlement_id'=>null,'message'=>'Evento ya procesado; no se duplicó ninguna operación.','idempotent'=>$idempotent,
        ];
    }
}
