<?php

declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Plan_Repository
{
    private string $table;

    public function __construct()
    {
        $this->table = OptiGrid_Subscriptions_Database::tables()['plans'];
    }

    public function ensure_sandbox_plan(): int
    {
        global $wpdb;
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE code = %s LIMIT 1",
            'sandbox-30'
        ));
        if ($existing !== null) { return (int) $existing; }

        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table, [
            'code' => 'sandbox-30',
            'name' => 'Plan Sandbox 30 días',
            'description' => 'Plan interno para validar el ciclo completo de pago, suscripción y acceso a Minecraft.',
            'price' => '10.00',
            'currency' => 'EUR',
            'duration_days' => 30,
            'is_active' => 1,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s','%s','%s','%s','%s','%d','%d','%d','%s','%s']);
        if ($ok === false) { throw new RuntimeException('No se pudo crear el plan Sandbox: ' . $wpdb->last_error); }
        return (int) $wpdb->insert_id;
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function active(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY sort_order ASC, id ASC", ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
}
