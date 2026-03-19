<?php

namespace dmstr\rest\sdk\traits;

trait HasErrors
{
    private array $_errors = [];

    public function hasErrors(?string $attribute = null): bool
    {
        if ($attribute === null) {
            return !empty($this->_errors);
        }
        return !empty($this->_errors[$attribute]);
    }

    /**
     * @return array ['field' => ['msg1', ...]] or ['msg1', ...] if attribute given
     */
    public function getErrors(?string $attribute = null): array
    {
        if ($attribute === null) {
            return $this->_errors;
        }
        return $this->_errors[$attribute] ?? [];
    }

    /**
     * @return array ['field' => 'first message']
     */
    public function getFirstErrors(): array
    {
        $firstErrors = [];
        foreach ($this->_errors as $attribute => $messages) {
            if (!empty($messages)) {
                $firstErrors[$attribute] = reset($messages);
            }
        }
        return $firstErrors;
    }

    public function getFirstError(string $attribute): ?string
    {
        return $this->_errors[$attribute][0] ?? null;
    }

    public function addError(string $attribute, string $message): void
    {
        $this->_errors[$attribute][] = $message;
    }

    public function clearErrors(?string $attribute = null): void
    {
        if ($attribute === null) {
            $this->_errors = [];
        } else {
            unset($this->_errors[$attribute]);
        }
    }
}
