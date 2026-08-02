<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pasarela interna para probar el ciclo completo sin dinero real.
 */
final class OptiGrid_Subscriptions_Sandbox_Gateway implements
    OptiGrid_Subscriptions_Payment_Gateway_Interface
{
    private const SCENARIOS = [
        'approved',
        'rejected',
        'pending',
        'cancelled',
        'technical_error',
    ];

    public function get_id(): string
    {
        return 'sandbox';
    }

    public function get_name(): string
    {
        return __('Sandbox', 'optigrid-subscriptions');
    }

    public function get_description(): string
    {
        return __(
            'Pasarela de pruebas para validar pedidos, pagos, suscripciones y entitlements sin mover dinero real.',
            'optigrid-subscriptions'
        );
    }

    public function is_available(): bool
    {
        return true;
    }

    public function is_test_gateway(): bool
    {
        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function get_status(): array
    {
        $settings = OptiGrid_Subscriptions_Gateway_Settings::for_gateway(
            $this->get_id()
        );

        return [
            'id'               => $this->get_id(),
            'name'             => $this->get_name(),
            'enabled'          => !empty($settings['enabled']),
            'available'        => $this->is_available(),
            'test_gateway'     => true,
            'default_scenario' => $this->sanitize_scenario(
                (string) ($settings['default_scenario'] ?? 'approved')
            ),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function create_payment(array $context): array
    {
        $scenario = isset($context['scenario'])
            ? $this->sanitize_scenario((string) $context['scenario'])
            : $this->get_default_scenario();

        $amount = isset($context['amount'])
            ? (string) $context['amount']
            : '0.00';

        $currency = isset($context['currency'])
            ? strtoupper(sanitize_key((string) $context['currency']))
            : 'EUR';

        $operation_id = wp_generate_uuid4();

        $result = [
            'gateway'                => $this->get_id(),
            'external_operation_id'  => 'sandbox-' . $operation_id,
            'scenario'               => $scenario,
            'status'                 => 'pending',
            'amount'                 => $amount,
            'currency'               => $currency,
            'message'                => '',
            'processed_at'           => current_time('mysql', true),
            'raw'                    => [],
        ];

        switch ($scenario) {
            case 'approved':
                $result['status'] = 'approved';
                $result['message'] = __(
                    'Pago aprobado por Sandbox.',
                    'optigrid-subscriptions'
                );
                break;

            case 'rejected':
                $result['status'] = 'rejected';
                $result['message'] = __(
                    'Pago rechazado por Sandbox.',
                    'optigrid-subscriptions'
                );
                break;

            case 'cancelled':
                $result['status'] = 'cancelled';
                $result['message'] = __(
                    'Pago cancelado por el usuario en Sandbox.',
                    'optigrid-subscriptions'
                );
                break;

            case 'technical_error':
                $result['status'] = 'error';
                $result['message'] = __(
                    'Sandbox simuló un error técnico.',
                    'optigrid-subscriptions'
                );
                break;

            case 'pending':
            default:
                $result['status'] = 'pending';
                $result['message'] = __(
                    'Pago pendiente en Sandbox.',
                    'optigrid-subscriptions'
                );
                break;
        }

        return $result;
    }

    private function get_default_scenario(): string
    {
        $settings = OptiGrid_Subscriptions_Gateway_Settings::for_gateway(
            $this->get_id()
        );

        return $this->sanitize_scenario(
            (string) ($settings['default_scenario'] ?? 'approved')
        );
    }

    private function sanitize_scenario(string $scenario): string
    {
        $scenario = sanitize_key($scenario);

        return in_array($scenario, self::SCENARIOS, true)
            ? $scenario
            : 'approved';
    }
}
