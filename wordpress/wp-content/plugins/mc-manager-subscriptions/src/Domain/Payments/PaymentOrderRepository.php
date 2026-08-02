<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Payment_Order_Repository
{
    private string $table;
    public function __construct() { $this->table = OptiGrid_Subscriptions_Database::tables()['orders']; }

    public function find_by_idempotency_key(string $key): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE idempotency_key = %s LIMIT 1", $key), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d LIMIT 1", $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }


    public function find_by_public_id_for_user(string $public_id, int $user_id): ?array
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE public_id=%s AND user_id=%d LIMIT 1", sanitize_text_field($public_id), $user_id), ARRAY_A);
        return is_array($row)?$row:null;
    }

    public function recent_for_user(int $user_id, int $limit=10): array
    {
        global $wpdb;
        $limit=max(1,min(50,$limit));
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE user_id=%d ORDER BY id DESC LIMIT %d", $user_id, $limit), ARRAY_A);
        return is_array($rows)?$rows:[];
    }

    public function create(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table, [
            'public_id' => wp_generate_uuid4(),
            'idempotency_key' => $data['idempotency_key'],
            'user_id' => $data['user_id'],
            'plan_id' => $data['plan_id'],
            'subscription_id' => null,
            'gateway' => $data['gateway'],
            'status' => 'pending',
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'expires_at' => gmdate('Y-m-d H:i:s', time() + HOUR_IN_SECONDS),
            'paid_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s','%s','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s']);
        if ($ok === false) { throw new RuntimeException('No se pudo crear la orden: ' . $wpdb->last_error); }
        return (int)$wpdb->insert_id;
    }

    public function update_result(int $id, string $status, ?int $subscription_id = null): void
    {
        global $wpdb;
        $data = ['status'=>$status, 'updated_at'=>current_time('mysql', true)];
        $formats = ['%s','%s'];
        if ($subscription_id !== null) { $data['subscription_id']=$subscription_id; $formats[]='%d'; }
        if ($status === 'paid') { $data['paid_at']=current_time('mysql', true); $formats[]='%s'; }
        $ok = $wpdb->update($this->table, $data, ['id'=>$id], $formats, ['%d']);
        if ($ok === false) { throw new RuntimeException('No se pudo actualizar la orden: ' . $wpdb->last_error); }
    }
}
