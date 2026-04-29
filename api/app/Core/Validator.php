<?php

namespace App\Core;

class Validator
{
    private array $errors = [];

    private function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = []
    ) {}

    public static function make(array $data, array $rules, array $messages = []): static
    {
        $v = new static($data, $rules, $messages);
        $v->validate();
        return $v;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        return array_values($this->errors)[0] ?? '';
    }

    // ── Validate all rules ─────────────────────────────────────────────────────

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleStr) {
            $rules = is_array($ruleStr) ? $ruleStr : explode('|', $ruleStr);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                [$ruleName, $param] = $this->parseRule($rule);

                $error = match ($ruleName) {
                    'required'   => $this->checkRequired($field, $value),
                    'email'      => $this->checkEmail($field, $value),
                    'numeric'    => $this->checkNumeric($field, $value),
                    'integer'    => $this->checkInteger($field, $value),
                    'string'     => $this->checkString($field, $value),
                    'min'        => $this->checkMin($field, $value, $param),
                    'max'        => $this->checkMax($field, $value, $param),
                    'min_length' => $this->checkMinLength($field, $value, $param),
                    'max_length' => $this->checkMaxLength($field, $value, $param),
                    'date'       => $this->checkDate($field, $value),
                    'in'         => $this->checkIn($field, $value, $param),
                    'regex'      => $this->checkRegex($field, $value, $param),
                    'confirmed'  => $this->checkConfirmed($field, $value),
                    'unique'     => $this->checkUnique($field, $value, $param),
                    'nullable'   => null,
                    default      => null,
                };

                if ($error) {
                    $this->errors[$field] = $this->messages["{$field}.{$ruleName}"]
                        ?? $this->messages[$field]
                        ?? $error;
                    break; // one error per field
                }
            }
        }
    }

    // ── Rule checkers ─────────────────────────────────────────────────────────

    private function checkRequired(string $f, mixed $v): ?string
    {
        if ($v === null || $v === '' || (is_array($v) && empty($v)))
            return "The {$f} field is required.";
        return null;
    }

    private function checkEmail(string $f, mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        if (!filter_var($v, FILTER_VALIDATE_EMAIL))
            return "The {$f} must be a valid email address.";
        return null;
    }

    private function checkNumeric(string $f, mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        if (!is_numeric($v))
            return "The {$f} must be a number.";
        return null;
    }

    private function checkInteger(string $f, mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        if (filter_var($v, FILTER_VALIDATE_INT) === false)
            return "The {$f} must be an integer.";
        return null;
    }

    private function checkString(string $f, mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        if (!is_string($v))
            return "The {$f} must be a string.";
        return null;
    }

    private function checkMin(string $f, mixed $v, string $param): ?string
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v) && (float)$v < (float)$param)
            return "The {$f} must be at least {$param}.";
        return null;
    }

    private function checkMax(string $f, mixed $v, string $param): ?string
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v) && (float)$v > (float)$param)
            return "The {$f} must not exceed {$param}.";
        return null;
    }

    private function checkMinLength(string $f, mixed $v, string $param): ?string
    {
        if ($v === null || $v === '') return null;
        if (strlen((string)$v) < (int)$param)
            return "The {$f} must be at least {$param} characters.";
        return null;
    }

    private function checkMaxLength(string $f, mixed $v, string $param): ?string
    {
        if ($v === null || $v === '') return null;
        if (strlen((string)$v) > (int)$param)
            return "The {$f} must not exceed {$param} characters.";
        return null;
    }

    private function checkDate(string $f, mixed $v): ?string
    {
        if ($v === null || $v === '') return null;
        if (!strtotime((string)$v))
            return "The {$f} must be a valid date.";
        return null;
    }

    private function checkIn(string $f, mixed $v, string $param): ?string
    {
        if ($v === null || $v === '') return null;
        $allowed = explode(',', $param);
        if (!in_array((string)$v, $allowed))
            return "The selected {$f} is invalid.";
        return null;
    }

    private function checkRegex(string $f, mixed $v, string $param): ?string
    {
        if ($v === null || $v === '') return null;
        if (!preg_match($param, (string)$v))
            return "The {$f} format is invalid.";
        return null;
    }

    private function checkConfirmed(string $f, mixed $v): ?string
    {
        $confirm = $this->data["{$f}_confirmation"] ?? null;
        if ($v !== $confirm)
            return "The {$f} confirmation does not match.";
        return null;
    }

    private function checkUnique(string $f, mixed $v, string $param): ?string
    {
        if ($v === null || $v === '') return null;
        // param: table,column OR table,column,exceptId
        $parts  = explode(',', $param);
        $table  = $parts[0] ?? '';
        $column = $parts[1] ?? $f;
        $except = $parts[2] ?? null;

        if (!$table) return null;

        $prefix = config('database.prefix', '');
        $sql    = "SELECT COUNT(*) as cnt FROM `{$prefix}{$table}` WHERE `{$column}` = ?";
        $params = [$v];

        if ($except) {
            $sql    .= " AND id != ?";
            $params[] = $except;
        }

        $row = DB::selectOne($sql, $params);
        if ($row && $row->cnt > 0)
            return "The {$f} has already been taken.";

        return null;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function parseRule(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        return [$parts[0], $parts[1] ?? ''];
    }
}
