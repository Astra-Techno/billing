<?php

namespace App\Base;

use AllowDynamicProperties;

#[AllowDynamicProperties]
class ClassObject
{
    protected array $_errors = [];

    public function raiseError(string $str, int $code = 400): never
    {
        throw new \Exception($str, $code);
    }

    public function setError(string $msg): false
    {
        $this->_errors[] = ['message' => $msg, 'trace' => $this->getCaller()];
        return false;
    }

    public function getError(bool $full = false): mixed
    {
        $error = array_pop($this->_errors);
        if (!$error)
            return $full ? ['message' => '', 'trace' => ''] : '';

        return $full ? $error : $error['message'];
    }

    public function set(string $property, mixed $value): void
    {
        if (!is_object($value) && !is_array($value))
            $value = is_string($value) ? trim($value) : $value;

        $this->$property = $value;
    }

    protected function getCaller(): array
    {
        foreach (debug_backtrace() as $m) {
            $method = $m['function'] ?? '';
            if (in_array(strtolower($method), ['seterror', 'raiseerror', 'getcaller', '__get', 'get']))
                continue;

            return [
                'class'  => $m['class']    ?? '',
                'method' => $method,
                'line'   => $m['line']     ?? '',
                'file'   => $m['file']     ?? '',
            ];
        }
        return ['class' => '', 'method' => '', 'line' => '', 'file' => ''];
    }
}
