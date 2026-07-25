<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Modules\Server;

use OptiGrid\MCManagerServer\Gateway\GatewayClient;
use Throwable;

final class ServerSnapshot
{
    /**
     * @param list<string> $warnings
     */
    private function __construct(
        private readonly bool $gatewayAvailable,
        private readonly string $status,
        private readonly string $difficulty,
        private readonly string $defaultGamemode,
        private readonly bool $defaultGamemodeAvailable,
        private readonly ?int $playersOnline,
        private readonly ?int $maxPlayers,
        private readonly ?int $worldCount,
        private readonly ?int $loadedChunks,
        private readonly string $checkedAt,
        private readonly array $warnings
    ) {
    }

    public static function collect(GatewayClient $client): self
    {
        $warnings = [];
        $server = [];
        $world = [];
        $telemetry = [];
        $gatewayAvailable = false;

        try {
            $server = $client->server()->data() ?? [];
            $gatewayAvailable = true;
        } catch (Throwable $exception) {
            $warnings[] = sprintf(
                __('No se pudo leer /server: %s', 'mc-manager-server'),
                $exception->getMessage()
            );
        }

        try {
            $world = $client->worldState()->data() ?? [];
            $gatewayAvailable = true;
        } catch (Throwable $exception) {
            $warnings[] = sprintf(
                __('No se pudo leer /world/state: %s', 'mc-manager-server'),
                $exception->getMessage()
            );
        }

        try {
            $telemetry = $client->telemetry()->data() ?? [];
            $gatewayAvailable = true;
        } catch (Throwable $exception) {
            $warnings[] = sprintf(
                __('No se pudo leer /telemetry/minecraft: %s', 'mc-manager-server'),
                $exception->getMessage()
            );
        }

        $runtime = is_array($server['runtime'] ?? null) ? $server['runtime'] : [];
        $capacity = is_array($server['capacity'] ?? null) ? $server['capacity'] : [];
        $playersTelemetry = is_array($telemetry['players'] ?? null) ? $telemetry['players'] : [];
        $chunks = is_array($telemetry['chunks'] ?? null) ? $telemetry['chunks'] : [];

        $difficultyState = is_array($runtime['difficulty'] ?? null)
            ? $runtime['difficulty']
            : [];

        $gamemodeState = is_array($runtime['default_gamemode'] ?? null)
            ? $runtime['default_gamemode']
            : [];

        $difficulty = self::scalarOrNull($difficultyState['value'] ?? null);
        $defaultGamemode = self::scalarOrNull($gamemodeState['value'] ?? null);
        $defaultGamemodeAvailable = ($gamemodeState['available'] ?? false) === true
            && $defaultGamemode !== null;

        $playersOnline = self::intOrNull(
            $capacity['players_online']
            ?? $playersTelemetry['online']
            ?? null
        );

        $maxPlayers = self::intOrNull(
            $capacity['max_players']
            ?? $playersTelemetry['maximum']
            ?? null
        );

        $worlds = is_array($chunks['worlds'] ?? null) ? $chunks['worlds'] : [];
        $worldCount = ($chunks['available'] ?? false) === true
            ? count($worlds)
            : null;

        $chunkTotal = is_array($chunks['total'] ?? null) ? $chunks['total'] : [];
        $loadedChunks = ($chunks['available'] ?? false) === true
            ? self::intOrNull($chunkTotal['total'] ?? null)
            : null;

        $status = self::scalarOrNull(
            $server['status']
            ?? $world['status']
            ?? $telemetry['status']
            ?? null
        );

        if ($status === null && ($server['online'] ?? false) === true) {
            $status = 'online';
        }

        $checkedAt = self::scalarOrNull(
            $server['checked_at']
            ?? $world['checked_at']
            ?? $telemetry['checked_at']
            ?? null
        ) ?? current_time('mysql');

        return new self(
            $gatewayAvailable,
            self::normalizeEnum($status, 'unknown'),
            self::normalizeEnum($difficulty, 'unknown'),
            self::normalizeEnum($defaultGamemode, 'unknown'),
            $defaultGamemodeAvailable,
            $playersOnline,
            $maxPlayers,
            $worldCount,
            $loadedChunks,
            $checkedAt,
            $warnings
        );
    }

    private static function scalarOrNull(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== ''
            ? trim((string) $value)
            : null;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_int($value) || is_numeric($value)
            ? (int) $value
            : null;
    }

    private static function normalizeEnum(mixed $value, string $default): string
    {
        return is_scalar($value) && trim((string) $value) !== ''
            ? strtolower(trim((string) $value))
            : $default;
    }

    public function gatewayAvailable(): bool { return $this->gatewayAvailable; }
    public function status(): string { return $this->status; }
    public function difficulty(): string { return $this->difficulty; }
    public function defaultGamemode(): string { return $this->defaultGamemode; }
    public function defaultGamemodeAvailable(): bool { return $this->defaultGamemodeAvailable; }
    public function playersOnline(): ?int { return $this->playersOnline; }
    public function maxPlayers(): ?int { return $this->maxPlayers; }
    public function worldCount(): ?int { return $this->worldCount; }
    public function loadedChunks(): ?int { return $this->loadedChunks; }
    public function checkedAt(): string { return $this->checkedAt; }

    /** @return list<string> */
    public function warnings(): array { return $this->warnings; }
}
