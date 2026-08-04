<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repositorio administrativo de planes.
 *
 * Los planes utilizados no se eliminan: se desactivan para conservar
 * la trazabilidad de órdenes y suscripciones históricas.
 */
final class OptiGrid_Subscriptions_Plan_Admin_Repository
{
    private string $table;

    public function __construct()
    {
        $this->table = OptiGrid_Subscriptions_Database::tables()['plans'];
    }

    public function all(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT *
             FROM {$this->table}
             ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$this->table}
                 WHERE id = %d
                 LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function code_exists(string $code, int $exclude_id = 0): bool
    {
        global $wpdb;

        if ($exclude_id > 0) {
            $found = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$this->table}
                     WHERE code = %s
                       AND id <> %d
                     LIMIT 1",
                    $code,
                    $exclude_id
                )
            );
        } else {
            $found = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$this->table}
                     WHERE code = %s
                     LIMIT 1",
                    $code
                )
            );
        }

        return $found !== null;
    }

    public function save(array $data, int $id = 0): int
    {
        global $wpdb;

        $now = current_time('mysql', true);

        $row = [
            'code'          => $data['code'],
            'name'          => $data['name'],
            'description'   => $data['description'],
            'price'         => $data['price'],
            'currency'      => $data['currency'],
            'duration_days' => $data['duration_days'],
            'is_active'     => $data['is_active'],
            'is_visible'    => $data['is_visible'],
            'sort_order'    => $data['sort_order'],
            'updated_at'    => $now,
        ];

        $formats = [
            '%s', '%s', '%s', '%s', '%s',
            '%d', '%d', '%d', '%d', '%s',
        ];

        if ($id > 0) {
            $updated = $wpdb->update(
                $this->table,
                $row,
                ['id' => $id],
                $formats,
                ['%d']
            );

            if ($updated === false) {
                throw new RuntimeException(
                    'No se pudo actualizar el plan: ' . $wpdb->last_error
                );
            }

            return $id;
        }

        $row['created_at'] = $now;
        $formats[] = '%s';

        $inserted = $wpdb->insert(
            $this->table,
            $row,
            $formats
        );

        if ($inserted === false) {
            throw new RuntimeException(
                'No se pudo crear el plan: ' . $wpdb->last_error
            );
        }

        return (int) $wpdb->insert_id;
    }

    public function duplicate(int $id): int
    {
        $source = $this->find($id);

        if ($source === null) {
            throw new RuntimeException('El plan de origen no existe.');
        }

        $base_code = sanitize_key((string) $source['code']) . '-copy';
        $code = $base_code;
        $suffix = 2;

        while ($this->code_exists($code)) {
            $code = $base_code . '-' . $suffix;
            $suffix++;
        }

        return $this->save(
            [
                'code'          => $code,
                'name'          => (string) $source['name'] . ' (copia)',
                'description'   => (string) ($source['description'] ?? ''),
                'price'         => (string) $source['price'],
                'currency'      => (string) $source['currency'],
                'duration_days' => (int) $source['duration_days'],
                'is_active'     => 0,
                'is_visible'    => 0,
                'sort_order'    => (int) $source['sort_order'],
            ]
        );
    }

    public function set_active(int $id, bool $active): void
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->table,
            [
                'is_active'  => $active ? 1 : 0,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        if ($updated === false) {
            throw new RuntimeException(
                'No se pudo cambiar el estado del plan: '
                . $wpdb->last_error
            );
        }
    }

    public function usage_counts(int $plan_id): array
    {
        global $wpdb;

        $tables = OptiGrid_Subscriptions_Database::tables();

        return [
            'subscriptions' => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$tables['subscriptions']}
                     WHERE plan_id = %d",
                    $plan_id
                )
            ),
            'orders' => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$tables['orders']}
                     WHERE plan_id = %d",
                    $plan_id
                )
            ),
        ];
    }
}
