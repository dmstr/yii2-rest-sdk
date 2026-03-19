<?php

namespace dmstr\rest\sdk\exceptions;

class ValidationException extends ApiException
{
    private array $_fieldErrors;

    public function __construct(
        string $message,
        int $statusCode,
        array $responseBody = [],
        array $fieldErrors = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->_fieldErrors = $fieldErrors;
        parent::__construct($message, $statusCode, $responseBody, $code, $previous);
    }

    /**
     * Raw field errors as returned by the API: [{field, message}, ...]
     */
    public function getFieldErrors(): array
    {
        return $this->_fieldErrors;
    }

    /**
     * Field errors grouped by field name: ['field' => ['msg1', 'msg2']]
     */
    public function getErrorsByField(): array
    {
        $grouped = [];
        foreach ($this->_fieldErrors as $error) {
            $field = $error['field'] ?? 'unknown';
            $grouped[$field][] = $error['message'] ?? 'Validation error';
        }
        return $grouped;
    }
}
