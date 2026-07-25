<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Modules\Summary;

use DateTimeImmutable;
use DateTimeZone;
use OptiGrid\MCManagerServer\Contracts\GatewayClientInterface;
use OptiGrid\MCManagerServer\Gateway\GatewayException;

final class SummarySnapshot
{
    /** @param array<string, mixed> $cards */
    private function __construct(
        private readonly array $cards,
        private readonly string $checkedAt,
        private readonly array $warnings,
        private readonly bool $gatewayAvailable
    ) {
    }

    public static function collect(GatewayClientInterface $client): self
    {
        $warnings = [];
        $server = self::readEndpoint($client, 'server', $warnings);
        $world = self::readEndpoint($client, 'worldState', $warnings);
        $telemetry = self::readEndpoint($client, 'telemetry', $warnings);

        $gatewayAvailable = $server !== null || $world !== null || $telemetry !== null;
        $checkedAt = self::firstString([
            $telemetry['checked_at'] ?? null,
            $server['checked_at'] ?? null,
            $world['checked_at'] ?? null,
        ]) ?? gmdate(DATE_ATOM);

        $online = self::firstBool([
            $telemetry['online'] ?? null,
            $server['online'] ?? null,
            isset($telemetry['status']) ? $telemetry['status'] === 'online' : null,
            isset($server['status']) ? $server['status'] === 'online' : null,
        ]);

        $playersOnline = self::firstInt([
            $telemetry['players']['online'] ?? null,
            $telemetry['capacity']['players_online'] ?? null,
            $server['capacity']['players_online'] ?? null,
        ]);
        $maxPlayers = self::firstInt([
            $telemetry['players']['maximum'] ?? null,
            $telemetry['players']['max'] ?? null,
            $telemetry['capacity']['max_players'] ?? null,
            $server['capacity']['max_players'] ?? null,
        ]);

        $tps = self::firstFloat([
            $telemetry['performance']['tps']['one_minute'] ?? null,
            $telemetry['performance']['tps']['one_min'] ?? null,
        ]);
        $mspt = self::firstFloat([
            $telemetry['performance']['mspt']['five_seconds']['average'] ?? null,
            $telemetry['performance']['mspt']['one_minute']['average'] ?? null,
        ]);

        $worldName = self::detectWorldName($telemetry);
        $difficulty = self::firstString([
            $server['runtime']['difficulty']['value'] ?? null,
            $telemetry['server']['difficulty']['value'] ?? null,
        ]);

        $endpointErrors = self::collectReportedErrors([$server, $world, $telemetry]);
        foreach ($endpointErrors as $error) {
            $warnings[] = $error;
        }
        $warnings = array_values(array_unique(array_filter($warnings)));

        $cards = [
            'server' => [
                'value' => $gatewayAvailable
                    ? ($online === true ? __('En línea', 'mc-manager-server') : ($online === false ? __('Fuera de línea', 'mc-manager-server') : __('Estado desconocido', 'mc-manager-server')))
                    : __('Gateway no disponible', 'mc-manager-server'),
                'description' => $gatewayAvailable
                    ? __('Estado observado mediante el Minecraft Gateway.', 'mc-manager-server')
                    : __('No fue posible obtener una respuesta válida del Gateway.', 'mc-manager-server'),
                'status' => $online === true ? __('Operativo', 'mc-manager-server') : '',
            ],
            'players' => [
                'value' => $playersOnline !== null
                    ? sprintf('%d / %s', $playersOnline, $maxPlayers !== null ? (string) $maxPlayers : '—')
                    : '—',
                'description' => $playersOnline !== null
                    ? __('Jugadores conectados y capacidad máxima observada.', 'mc-manager-server')
                    : __('La capacidad no está disponible en el snapshot actual.', 'mc-manager-server'),
            ],
            'world' => [
                'value' => $worldName ?? __('Mundos disponibles', 'mc-manager-server'),
                'description' => self::worldDescription($world, $telemetry),
            ],
            'performance' => [
                'value' => $tps !== null ? sprintf('TPS %.2f', $tps) : '—',
                'description' => $mspt !== null
                    ? sprintf(__('MSPT medio: %.2f ms.', 'mc-manager-server'), $mspt)
                    : __('MSPT no disponible en el snapshot actual.', 'mc-manager-server'),
                'status' => self::performanceStatus($tps, $mspt),
            ],
            'alerts' => [
                'value' => $warnings === [] ? __('Sin incidencias', 'mc-manager-server') : sprintf(_n('%d incidencia', '%d incidencias', count($warnings), 'mc-manager-server'), count($warnings)),
                'description' => $warnings === []
                    ? __('Los endpoints consultados no han comunicado errores.', 'mc-manager-server')
                    : $warnings[0],
                'status' => $warnings === [] ? __('Correcto', 'mc-manager-server') : __('Revisar', 'mc-manager-server'),
            ],
            'configuration' => [
                'value' => $difficulty !== null ? ucfirst($difficulty) : __('Sin evaluar', 'mc-manager-server'),
                'description' => $difficulty !== null
                    ? __('Dificultad observada directamente mediante RCON.', 'mc-manager-server')
                    : __('No hay una dificultad observable en el snapshot actual.', 'mc-manager-server'),
                'status' => $difficulty !== null ? __('Lectura real', 'mc-manager-server') : '',
            ],
        ];

        return new self($cards, $checkedAt, $warnings, $gatewayAvailable);
    }

    /** @return array<string, mixed> */
    public function cards(): array
    {
        return $this->cards;
    }

    public function checkedAt(): string
    {
        return $this->checkedAt;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function gatewayAvailable(): bool
    {
        return $this->gatewayAvailable;
    }

    /** @param list<string> $warnings @return array<string, mixed>|null */
    private static function readEndpoint(GatewayClientInterface $client, string $method, array &$warnings): ?array
    {
        try {
            $response = $client->{$method}();
            $data = $response->data();
            return is_array($data) ? $data : null;
        } catch (GatewayException $exception) {
            $warnings[] = sprintf(
                __('Fallo consultando %1$s: %2$s', 'mc-manager-server'),
                $method,
                $exception->getMessage()
            );
            return null;
        } catch (\Throwable $exception) {
            $warnings[] = sprintf(
                __('Error inesperado consultando %1$s: %2$s', 'mc-manager-server'),
                $method,
                $exception->getMessage()
            );
            return null;
        }
    }

    /** @param array<int, mixed> $values */
    private static function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }
        return null;
    }

    /** @param array<int, mixed> $values */
    private static function firstBool(array $values): ?bool
    {
        foreach ($values as $value) {
            if (is_bool($value)) {
                return $value;
            }
        }
        return null;
    }

    /** @param array<int, mixed> $values */
    private static function firstInt(array $values): ?int
    {
        foreach ($values as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                return (int) $value;
            }
        }
        return null;
    }

    /** @param array<int, mixed> $values */
    private static function firstFloat(array $values): ?float
    {
        foreach ($values as $value) {
            if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
                return (float) $value;
            }
        }
        return null;
    }

    /** @param array<string, mixed>|null $telemetry */
    private static function detectWorldName(?array $telemetry): ?string
    {
        if ($telemetry === null) {
            return null;
        }
        $worlds = $telemetry['chunks']['worlds'] ?? $telemetry['worlds'] ?? null;
        if (is_array($worlds) && $worlds !== []) {
            $names = array_keys($worlds);
            return implode(', ', array_map('strval', array_slice($names, 0, 3)));
        }
        return null;
    }

    /** @param array<string, mixed>|null $world @param array<string, mixed>|null $telemetry */
    private static function worldDescription(?array $world, ?array $telemetry): string
    {
        $time = self::firstInt([$world['world']['time']['value'] ?? null]);
        $chunks = self::firstInt([
            $telemetry['chunks']['total']['total'] ?? null,
            $telemetry['chunks']['total_chunks'] ?? null,
        ]);
        $parts = [];
        if ($time !== null) {
            $parts[] = sprintf(__('Tiempo global: %d ticks', 'mc-manager-server'), $time);
        }
        if ($chunks !== null) {
            $parts[] = sprintf(_n('%d chunk cargado', '%d chunks cargados', $chunks, 'mc-manager-server'), $chunks);
        }
        return $parts !== []
            ? implode(' · ', $parts)
            : __('Estado de mundos consultado, sin métricas resumibles.', 'mc-manager-server');
    }

    private static function performanceStatus(?float $tps, ?float $mspt): string
    {
        if ($tps === null && $mspt === null) {
            return '';
        }
        if (($tps !== null && $tps < 18.0) || ($mspt !== null && $mspt > 50.0)) {
            return __('Degradado', 'mc-manager-server');
        }
        return __('Saludable', 'mc-manager-server');
    }

    /** @param array<int, array<string, mixed>|null> $payloads @return list<string> */
    private static function collectReportedErrors(array $payloads): array
    {
        $errors = [];
        foreach ($payloads as $payload) {
            if (!is_array($payload) || !isset($payload['errors']) || !is_array($payload['errors'])) {
                continue;
            }
            foreach ($payload['errors'] as $error) {
                if (is_string($error) && trim($error) !== '') {
                    $errors[] = trim($error);
                } elseif (is_array($error)) {
                    $message = $error['message'] ?? $error['error'] ?? null;
                    if (is_string($message) && trim($message) !== '') {
                        $errors[] = trim($message);
                    }
                }
            }
        }
        return $errors;
    }
}
