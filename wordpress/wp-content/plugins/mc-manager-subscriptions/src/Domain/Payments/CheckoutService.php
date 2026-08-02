<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Checkout_Service
{
    private OptiGrid_Subscriptions_Gateway_Registry $gateways;
    private OptiGrid_Subscriptions_Plan_Repository $plans;
    private OptiGrid_Subscriptions_Payment_Order_Repository $orders;
    private OptiGrid_Subscriptions_Payment_Result_Processor $processor;

    public function __construct($gateways,$plans,$orders,$processor)
    { $this->gateways=$gateways; $this->plans=$plans; $this->orders=$orders; $this->processor=$processor; }

    public function checkout(int $user_id, int $plan_id, string $gateway_id, string $scenario, string $idempotency_key): array
    {
        if ($user_id < 1 || get_userdata($user_id) === false) { throw new InvalidArgumentException('El usuario seleccionado no existe.'); }
        $plan=$this->plans->find($plan_id);
        if ($plan === null || empty($plan['is_active'])) { throw new InvalidArgumentException('El plan seleccionado no está disponible.'); }
        $gateway=$this->gateways->get_enabled($gateway_id);
        $idempotency_key=sanitize_text_field($idempotency_key);
        if ($idempotency_key === '') { throw new InvalidArgumentException('Falta la clave de idempotencia.'); }

        $existing=$this->orders->find_by_idempotency_key($idempotency_key);
        if ($existing !== null) {
            return [
                'order_id'=>(int)$existing['id'],'public_id'=>$existing['public_id'],'order_status'=>$existing['status'],
                'payment_status'=>'not_repeated','transaction_external_id'=>'','subscription_id'=>!empty($existing['subscription_id'])?(int)$existing['subscription_id']:null,
                'entitlement_id'=>null,'message'=>'La solicitud ya había sido ejecutada; se devolvió la orden existente.','idempotent'=>true,
            ];
        }

        $order_id=$this->orders->create([
            'idempotency_key'=>$idempotency_key,'user_id'=>$user_id,'plan_id'=>$plan_id,'gateway'=>$gateway_id,
            'amount'=>$plan['price'],'currency'=>$plan['currency'],
        ]);
        $order=$this->orders->find($order_id);
        if ($order === null) { throw new RuntimeException('La orden fue creada, pero no pudo recuperarse.'); }

        $raw=$gateway->create_payment([
            'order_id'=>$order_id,'public_id'=>$order['public_id'],'user_id'=>$user_id,'plan_id'=>$plan_id,
            'amount'=>$plan['price'],'currency'=>$plan['currency'],'scenario'=>$scenario,
        ]);
        $result=new OptiGrid_Subscriptions_Payment_Result($raw);
        return $this->processor->process($order,$plan,$result);
    }
}
