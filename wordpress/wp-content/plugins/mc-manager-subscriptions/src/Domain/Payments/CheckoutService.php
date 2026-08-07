<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Checkout_Service
{
    private OptiGrid_Subscriptions_Gateway_Registry $gateways;
    private OptiGrid_Subscriptions_Plan_Repository $plans;
    private OptiGrid_Subscriptions_Payment_Order_Repository $orders;
    private OptiGrid_Subscriptions_Payment_Result_Processor $processor;

    public function __construct(
        $gateways,
        $plans,
        $orders,
        $processor
    ) {
        $this->gateways = $gateways;
        $this->plans = $plans;
        $this->orders = $orders;
        $this->processor = $processor;
    }

    public function create_order(
        int $user_id,
        int $plan_id,
        string $gateway_id,
        string $idempotency_key
    ): array {
        if (
            $user_id < 1
            || get_userdata($user_id) === false
        ) {
            throw new InvalidArgumentException(
                'El usuario seleccionado no existe.'
            );
        }

        $plan = $this->plans->find($plan_id);

        if (
            $plan === null
            || empty($plan['is_active'])
        ) {
            throw new InvalidArgumentException(
                'El plan seleccionado no está disponible.'
            );
        }

        $this->gateways->get_enabled($gateway_id);

        $idempotency_key =
            sanitize_text_field($idempotency_key);

        if ($idempotency_key === '') {
            throw new InvalidArgumentException(
                'Falta la clave de idempotencia.'
            );
        }

        $existing =
            $this->orders->find_by_idempotency_key(
                $idempotency_key
            );

        if ($existing !== null) {
            return $existing;
        }

        $order_id = $this->orders->create([
            'idempotency_key' => $idempotency_key,
            'user_id' => $user_id,
            'plan_id' => $plan_id,
            'gateway' => $gateway_id,
            'amount' => $plan['price'],
            'currency' => $plan['currency'],
        ]);

        $order = $this->orders->find($order_id);

        if ($order === null) {
            throw new RuntimeException(
                'La orden fue creada, pero no pudo recuperarse.'
            );
        }

        return $order;
    }

    /**
     * Inicia la experiencia propia de la pasarela.
     *
     * @return array<string,mixed>
     */
    public function create_gateway_checkout(
        array $order
    ): array {
        $plan = $this->plans->find(
            (int) $order['plan_id']
        );

        if ($plan === null) {
            throw new RuntimeException(
                'El plan asociado a la orden ya no existe.'
            );
        }

        $gateway = $this->gateways->get_enabled(
            (string) $order['gateway']
        );

        return $gateway->create_checkout([
            'order' => $order,
            'plan' => $plan,
            'user' => get_userdata(
                (int) $order['user_id']
            ),
        ]);
    }

    public function process_order(
        string $public_id,
        string $scenario
    ): array {
        $order =
            $this->orders->find_by_public_id($public_id);

        if ($order === null) {
            throw new InvalidArgumentException(
                'La orden no existe.'
            );
        }

        if ($order['status'] !== 'pending') {
            return [
                'order_id' => (int) $order['id'],
                'public_id' => $order['public_id'],
                'order_status' => $order['status'],
                'payment_status' => 'not_repeated',
                'transaction_external_id' => '',
                'subscription_id' =>
                    !empty($order['subscription_id'])
                        ? (int) $order['subscription_id']
                        : null,
                'entitlement_id' => null,
                'message' =>
                    'La orden ya se encuentra en un estado final.',
                'idempotent' => true,
            ];
        }

        $plan = $this->plans->find(
            (int) $order['plan_id']
        );

        if ($plan === null) {
            throw new RuntimeException(
                'El plan asociado a la orden ya no existe.'
            );
        }

        $gateway = $this->gateways->get_enabled(
            (string) $order['gateway']
        );

        $raw = $gateway->create_payment([
            'order_id' => (int) $order['id'],
            'public_id' => $order['public_id'],
            'user_id' => (int) $order['user_id'],
            'plan_id' => (int) $order['plan_id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'scenario' => $scenario,
        ]);

        return $this->processor->process(
            $order,
            $plan,
            new OptiGrid_Subscriptions_Payment_Result($raw)
        );
    }

    /**
     * Procesa un resultado ya normalizado por una pasarela externa.
     *
     * @param array<string,mixed> $raw
     */
    public function process_external_result(
        string $public_id,
        array $raw
    ): array {
        $order =
            $this->orders->find_by_public_id($public_id);

        if ($order === null) {
            throw new InvalidArgumentException(
                'La orden no existe.'
            );
        }

        $plan = $this->plans->find(
            (int) $order['plan_id']
        );

        if ($plan === null) {
            throw new RuntimeException(
                'El plan asociado a la orden ya no existe.'
            );
        }

        return $this->processor->process(
            $order,
            $plan,
            new OptiGrid_Subscriptions_Payment_Result($raw)
        );
    }

    public function checkout(
        int $user_id,
        int $plan_id,
        string $gateway_id,
        string $scenario,
        string $idempotency_key
    ): array {
        $order = $this->create_order(
            $user_id,
            $plan_id,
            $gateway_id,
            $idempotency_key
        );

        return $this->process_order(
            (string) $order['public_id'],
            $scenario
        );
    }
}
