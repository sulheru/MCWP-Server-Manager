<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class OptiGrid_Subscriptions_Subscription_Admin_Repository
{
    private string $subscriptions_table;
    private string $plans_table;
    private string $orders_table;
    private string $entitlements_table;

    public function __construct()
    {
        $tables = OptiGrid_Subscriptions_Database::tables();
        $this->subscriptions_table = $tables['subscriptions'];
        $this->plans_table = $tables['plans'];
        $this->orders_table = $tables['orders'];
        $this->entitlements_table = $tables['entitlements'];
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,total:int,pages:int,page:int}
     */
    public function search(array $filters, int $page = 1, int $per_page = 20): array
    {
        global $wpdb;

        $page = max(1, $page);
        $per_page = max(1, min(100, $per_page));
        $offset = ($page - 1) * $per_page;

        $where = ['1=1'];
        $params = [];

        $status = sanitize_key((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 's.status = %s';
            $params[] = $status;
        }

        $plan_id = absint($filters['plan_id'] ?? 0);
        if ($plan_id > 0) {
            $where[] = 's.plan_id = %d';
            $params[] = $plan_id;
        }

        $search = sanitize_text_field((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(u.user_login LIKE %s OR u.user_email LIKE %s OR p.name LIKE %s OR CAST(s.id AS CHAR) = %s)';
            array_push($params, $like, $like, $like, $search);
        }

        $where_sql = implode(' AND ', $where);

        $base_from = "FROM {$this->subscriptions_table} s
            INNER JOIN {$wpdb->users} u ON u.ID = s.user_id
            INNER JOIN {$this->plans_table} p ON p.id = s.plan_id";

        $count_sql = "SELECT COUNT(*) {$base_from} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var(
            $params === [] ? $count_sql : $wpdb->prepare($count_sql, ...$params)
        );

        $list_sql = "SELECT
                s.*,
                u.user_login,
                u.user_email,
                p.name AS plan_name,
                p.code AS plan_code,
                p.duration_days,
                o.id AS order_id,
                o.public_id AS order_public_id,
                o.status AS order_status,
                e.id AS entitlement_id,
                e.status AS entitlement_status,
                e.ends_at AS entitlement_ends_at,
                e.revoked_at AS entitlement_revoked_at
            {$base_from}
            LEFT JOIN {$this->orders_table} o ON o.subscription_id = s.id
            LEFT JOIN {$this->entitlements_table} e
                ON e.source_type = 'subscription'
                AND e.source_id = s.id
                AND e.entitlement_key = 'minecraft_access'
            WHERE {$where_sql}
            GROUP BY s.id
            ORDER BY s.id DESC
            LIMIT %d OFFSET %d";

        $list_params = array_merge($params, [$per_page, $offset]);
        $items = $wpdb->get_results(
            $wpdb->prepare($list_sql, ...$list_params),
            ARRAY_A
        );

        return [
            'items' => is_array($items) ? $items : [],
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $per_page)),
            'page' => $page,
        ];
    }

    public function find(int $subscription_id): ?array
    {
        global $wpdb;

        if ($subscription_id < 1) {
            return null;
        }

        $sql = "SELECT
                s.*,
                u.user_login,
                u.user_email,
                p.name AS plan_name,
                p.code AS plan_code,
                p.duration_days,
                o.id AS order_id,
                o.public_id AS order_public_id,
                o.status AS order_status,
                o.amount AS order_amount,
                o.currency AS order_currency,
                o.gateway AS order_gateway,
                e.id AS entitlement_id,
                e.status AS entitlement_status,
                e.starts_at AS entitlement_starts_at,
                e.ends_at AS entitlement_ends_at,
                e.revoked_at AS entitlement_revoked_at,
                e.revocation_reason AS entitlement_revocation_reason
            FROM {$this->subscriptions_table} s
            INNER JOIN {$wpdb->users} u ON u.ID = s.user_id
            INNER JOIN {$this->plans_table} p ON p.id = s.plan_id
            LEFT JOIN {$this->orders_table} o ON o.subscription_id = s.id
            LEFT JOIN {$this->entitlements_table} e
                ON e.source_type = 'subscription'
                AND e.source_id = s.id
                AND e.entitlement_key = 'minecraft_access'
            WHERE s.id = %d
            ORDER BY o.id DESC
            LIMIT 1";

        $row = $wpdb->get_row(
            $wpdb->prepare($sql, $subscription_id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function all_plans(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT id, code, name, is_active
             FROM {$this->plans_table}
             ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }
}
