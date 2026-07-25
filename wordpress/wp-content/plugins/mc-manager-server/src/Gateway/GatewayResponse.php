<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Gateway;

final class GatewayResponse
{
    /**
     * @param array<string, mixed>|list<mixed>|null $data
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly ?array $data,
        private readonly string $rawBody,
        private readonly array $headers = []
    ) {
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /** @return array<string, mixed>|list<mixed>|null */
    public function data(): ?array
    {
        return $this->data;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /** @return array<string, string|list<string>> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function value(string $key, mixed $default = null): mixed
    {
        if ($this->data === null || !array_key_exists($key, $this->data)) {
            return $default;
        }

        return $this->data[$key];
    }
}
