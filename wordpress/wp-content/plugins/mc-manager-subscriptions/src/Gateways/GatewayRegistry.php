<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registro central de extensiones internas de pago.
 */
final class OptiGrid_Subscriptions_Gateway_Registry
{
    /**
     * @var array<string,OptiGrid_Subscriptions_Payment_Gateway_Interface>
     */
    private array $gateways = [];

    public function register(
        OptiGrid_Subscriptions_Payment_Gateway_Interface $gateway
    ): void {
        $gateway_id = sanitize_key($gateway->get_id());

        if ($gateway_id === '') {
            throw new InvalidArgumentException(
                'La pasarela debe declarar un identificador válido.'
            );
        }

        if (isset($this->gateways[$gateway_id])) {
            throw new RuntimeException(
                sprintf(
                    'La pasarela "%s" ya está registrada.',
                    $gateway_id
                )
            );
        }

        $this->gateways[$gateway_id] = $gateway;
    }

    /**
     * Permite que futuras extensiones internas registren pasarelas.
     */
    public function register_extensions(): void
    {
        /**
         * Las extensiones reciben el registro y pueden llamar a register().
         */
        do_action('optigrid_subscriptions_register_gateways', $this);
    }

    /**
     * @return array<string,OptiGrid_Subscriptions_Payment_Gateway_Interface>
     */
    public function all(): array
    {
        return $this->gateways;
    }

    public function has(string $gateway_id): bool
    {
        return isset($this->gateways[sanitize_key($gateway_id)]);
    }

    public function get(
        string $gateway_id
    ): OptiGrid_Subscriptions_Payment_Gateway_Interface {
        $gateway_id = sanitize_key($gateway_id);

        if (!$this->has($gateway_id)) {
            throw new OutOfBoundsException(
                sprintf(
                    'La pasarela "%s" no está registrada.',
                    $gateway_id
                )
            );
        }

        return $this->gateways[$gateway_id];
    }

    public function is_enabled(string $gateway_id): bool
    {
        return $this->has($gateway_id)
            && OptiGrid_Subscriptions_Gateway_Settings::is_enabled(
                $gateway_id
            );
    }

    /**
     * @return array<string,OptiGrid_Subscriptions_Payment_Gateway_Interface>
     */
    public function enabled(): array
    {
        return array_filter(
            $this->gateways,
            fn (
                OptiGrid_Subscriptions_Payment_Gateway_Interface $gateway
            ): bool => $this->is_enabled($gateway->get_id())
                && $gateway->is_available()
        );
    }

    public function get_enabled(
        string $gateway_id
    ): OptiGrid_Subscriptions_Payment_Gateway_Interface {
        $gateway = $this->get($gateway_id);

        if (!$this->is_enabled($gateway_id)) {
            throw new RuntimeException(
                sprintf(
                    'La pasarela "%s" está desactivada.',
                    $gateway_id
                )
            );
        }

        if (!$gateway->is_available()) {
            throw new RuntimeException(
                sprintf(
                    'La pasarela "%s" no está disponible.',
                    $gateway_id
                )
            );
        }

        return $gateway;
    }
}
