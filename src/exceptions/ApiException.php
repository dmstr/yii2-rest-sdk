<?php

namespace dmstr\rest\sdk\exceptions;

use Throwable;

class ApiException extends HttpClientException
{
    private int $_statusCode;
    private array $_responseBody;

    public function __construct(
        string $message,
        int $statusCode,
        array $responseBody = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->_statusCode = $statusCode;
        $this->_responseBody = $responseBody;
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->_statusCode;
    }

    public function getResponseBody(): array
    {
        return $this->_responseBody;
    }

    public function getApiErrorName(): ?string
    {
        return $this->_responseBody['name'] ?? null;
    }

    public function getApiMessage(): ?string
    {
        return $this->_responseBody['message'] ?? null;
    }
}
