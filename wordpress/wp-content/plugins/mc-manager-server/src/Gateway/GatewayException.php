<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Gateway;

use RuntimeException;

final class GatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
        private readonly ?string $responseBody = null,
        private readonly ?string $requestMethod = null,
        private readonly ?string $requestUrl = null
    ) {
        parent::__construct($message);
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    public function responseBody(): ?string
    {
        return $this->responseBody;
    }

    public function requestMethod(): ?string
    {
        return $this->requestMethod;
    }

    public function requestUrl(): ?string
    {
        return $this->requestUrl;
    }
}
