<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Contracts;

use OptiGrid\MCManagerServer\Gateway\GatewayResponse;

interface GatewayClientInterface
{
    public function baseUrl(): string;

    /** @param array<string, scalar|array<array-key, scalar>|null> $query */
    public function get(string $path, array $query = []): GatewayResponse;

    /** @param array<string, mixed> $payload */
    public function post(string $path, array $payload = []): GatewayResponse;

    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $query
     * @param array<string, mixed>|null $payload
     */
    public function request(string $method, string $path, array $query = [], ?array $payload = null): GatewayResponse;

    public function health(): GatewayResponse;

    public function server(): GatewayResponse;

    public function worldState(): GatewayResponse;

    public function gamerules(): GatewayResponse;

    public function telemetry(): GatewayResponse;

    public function setDifficulty(string $difficulty): GatewayResponse;

    public function setDefaultGamemode(string $gamemode): GatewayResponse;
}
