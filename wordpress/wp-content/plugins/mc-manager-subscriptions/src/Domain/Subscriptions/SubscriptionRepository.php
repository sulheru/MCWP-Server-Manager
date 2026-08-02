<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Subscription_Repository
{
    private string $table;
    public function __construct() { $this->table = OptiGrid_Subscriptions_Database::tables()['subscriptions']; }

    public function create_active(int $user_id, int $plan_id, int $duration_days): array
    {
        global $wpdb;
        $start = current_time('mysql', true);
        $end = gmdate('Y-m-d H:i:s', strtotime($start . ' +' . max(1,$duration_days) . ' days'));
        $ok=$wpdb->insert($this->table, [
            'user_id'=>$user_id,'plan_id'=>$plan_id,'status'=>'active','starts_at'=>$start,'ends_at'=>$end,
            'cancelled_at'=>null,'cancellation_reason'=>null,'created_at'=>$start,'updated_at'=>$start,
        ], ['%d','%d','%s','%s','%s','%s','%s','%s','%s']);
        if ($ok === false) { throw new RuntimeException('No se pudo crear la suscripción: ' . $wpdb->last_error); }
        return ['id'=>(int)$wpdb->insert_id,'starts_at'=>$start,'ends_at'=>$end];
    }

    public function active_for_user(int $user_id): array
    {
        global $wpdb;
        $plans=OptiGrid_Subscriptions_Database::tables()['plans'];
        $rows=$wpdb->get_results($wpdb->prepare("SELECT s.*,p.name AS plan_name,p.code AS plan_code FROM {$this->table} s INNER JOIN {$plans} p ON p.id=s.plan_id WHERE s.user_id=%d AND s.status='active' AND s.starts_at<=UTC_TIMESTAMP() AND (s.ends_at IS NULL OR s.ends_at>UTC_TIMESTAMP()) ORDER BY s.ends_at DESC,s.id DESC",$user_id),ARRAY_A);
        return is_array($rows)?$rows:[];
    }

}
