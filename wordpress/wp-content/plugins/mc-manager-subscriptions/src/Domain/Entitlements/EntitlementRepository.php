<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Entitlement_Repository
{
    private string $table;
    public function __construct() { $this->table = OptiGrid_Subscriptions_Database::tables()['entitlements']; }

    public function ensure_for_subscription(int $user_id, int $subscription_id, string $starts_at, string $ends_at): int
    {
        global $wpdb;
        $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table} WHERE source_type=%s AND source_id=%d AND entitlement_key=%s LIMIT 1", 'subscription', $subscription_id, 'minecraft_access'));
        if ($existing !== null) { return (int)$existing; }
        $now=current_time('mysql', true);
        $ok=$wpdb->insert($this->table, [
            'user_id'=>$user_id,'entitlement_key'=>'minecraft_access','status'=>'active','source_type'=>'subscription','source_id'=>$subscription_id,
            'starts_at'=>$starts_at,'ends_at'=>$ends_at,'revoked_at'=>null,'revocation_reason'=>null,'created_at'=>$now,'updated_at'=>$now,
        ], ['%d','%s','%s','%s','%d','%s','%s','%s','%s','%s','%s']);
        if ($ok === false) { throw new RuntimeException('No se pudo crear el entitlement: ' . $wpdb->last_error); }
        return (int)$wpdb->insert_id;
    }

    public function active_for_user(int $user_id, string $entitlement_key='minecraft_access'): array
    {
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE user_id=%d AND entitlement_key=%s AND status='active' AND starts_at<=UTC_TIMESTAMP() AND (ends_at IS NULL OR ends_at>UTC_TIMESTAMP()) AND revoked_at IS NULL ORDER BY ends_at DESC,id DESC",$user_id,sanitize_key($entitlement_key)),ARRAY_A);
        return is_array($rows)?$rows:[];
    }

}
