<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrato común para extensiones internas de pago.
 *
 * Las pasarelas no deciden la lógica de suscripciones ni conceden
 * entitlements directamente. Solo inician checkouts y normalizan
 * resultados financieros.
 */
interface OptiGrid_Subscriptions_Payment_Gateway_Interface
{
    public function get_id(): string;

    public function get_name(): string;

    public function get_description(): string;

    public function is_available(): bool;

    public function is_test_gateway(): bool;

    /**
     * @return array<string,mixed>
     */
    public function get_status(): array;

    /**
     * Inicia el checkout de una orden ya persistida.
     *
     * Debe devolver al menos:
     * - gateway
     * - status
     * - redirect_url
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function create_checkout(array $context): array;

    /**
     * Produce un resultado financiero normalizado.
     *
     * Sandbox lo utiliza para sus escenarios simulados. Las pasarelas
     * externas pueden resolver el resultado mediante callbacks/webhooks.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function create_payment(array $context): array;
}
