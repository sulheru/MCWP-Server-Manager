<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contrato común para extensiones internas de pago.
 *
 * Las pasarelas no deciden la lógica de suscripciones ni conceden
 * entitlements directamente. Solo normalizan operaciones y resultados.
 */
interface OptiGrid_Subscriptions_Payment_Gateway_Interface
{
    /**
     * Identificador técnico estable.
     */
    public function get_id(): string;

    /**
     * Nombre visible.
     */
    public function get_name(): string;

    /**
     * Descripción breve para administración.
     */
    public function get_description(): string;

    /**
     * Indica si la implementación puede utilizarse en este entorno.
     */
    public function is_available(): bool;

    /**
     * Indica si es una pasarela de pruebas.
     */
    public function is_test_gateway(): bool;

    /**
     * Devuelve la configuración pública y saneada de la pasarela.
     *
     * @return array<string,mixed>
     */
    public function get_status(): array;

    /**
     * Crea una operación normalizada de pago.
     *
     * Esta etapa todavía no persiste órdenes ni transacciones.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function create_payment(array $context): array;
}
