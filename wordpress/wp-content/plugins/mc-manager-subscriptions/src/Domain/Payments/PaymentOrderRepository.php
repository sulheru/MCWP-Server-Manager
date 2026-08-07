<?php

declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Payment_Order_Repository
{
    private string $table;
    public function __construct() { $this->table=OptiGrid_Subscriptions_Database::tables()['orders']; }

    public function find_by_idempotency_key(string $key): ?array
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE idempotency_key=%s LIMIT 1",$key),ARRAY_A);
        return is_array($row)?$row:null;
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id=%d LIMIT 1",$id),ARRAY_A);
        return is_array($row)?$row:null;
    }

    public function find_by_public_id_for_user(string $public_id,int $user_id): ?array
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE public_id=%s AND user_id=%d LIMIT 1",sanitize_text_field($public_id),$user_id),ARRAY_A);
        return is_array($row)?$row:null;
    }

    public function find_by_public_id(string $public_id): ?array
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE public_id=%s LIMIT 1",sanitize_text_field($public_id)),ARRAY_A);
        return is_array($row)?$row:null;
    }

    public function find_by_gateway_reference(string $gateway,string $reference): ?array
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE gateway=%s AND gateway_reference=%s LIMIT 1",sanitize_key($gateway),sanitize_text_field($reference)),ARRAY_A);
        return is_array($row)?$row:null;
    }

    public function sandbox_orders(string $status='pending',int $limit=100): array
    {
        global $wpdb;
        $plans=OptiGrid_Subscriptions_Database::tables()['plans'];
        $status=sanitize_key($status); $limit=max(1,min(500,$limit));
        $where="o.gateway='sandbox'"; $args=[];
        if($status!=='all'){ $where.=" AND o.status=%s"; $args[]=$status; }
        $sql="SELECT o.*, p.name AS plan_name, u.user_login, u.user_email FROM {$this->table} o LEFT JOIN {$plans} p ON p.id=o.plan_id LEFT JOIN {$wpdb->users} u ON u.ID=o.user_id WHERE {$where} ORDER BY o.id DESC LIMIT %d";
        $args[]=$limit;
        $rows=$wpdb->get_results($wpdb->prepare($sql,...$args),ARRAY_A);
        return is_array($rows)?$rows:[];
    }

    public function paypal_pending(int $limit=25): array
    {
        global $wpdb;
        $limit=max(1,min(100,$limit));
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE gateway='paypal' AND status='pending' AND gateway_reference IS NOT NULL AND gateway_reference<>'' ORDER BY id ASC LIMIT %d",$limit),ARRAY_A);
        return is_array($rows)?$rows:[];
    }

    public function recent_for_user(int $user_id,int $limit=10): array
    {
        global $wpdb;
        $limit=max(1,min(50,$limit));
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} WHERE user_id=%d ORDER BY id DESC LIMIT %d",$user_id,$limit),ARRAY_A);
        return is_array($rows)?$rows:[];
    }

    public function create(array $data): int
    {
        global $wpdb; $now=current_time('mysql',true);
        $ok=$wpdb->insert($this->table,[
            'public_id'=>wp_generate_uuid4(),'idempotency_key'=>$data['idempotency_key'],'user_id'=>$data['user_id'],'plan_id'=>$data['plan_id'],
            'subscription_id'=>null,'gateway'=>$data['gateway'],'gateway_reference'=>null,'status'=>'pending','amount'=>$data['amount'],'currency'=>$data['currency'],
            'expires_at'=>gmdate('Y-m-d H:i:s',time()+HOUR_IN_SECONDS),'paid_at'=>null,'created_at'=>$now,'updated_at'=>$now,
        ],['%s','%s','%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s']);
        if($ok===false){throw new RuntimeException('No se pudo crear la orden: '.$wpdb->last_error);}
        return (int)$wpdb->insert_id;
    }

    public function set_gateway_reference(int $id,string $reference): void
    {
        global $wpdb; $reference=sanitize_text_field($reference);
        if($reference===''){throw new InvalidArgumentException('La referencia externa no puede estar vacía.');}
        $ok=$wpdb->update($this->table,['gateway_reference'=>$reference,'updated_at'=>current_time('mysql',true)],['id'=>$id],['%s','%s'],['%d']);
        if($ok===false){throw new RuntimeException('No se pudo guardar la referencia externa: '.$wpdb->last_error);}
    }

    public function update_result(int $id,string $status,?int $subscription_id=null): void
    {
        global $wpdb;
        $data=['status'=>$status,'updated_at'=>current_time('mysql',true)]; $formats=['%s','%s'];
        if($subscription_id!==null){$data['subscription_id']=$subscription_id;$formats[]='%d';}
        if($status==='paid'){$data['paid_at']=current_time('mysql',true);$formats[]='%s';}
        $ok=$wpdb->update($this->table,$data,['id'=>$id],$formats,['%d']);
        if($ok===false){throw new RuntimeException('No se pudo actualizar la orden: '.$wpdb->last_error);}
    }
}
