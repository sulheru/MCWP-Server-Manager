<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/*
 * Política de conservación de datos:
 *
 * La desinstalación del plugin no elimina automáticamente:
 *
 * - planes;
 * - órdenes;
 * - transacciones;
 * - eventos;
 * - suscripciones;
 * - entitlements.
 *
 * Los datos económicos y de auditoría deben conservarse hasta que
 * exista una política explícita de eliminación.
 */
