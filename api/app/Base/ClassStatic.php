<?php

namespace App\Base;

class ClassStatic
{
    protected static array $_errors = [];

    final public static function raiseError(string $str, int $code = 400): never
    {
        throw new \Exception($str, $code);
    }

    public static function setError(string $msg): false
    {
        self::$_errors[] = ['error' => $msg, 'trace' => self::getCaller()];
        return false;
    }

    public static function getError(bool $full = false): mixed
    {
        $error = array_pop(self::$_errors);
        if (!$error)
            return $full ? ['error' => '', 'trace' => ''] : '';

        return $full ? $error : $error['error'];
    }

    final public static function getCaller(): string
    {
        foreach (debug_backtrace() as $m) {
            $method = $m['function'] ?? '';
            if (in_array(strtolower($method), ['seterror', 'raiseerror', 'getcaller']))
                continue;
            $class = $m['class'] ?? '';
            $line  = $m['line']  ?? '';
            return "{$class}::{$method} (line:{$line})";
        }
        return '';
    }
}
