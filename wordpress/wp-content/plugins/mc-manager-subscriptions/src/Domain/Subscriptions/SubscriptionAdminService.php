<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class OptiGrid_Subscriptions_Subscription_Admin_Service
{
    private string $subscriptions_table;
    private string $entitlements_table;

    public function __construct()
    {
        $tables = OptiGrid_Subscriptions_Database::tables();
        $this->subscriptions_table = $tables['subscriptions'];
        $this->entitlements_table = $tables['entitlements'];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $subscription_id, array $data): void
    {
        global $wpdb;

        if ($subscription_id < 1) {
            throw new InvalidArgumentException('Identificador de suscripción no válido.');
        }

        $allowed_statuses = ['pending', 'active', 'cancelled', 'expired'];
        $status = sanitize_key((string) ($data['status'] ?? ''));
        if (!in_array($status, $allowed_statuses, true)) {
            throw new InvalidArgumentException('Estado de suscripción no válido.');
        }

        $starts_at = $this->sanitize_datetime($data['starts_at'] ?? null, true);
        $ends_at = $this->sanitize_datetime($data['ends_at'] ?? null, false);
        $reason = sanitize_text_field((string) ($data['cancellation_reason'] ?? ''));

        if ($starts_at !== null && $ends_at !== null && strtotime($ends_at) <= strtotime($starts_at)) {
            throw new InvalidArgumentException('La fecha final debe ser posterior a la fecha de inicio.');
        }

        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->subscriptions_table} WHERE id = %d LIMIT 1",
                $subscription_id
            ),
            ARRAY_A
        );

        if (!is_array($subscription)) {
            throw new RuntimeException('La suscripción no existe.');
        }

        $now = current_time('mysql', true);

        if ($status === 'active' && $starts_at === null) {
            $starts_at = $now;
        }

        $wpdb->query('START TRANSACTION');

        try {
            $cancelled_at = in_array($status, ['cancelled', 'expired'], true)
                ? ($subscription['cancelled_at'] ?: $now)
                : null;

            $updated = $wpdb->update(
                $this->subscriptions_table,
                [
                    'status' => $status,
                    'starts_at' => $starts_at,
                    'ends_at' => $ends_at,
                    'cancelled_at' => $cancelled_at,
                    'cancellation_reason' => $reason !== '' ? $reason : null,
                    'updated_at' => $now,
                ],
                ['id' => $subscription_id],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                throw new RuntimeException('No se pudo actualizar la suscripción: ' . $wpdb->last_error);
            }

            $entitlement_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id
                     FROM {$this->entitlements_table}
                     WHERE source_type = 'subscription'
                       AND source_id = %d
                       AND entitlement_key = 'minecraft_access'
                     LIMIT 1",
                    $subscription_id
                )
            );

            if ($status === 'active') {
                if ($entitlement_id !== null) {
                    $ok = $wpdb->update(
                        $this->entitlements_table,
                        [
                            'status' => 'active',
                            'starts_at' => $starts_at,
                            'ends_at' => $ends_at,
                            'revoked_at' => null,
                            'revocation_reason' => null,
                            'updated_at' => $now,
                        ],
                        ['id' => (int) $entitlement_id],
                        ['%s', '%s', '%s', '%s', '%s', '%s'],
                        ['%d']
                    );
                } else {
                    $ok = $wpdb->insert(
                        $this->entitlements_table,
                        [
                            'user_id' => (int) $subscription['user_id'],
                            'entitlement_key' => 'minecraft_access',
                            'status' => 'active',
                            'source_type' => 'subscription',
                            'source_id' => $subscription_id,
                            'starts_at' => $starts_at,
                            'ends_at' => $ends_at,
                            'revoked_at' => null,
                            'revocation_reason' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
                    );
                }

                if ($ok === false) {
                    throw new RuntimeException('No se pudo activar el entitlement: ' . $wpdb->last_error);
                }
            } elseif ($entitlement_id !== null) {
                $revocation_reason = $reason !== ''
                    ? $reason
                    : sprintf('Suscripción marcada como %s', $status);

                $ok = $wpdb->update(
                    $this->entitlements_table,
                    [
                        'status' => 'revoked',
                        'ends_at' => $ends_at,
                        'revoked_at' => $now,
                        'revocation_reason' => $revocation_reason,
                        'updated_at' => $now,
                    ],
                    ['id' => (int) $entitlement_id],
                    ['%s', '%s', '%s', '%s', '%s'],
                    ['%d']
                );

                if ($ok === false) {
                    throw new RuntimeException('No se pudo revocar el entitlement: ' . $wpdb->last_error);
                }
            }

            $wpdb->query('COMMIT');

            do_action(
                'optigrid_subscriptions_subscription_updated',
                $subscription_id,
                $status
            );
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    private function sanitize_datetime(mixed $value, bool $required): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            if ($required) {
                return null;
            }

            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new InvalidArgumentException('Fecha u hora no válida.');
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
