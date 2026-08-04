<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Updates;

use stdClass;
use WP_Error;
use WP_Upgrader;

/**
 * Actualizador nativo de plugins OptiGrid desde GitHub.
 *
 * El repositorio y el canal se configuran desde WordPress.
 * Gateway y sync-worker quedan fuera de este mecanismo.
 */
final class GitHubPluginUpdater
{
    private const CACHE_TTL = 900;

    private GitHubUpdateSettings $settings;

    /**
     * @var array<string,array{slug:string,main_file:string,name:string}>
     */
    private array $plugins = [
        'mc-manager-server/mc-manager-server.php' => [
            'slug' => 'mc-manager-server',
            'main_file' => 'mc-manager-server.php',
            'name' => 'MC Manager Server',
        ],
        'mc-manager-users/mc-manager-users.php' => [
            'slug' => 'mc-manager-users',
            'main_file' => 'mc-manager-users.php',
            'name' => 'MC Manager Users',
        ],
        'mc-manager-azure-entra-id/mc-manager-azure-entra-id.php' => [
            'slug' => 'mc-manager-azure-entra-id',
            'main_file' => 'mc-manager-azure-entra-id.php',
            'name' => 'MC Manager Azure Entra ID',
        ],
        'mc-manager-subscriptions/mc-manager-subscriptions.php' => [
            'slug' => 'mc-manager-subscriptions',
            'main_file' => 'mc-manager-subscriptions.php',
            'name' => 'OptiGrid Subscriptions',
        ],
    ];

    public function __construct(GitHubUpdateSettings $settings)
    {
        $this->settings = $settings;
    }

    public function registerHooks(): void
    {
        add_filter(
            'pre_set_site_transient_update_plugins',
            [$this, 'injectUpdates']
        );

        add_filter(
            'upgrader_source_selection',
            [$this, 'selectPluginSource'],
            20,
            4
        );

        add_filter(
            'http_request_args',
            [$this, 'githubRequestArgs'],
            20,
            2
        );

        add_action(
            'upgrader_process_complete',
            [$this, 'afterUpgrade'],
            10,
            2
        );
    }

    /**
     * @param mixed $transient
     * @return mixed
     */
    public function injectUpdates($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }

        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }

        foreach ($this->plugins as $pluginFile => $component) {
            $installedFile = WP_PLUGIN_DIR . '/' . $pluginFile;

            if (!is_readable($installedFile)) {
                continue;
            }

            $localData = get_file_data(
                $installedFile,
                ['Version' => 'Version'],
                'plugin'
            );

            $localVersion = (string) ($localData['Version'] ?? '');
            $remoteVersion = $this->remoteVersion($pluginFile);

            if (
                is_wp_error($remoteVersion)
                || $localVersion === ''
                || version_compare($remoteVersion, $localVersion, '<=')
            ) {
                continue;
            }

            $update = new stdClass();
            $update->id = $this->repositoryUrl();
            $update->slug = $component['slug'];
            $update->plugin = $pluginFile;
            $update->new_version = $remoteVersion;
            $update->url = $this->repositoryUrl();
            $update->package = $this->packageUrl();
            $update->tested = get_bloginfo('version');
            $update->requires_php = '8.1';

            $transient->response[$pluginFile] = $update;
        }

        return $transient;
    }

    /**
     * @param string|WP_Error $source
     * @param string $remoteSource
     * @param WP_Upgrader $upgrader
     * @param array<string,mixed> $hookExtra
     * @return string|WP_Error
     */
    public function selectPluginSource(
        $source,
        string $remoteSource,
        WP_Upgrader $upgrader,
        array $hookExtra
    ) {
        if (is_wp_error($source)) {
            return $source;
        }

        $pluginFile = isset($hookExtra['plugin'])
            ? plugin_basename((string) $hookExtra['plugin'])
            : '';

        if (!isset($this->plugins[$pluginFile])) {
            return $source;
        }

        $slug = $this->plugins[$pluginFile]['slug'];
        $pattern = trailingslashit($remoteSource)
            . '*/wordpress/wp-content/plugins/'
            . $slug;

        $matches = glob($pattern, GLOB_ONLYDIR);

        if (!is_array($matches) || count($matches) !== 1) {
            return new WP_Error(
                'optigrid_github_source_missing',
                sprintf(
                    'No se encontró %s dentro del paquete GitHub.',
                    $slug
                )
            );
        }

        $componentSource = untrailingslashit($matches[0]);
        $destination = trailingslashit($remoteSource) . $slug;

        if (file_exists($destination)) {
            global $wp_filesystem;

            if ($wp_filesystem) {
                $wp_filesystem->delete($destination, true);
            }
        }

        if (!@rename($componentSource, $destination)) {
            if (!function_exists('copy_dir')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $copied = copy_dir($componentSource, $destination);

            if (is_wp_error($copied)) {
                return $copied;
            }
        }

        return trailingslashit($destination);
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function githubRequestArgs(array $args, string $url): array
    {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));

        if (!in_array(
            $host,
            [
                'api.github.com',
                'github.com',
                'codeload.github.com',
                'raw.githubusercontent.com',
            ],
            true
        )) {
            return $args;
        }

        $configuration = $this->settings->all();

        $headers = isset($args['headers']) && is_array($args['headers'])
            ? $args['headers']
            : [];

        $headers['User-Agent'] = 'OptiGrid-WordPress-Updater';

        if ($configuration['token'] !== '') {
            $headers['Authorization'] = 'Bearer '
                . $configuration['token'];
        }

        $args['headers'] = $headers;
        $args['timeout'] = max(20, (int) ($args['timeout'] ?? 0));
        $args['redirection'] = 8;

        return $args;
    }

    /**
     * @param WP_Upgrader $upgrader
     * @param array<string,mixed> $hookExtra
     */
    public function afterUpgrade(
        WP_Upgrader $upgrader,
        array $hookExtra
    ): void {
        if (
            ($hookExtra['action'] ?? '') !== 'update'
            || ($hookExtra['type'] ?? '') !== 'plugin'
        ) {
            return;
        }

        $this->clearCaches();
    }

    /**
     * @return array<string,array<string,string>>
     */
    public function statuses(bool $force = false): array
    {
        if ($force) {
            $this->clearCaches();
        }

        $statuses = [];

        foreach ($this->plugins as $pluginFile => $component) {
            $installedFile = WP_PLUGIN_DIR . '/' . $pluginFile;

            if (!is_readable($installedFile)) {
                $statuses[$pluginFile] = [
                    'name' => $component['name'],
                    'local' => '',
                    'remote' => '',
                    'status' => 'not_installed',
                    'message' => 'No instalado',
                ];
                continue;
            }

            $localData = get_file_data(
                $installedFile,
                ['Version' => 'Version'],
                'plugin'
            );

            $localVersion = (string) ($localData['Version'] ?? '');
            $remoteVersion = $this->remoteVersion($pluginFile);

            if (is_wp_error($remoteVersion)) {
                $statuses[$pluginFile] = [
                    'name' => $component['name'],
                    'local' => $localVersion,
                    'remote' => '',
                    'status' => 'error',
                    'message' => $remoteVersion->get_error_message(),
                ];
                continue;
            }

            $hasUpdate = version_compare(
                $remoteVersion,
                $localVersion,
                '>'
            );

            $statuses[$pluginFile] = [
                'name' => $component['name'],
                'local' => $localVersion,
                'remote' => $remoteVersion,
                'status' => $hasUpdate ? 'update' : 'current',
                'message' => $hasUpdate
                    ? 'Actualización disponible'
                    : 'Actualizado',
            ];
        }

        return $statuses;
    }

    public function clearCaches(): void
    {
        foreach (array_keys($this->plugins) as $pluginFile) {
            delete_transient($this->cacheKey($pluginFile));
        }

        delete_site_transient('update_plugins');
    }

    /**
     * @return string|WP_Error
     */
    private function remoteVersion(string $pluginFile)
    {
        $cacheKey = $this->cacheKey($pluginFile);
        $cached = get_transient($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $component = $this->plugins[$pluginFile];
        $configuration = $this->settings->all();

        $url = sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s/wordpress/wp-content/plugins/%s/%s',
            rawurlencode($configuration['owner']),
            rawurlencode($configuration['repository']),
            $this->encodeRef($configuration['channel']),
            rawurlencode($component['slug']),
            rawurlencode($component['main_file'])
        );

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 20,
                'headers' => ['Accept' => 'text/plain'],
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return new WP_Error(
                'optigrid_github_http',
                sprintf(
                    'GitHub respondió HTTP %d para %s.',
                    $code,
                    $component['slug']
                )
            );
        }

        $body = wp_remote_retrieve_body($response);

        if (!preg_match(
            '/^[ \t\/*#@]*Version:[ \t]*([0-9A-Za-z.+_-]+)/mi',
            $body,
            $matches
        )) {
            return new WP_Error(
                'optigrid_github_version_missing',
                sprintf(
                    'No se encontró Version en %s.',
                    $component['slug']
                )
            );
        }

        $version = sanitize_text_field($matches[1]);

        set_transient(
            $cacheKey,
            $version,
            self::CACHE_TTL
        );

        return $version;
    }

    private function cacheKey(string $pluginFile): string
    {
        $configuration = $this->settings->all();

        return 'optigrid_github_update_'
            . md5(
                $configuration['owner']
                . '/'
                . $configuration['repository']
                . '@'
                . $configuration['channel']
                . ':'
                . $pluginFile
            );
    }

    private function repositoryUrl(): string
    {
        $configuration = $this->settings->all();

        return sprintf(
            'https://github.com/%s/%s',
            rawurlencode($configuration['owner']),
            rawurlencode($configuration['repository'])
        );
    }

    private function packageUrl(): string
    {
        $configuration = $this->settings->all();

        return sprintf(
            'https://api.github.com/repos/%s/%s/zipball/%s',
            rawurlencode($configuration['owner']),
            rawurlencode($configuration['repository']),
            $this->encodeRef($configuration['channel'])
        );
    }

    private function encodeRef(string $reference): string
    {
        return implode(
            '/',
            array_map(
                'rawurlencode',
                explode('/', $reference)
            )
        );
    }
}
