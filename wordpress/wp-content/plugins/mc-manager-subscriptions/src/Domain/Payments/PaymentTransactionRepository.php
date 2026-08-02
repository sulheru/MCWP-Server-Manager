<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Payment_Transaction_Repository
{
    private string $table;
    public function __construct() { $this->table = OptiGrid_Subscriptions_Database::tables()['transactions']; }

    public function find_by_external(string $gateway, string $external_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE gateway = %s AND external_transaction_id = %s LIMIT 1", $gateway, $external_id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function create(int $order_id, OptiGrid_Subscriptions_Payment_Result $result): int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $ok = $wpdb->insert($this->table, [
            'order_id'=>$order_id,
            'gateway'=>$result->gateway,
            'external_transaction_id'=>$result->external_id,
            'transaction_type'=>'payment',
            'status'=>$result->status,
            'amount'=>$result->amount,
            'currency'=>$result->currency,
            'gateway_message'=>$result->message,
            'processed_at'=>$result->processed_at,
            'created_at'=>$now,
            'updated_at'=>$now,
        ], ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']);
        if ($ok === false) { throw new RuntimeException('No se pudo registrar la transacción: ' . $wpdb->last_error); }
        return (int)$wpdb->insert_id;
    }
}
