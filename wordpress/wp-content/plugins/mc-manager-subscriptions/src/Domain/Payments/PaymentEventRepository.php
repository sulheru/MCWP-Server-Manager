<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Payment_Event_Repository
{
    private string $table;
    public function __construct() { $this->table = OptiGrid_Subscriptions_Database::tables()['events']; }

    public function find(string $gateway, string $event_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE gateway = %s AND event_id = %s LIMIT 1", $gateway, $event_id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function create(OptiGrid_Subscriptions_Payment_Result $result): int
    {
        global $wpdb;
        $payload = wp_json_encode($result->to_payload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) { throw new RuntimeException('No se pudo serializar el evento de pago.'); }
        $ok = $wpdb->insert($this->table, [
            'gateway'=>$result->gateway,
            'event_id'=>$result->event_id(),
            'event_type'=>$result->event_type(),
            'payload_hash'=>hash('sha256', $payload),
            'payload'=>$payload,
            'status'=>'received',
            'error_message'=>null,
            'received_at'=>current_time('mysql', true),
            'processed_at'=>null,
        ], ['%s','%s','%s','%s','%s','%s','%s','%s','%s']);
        if ($ok === false) { throw new RuntimeException('No se pudo registrar el evento: ' . $wpdb->last_error); }
        return (int)$wpdb->insert_id;
    }

    public function mark_processed(int $id): void
    {
        global $wpdb;
        $ok=$wpdb->update($this->table, ['status'=>'processed','processed_at'=>current_time('mysql', true),'error_message'=>null], ['id'=>$id], ['%s','%s','%s'], ['%d']);
        if ($ok === false) { throw new RuntimeException('No se pudo cerrar el evento: ' . $wpdb->last_error); }
    }

    public function mark_failed(int $id, string $message): void
    {
        global $wpdb;
        $wpdb->update($this->table, ['status'=>'failed','processed_at'=>current_time('mysql', true),'error_message'=>$message], ['id'=>$id], ['%s','%s','%s'], ['%d']);
    }
}
