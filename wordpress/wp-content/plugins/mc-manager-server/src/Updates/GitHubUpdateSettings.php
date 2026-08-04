<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Updates;

/**
 * Configuración central del canal de actualizaciones.
 */
final class GitHubUpdateSettings
{
    public const OPTION_NAME = 'optigrid_github_update_settings';

    /**
     * @return array{
     *   owner:string,
     *   repository:string,
     *   channel:string,
     *   frequency:string,
     *   token:string
     * }
     */
    public function all(): array
    {
        $stored = get_option(self::OPTION_NAME, []);

        if (!is_array($stored)) {
            $stored = [];
        }

        return [
            'owner' => $this->sanitizeOwner(
                (string) ($stored['owner'] ?? 'sulheru')
            ),
            'repository' => $this->sanitizeRepository(
                (string) (
                    $stored['repository']
                    ?? 'MCWP-Server-Manager'
                )
            ),
            'channel' => $this->sanitizeChannel(
                (string) ($stored['channel'] ?? 'main')
            ),
            'frequency' => $this->sanitizeFrequency(
                (string) ($stored['frequency'] ?? 'twicedaily')
            ),
            'token' => $this->token($stored),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,string>
     */
    public function save(array $input): array
    {
        $current = $this->all();

        $owner = $this->sanitizeOwner(
            (string) ($input['owner'] ?? '')
        );

        $repository = $this->sanitizeRepository(
            (string) ($input['repository'] ?? '')
        );

        $channel = $this->sanitizeChannel(
            (string) ($input['channel'] ?? '')
        );

        $frequency = $this->sanitizeFrequency(
            (string) ($input['frequency'] ?? '')
        );

        if ($owner === '') {
            throw new \InvalidArgumentException(
                'El propietario del repositorio es obligatorio.'
            );
        }

        if ($repository === '') {
            throw new \InvalidArgumentException(
                'El nombre del repositorio es obligatorio.'
            );
        }

        if ($channel === '') {
            throw new \InvalidArgumentException(
                'La rama, etiqueta o canal es obligatorio.'
            );
        }

        $tokenInput = isset($input['token'])
            ? trim((string) $input['token'])
            : '';

        $clearToken = !empty($input['clear_token']);

        if ($clearToken) {
            $token = '';
        } elseif ($tokenInput !== '') {
            $token = sanitize_text_field($tokenInput);
        } else {
            $token = $current['token'];
        }

        $saved = [
            'owner' => $owner,
            'repository' => $repository,
            'channel' => $channel,
            'frequency' => $frequency,
            'token' => $token,
        ];

        update_option(
            self::OPTION_NAME,
            $saved,
            false
        );

        return $saved;
    }

    public function hasToken(): bool
    {
        return $this->all()['token'] !== '';
    }

    public function clearCache(): void
    {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_optigrid_github_update_%'
                OR option_name LIKE '_transient_timeout_optigrid_github_update_%'"
        );

        delete_site_transient('update_plugins');
    }

    private function token(array $stored): string
    {
        if (defined('OPTIGRID_GITHUB_TOKEN')) {
            $constant = trim((string) OPTIGRID_GITHUB_TOKEN);

            if ($constant !== '') {
                return $constant;
            }
        }

        return isset($stored['token'])
            ? trim((string) $stored['token'])
            : '';
    }

    private function sanitizeOwner(string $owner): string
    {
        $owner = trim($owner);

        return preg_match('/^[A-Za-z0-9-]{1,100}$/', $owner)
            ? $owner
            : '';
    }

    private function sanitizeRepository(string $repository): string
    {
        $repository = trim($repository);

        return preg_match(
            '/^[A-Za-z0-9._-]{1,100}$/',
            $repository
        )
            ? $repository
            : '';
    }

    private function sanitizeChannel(string $channel): string
    {
        $channel = trim($channel);

        return preg_match(
            '/^[A-Za-z0-9._\/-]{1,190}$/',
            $channel
        )
            ? $channel
            : '';
    }

    private function sanitizeFrequency(string $frequency): string
    {
        $allowed = [
            'hourly',
            'twicedaily',
            'daily',
            'manual',
        ];

        return in_array($frequency, $allowed, true)
            ? $frequency
            : 'twicedaily';
    }
}
