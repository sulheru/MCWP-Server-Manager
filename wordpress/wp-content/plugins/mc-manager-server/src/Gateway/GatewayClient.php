<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Gateway;

use JsonException;
use OptiGrid\MCManagerServer\Contracts\GatewayClientInterface;
use WP_Error;

final class GatewayClient implements GatewayClientInterface
{
    private const DEFAULT_TIMEOUT = 8;

    private readonly string $baseUrl;

    public function __construct(string $baseUrl, private readonly int $timeout = self::DEFAULT_TIMEOUT)
    {
        $normalized = untrailingslashit(trim($baseUrl));

        $parts = wp_parse_url($normalized);

        if (
            $normalized === ''
            || !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            || trim((string) $parts['host']) === ''
        ) {
            throw new GatewayException(__('La URL base del Minecraft Gateway no es válida.', 'mc-manager-server'));
        }

        $this->baseUrl = $normalized;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function get(string $path, array $query = []): GatewayResponse
    {
        return $this->request('GET', $path, $query);
    }

    public function post(string $path, array $payload = []): GatewayResponse
    {
        return $this->request('POST', $path, [], $payload);
    }

    public function request(string $method, string $path, array $query = [], ?array $payload = null): GatewayResponse
    {
        $method = strtoupper(trim($method));

        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new GatewayException(
                sprintf(__('Método HTTP no admitido: %s', 'mc-manager-server'), $method)
            );
        }

        $url = $this->buildUrl($path, $query);
        $args = [
            'method' => $method,
            'timeout' => max(1, $this->timeout),
            'redirection' => 0,
            'reject_unsafe_urls' => false,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'MC-Manager-Server/' . MC_MANAGER_SERVER_VERSION,
            ],
        ];

        if ($payload !== null) {
            try {
                $args['body'] = wp_json_encode($payload, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new GatewayException(
                    __('No se pudo serializar la solicitud para el Gateway.', 'mc-manager-server'),
                    null,
                    null,
                    $method,
                    $url
                );
            }

            $args['headers']['Content-Type'] = 'application/json; charset=utf-8';
        }

        /**
         * Permite ajustar la solicitud HTTP sin reemplazar el cliente completo.
         * Nunca debe utilizarse para registrar secretos en logs.
         *
         * @param array<string, mixed> $args
         * @param string $method
         * @param string $url
         * @param array<string, mixed>|null $payload
         */
        $args = apply_filters('mc_manager_server_gateway_request_args', $args, $method, $url, $payload);

        $response = wp_remote_request($url, $args);

        if ($response instanceof WP_Error) {
            throw new GatewayException(
                sprintf(
                    __('No se pudo conectar con el Minecraft Gateway: %s', 'mc-manager-server'),
                    $response->get_error_message()
                ),
                null,
                null,
                $method,
                $url
            );
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $rawBody = (string) wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        $headerData = method_exists($headers, 'getAll') ? $headers->getAll() : (array) $headers;

        $data = null;
        if ($rawBody !== '') {
            try {
                $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
                $data = is_array($decoded) ? $decoded : ['value' => $decoded];
            } catch (JsonException $exception) {
                throw new GatewayException(
                    __('El Gateway devolvió una respuesta que no es JSON válido.', 'mc-manager-server'),
                    $statusCode,
                    $rawBody,
                    $method,
                    $url
                );
            }
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $detail = is_array($data) && isset($data['detail']) && is_scalar($data['detail'])
                ? (string) $data['detail']
                : __('Respuesta HTTP no satisfactoria.', 'mc-manager-server');

            throw new GatewayException(
                sprintf(__('Gateway HTTP %1$d: %2$s', 'mc-manager-server'), $statusCode, $detail),
                $statusCode,
                $rawBody,
                $method,
                $url
            );
        }

        return new GatewayResponse($statusCode, $data, $rawBody, $headerData);
    }

    public function health(): GatewayResponse
    {
        return $this->get('/health');
    }

    public function server(): GatewayResponse
    {
        return $this->get('/server');
    }

    public function worldState(): GatewayResponse
    {
        return $this->get('/world/state');
    }

    public function gamerules(): GatewayResponse
    {
        return $this->get('/gamerules');
    }

    public function telemetry(): GatewayResponse
    {
        return $this->get('/telemetry/minecraft');
    }

    public function setDifficulty(string $difficulty): GatewayResponse
    {
        $difficulty = strtolower(trim($difficulty));
        $allowed = ['peaceful', 'easy', 'normal', 'hard'];

        if (!in_array($difficulty, $allowed, true)) {
            throw new GatewayException(__('La dificultad solicitada no es válida.', 'mc-manager-server'));
        }

        return $this->post('/server/difficulty', ['value' => $difficulty]);
    }

    public function setDefaultGamemode(string $gamemode): GatewayResponse
    {
        $gamemode = strtolower(trim($gamemode));
        $allowed = ['survival', 'creative', 'adventure', 'spectator'];

        if (!in_array($gamemode, $allowed, true)) {
            throw new GatewayException(__('El modo de juego solicitado no es válido.', 'mc-manager-server'));
        }

        return $this->post('/server/default-gamemode', ['value' => $gamemode]);
    }

    /** @param array<string, scalar|array<array-key, scalar>|null> $query */
    private function buildUrl(string $path, array $query): string
    {
        $path = '/' . ltrim(trim($path), '/');
        $url = $this->baseUrl . $path;

        if ($query !== []) {
            $url = add_query_arg($query, $url);
        }

        return $url;
    }
}
