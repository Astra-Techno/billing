<?php

namespace App\Core;

use Psr\Http\Message\ServerRequestInterface;

class RequestHolder
{
    private static ?ServerRequestInterface $request = null;

    public static function set(ServerRequestInterface $request): void
    {
        self::$request = $request;
    }

    public static function get(): ?ServerRequestInterface
    {
        return self::$request;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    public static function all(): array
    {
        if (!self::$request)
            return [];

        $query  = self::$request->getQueryParams() ?? [];
        $body   = self::$request->getParsedBody()  ?? [];

        return array_merge($query, (array) $body);
    }

    public static function header(string $name): string
    {
        if (!self::$request)
            return '';

        $values = self::$request->getHeader($name);
        return $values[0] ?? '';
    }

    public static function ip(): string
    {
        if (!self::$request)
            return '';

        $params = self::$request->getServerParams();
        return $params['REMOTE_ADDR'] ?? '';
    }

    public static function bearerToken(): ?string
    {
        $auth = self::header('Authorization');
        if (str_starts_with($auth, 'Bearer '))
            return substr($auth, 7);
        return null;
    }
}
