<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $v = new self($data);
        $v->validate($rules, $labels);
        return $v;
    }

    public function validate(array $rules, array $labels = []): void
    {
        foreach ($rules as $field => $ruleString) {
            $rulesList = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));

            foreach ($rulesList as $rule) {
                $name = $rule;
                $param = null;
                if (is_string($rule) && str_contains($rule, ':')) {
                    [$name, $param] = explode(':', $rule, 2);
                }

                $this->apply($field, $label, $name, $param, $value);
            }
        }
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }

    private function apply(string $field, string $label, string $rule, ?string $param, mixed $value): void
    {
        $failed = false;
        $message = '';

        switch ($rule) {
            case 'required':
                $failed = $value === null || $value === '' || $value === [];
                $message = $label . ' is required.';
                break;
            case 'email':
                $failed = $value !== null && $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL);
                $message = $label . ' must be a valid email address.';
                break;
            case 'min':
                $failed = is_string($value) && strlen($value) < (int) $param;
                $message = $label . ' must be at least ' . $param . ' characters.';
                break;
            case 'max':
                $failed = is_string($value) && strlen($value) > (int) $param;
                $message = $label . ' may not be greater than ' . $param . ' characters.';
                break;
            case 'confirmed':
                $failed = ($this->data[$field . '_confirmation'] ?? null) !== $value;
                $message = $label . ' and confirm password must match.';
                break;
            case 'accepted':
                $failed = !in_array($value, [1, '1', true, 'on', 'yes'], true);
                $message = 'You must accept ' . $label . '.';
                break;
            case 'in':
                $allowed = explode(',', (string) $param);
                $failed = !in_array((string) $value, $allowed, true);
                $message = $label . ' is invalid.';
                break;
            case 'numeric':
                $failed = $value !== null && $value !== '' && !is_numeric($value);
                $message = $label . ' must be a number.';
                break;
            case 'integer':
                $failed = $value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false;
                $message = $label . ' must be an integer.';
                break;
            case 'url':
                $failed = $value !== null && $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_URL);
                $message = $label . ' must be a valid URL.';
                break;
        }

        if ($failed) {
            $this->errors[$field][] = $message;
        }
    }
}
