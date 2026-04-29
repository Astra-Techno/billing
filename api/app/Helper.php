<?php

use App\Base\Factory;
use App\Base\LogManager;
use App\Base\Query;
use App\Base\Sql;
use App\Base\Task;
use App\Base\Entity;
use App\Core\Auth;
use App\Core\Cache;
use App\Core\DB;
use App\Core\RequestHolder;

// ── Framework helpers ─────────────────────────────────────────────────────────

if (!function_exists('debugMode')) {
    function debugMode(): bool
    {
        return filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $configs = [];

        [$file, $dotKey] = array_pad(explode('.', $key, 2), 2, null);

        if (!isset($configs[$file])) {
            $path = dirname(__DIR__) . "/config/{$file}.php";
            $configs[$file] = file_exists($path) ? require $path : [];
        }

        if ($dotKey === null) return $configs[$file] ?? $default;

        $keys  = explode('.', $dotKey);
        $value = $configs[$file];
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value))
                return $default;
            $value = $value[$k];
        }
        return $value;
    }
}

// ── Request helpers ───────────────────────────────────────────────────────────

if (!function_exists('request')) {
    function request(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return RequestHolder::get();
        return RequestHolder::input($key, $default);
    }
}

// ── Auth helpers ──────────────────────────────────────────────────────────────

if (!function_exists('user')) {
    function user(): ?object
    {
        return Auth::user();
    }
}

if (!function_exists('userId')) {
    function userId(): ?int
    {
        return Auth::id();
    }
}

// ── DataForge helpers ─────────────────────────────────────────────────────────

if (!function_exists('Sql')) {
    function Sql(string $name, array $input = [], string $db = ''): Sql
    {
        return (new Sql())->load($name, $input, $db);
    }
}

if (!function_exists('Query')) {
    function Query(string $name): Query
    {
        return new Query($name);
    }
}

if (!function_exists('RunTask')) {
    function RunTask(string $name, array $input = []): array
    {
        return Task::run($name, $input);
    }
}

if (!function_exists('GetEntity')) {
    function GetEntity(string $name, array $input = []): Entity
    {
        return Entity::load($name, $input);
    }
}

// ── Database helpers ──────────────────────────────────────────────────────────

if (!function_exists('dbQuote')) {
    function dbQuote(mixed $value, string $connection = 'mysql'): string
    {
        if (is_null($value))   return 'NULL';
        if (is_bool($value))   return $value ? '1' : '0';
        if (is_numeric($value)) return (string)$value;
        return DB::getPdo($connection)->quote((string)$value);
    }
}

if (!function_exists('dbPrefix')) {
    function dbPrefix(): string
    {
        return DB::prefix();
    }
}

// ── Value helpers ─────────────────────────────────────────────────────────────

if (!function_exists('isEmpty')) {
    function isEmpty(mixed $value): bool
    {
        if (is_array($value)) return empty($value);
        return $value === null || $value === '' || $value === false;
    }
}

if (!function_exists('GetValue')) {
    function GetValue(array|object $data, string $key, mixed $default = ''): mixed
    {
        if (is_object($data)) $data = (array)$data;
        return $data[$key] ?? $default;
    }
}

if (!function_exists('toFloat')) {
    function toFloat(mixed $value): float
    {
        return (float)str_replace(',', '', (string)$value);
    }
}

if (!function_exists('toCents')) {
    function toCents(mixed $value): int
    {
        return (int)round(toFloat($value) * 100);
    }
}

if (!function_exists('fromCents')) {
    function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }
}

// ── String helpers ────────────────────────────────────────────────────────────

if (!function_exists('slug')) {
    function slug(string $str): string
    {
        $str = strtolower(trim($str));
        $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
        return preg_replace('/[\s-]+/', '-', $str);
    }
}

if (!function_exists('generateToken')) {
    function generateToken(int $length = 40): string
    {
        return bin2hex(random_bytes($length));
    }
}

if (!function_exists('uuid')) {
    function uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

// ── Date helpers ──────────────────────────────────────────────────────────────

if (!function_exists('now')) {
    function now(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format);
    }
}

if (!function_exists('today')) {
    function today(): string
    {
        return date('Y-m-d');
    }
}

// ── Cache helper ──────────────────────────────────────────────────────────────

if (!function_exists('cache')) {
    function cache(string $key, mixed $default = null, ?int $ttl = null, ?callable $callback = null): mixed
    {
        if ($callback !== null && $ttl !== null)
            return Cache::remember($key, $ttl, $callback);
        return Cache::get($key, $default);
    }
}

// ── Logging ───────────────────────────────────────────────────────────────────

if (!function_exists('FileLogger')) {
    function FileLogger(string $channel, string $message, string $level = 'info', array $context = []): void
    {
        match (strtolower($level)) {
            'error'   => LogManager::logError($channel, $message, $context),
            'warning' => LogManager::logWarning($channel, $message, $context),
            default   => LogManager::logInfo($channel, $message, $context),
        };
    }
}

// ── HTTP helpers ──────────────────────────────────────────────────────────────

if (!function_exists('HttpClient')) {
    function HttpClient(string $url, array $options = []): array
    {
        $method  = strtoupper($options['method'] ?? 'GET');
        $headers = $options['headers'] ?? [];
        $body    = $options['body']    ?? null;
        $timeout = $options['timeout'] ?? 30;

        $curlHeaders = array_map(
            fn($k, $v) => "{$k}: {$v}",
            array_keys($headers),
            array_values($headers)
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_SSL_VERIFYPEER => !debugMode(),
        ]);

        if ($body !== null)
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);

        $response   = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error      = curl_error($ch);
        curl_close($ch);

        $decoded = json_decode($response ?: '', true);

        return [
            'status'  => $statusCode,
            'body'    => $decoded ?? $response,
            'raw'     => $response,
            'error'   => $error,
            'success' => $statusCode >= 200 && $statusCode < 300,
        ];
    }
}

// ── Email helper (placeholder — swap with real mailer later) ──────────────────

if (!function_exists('Email')) {
    function Email(string $to, string $subject, string $body, array $options = []): bool
    {
        $from    = $options['from']    ?? ($_ENV['MAIL_FROM'] ?? 'noreply@example.com');
        $headers = "From: {$from}\r\nContent-Type: text/html; charset=UTF-8\r\n";
        return mail($to, $subject, $body, $headers);
    }
}

// ── JSON response helper ──────────────────────────────────────────────────────

if (!function_exists('jsonResponse')) {
    function jsonResponse(mixed $data, int $status = 200): array
    {
        return ['status' => $status, 'data' => $data];
    }
}
