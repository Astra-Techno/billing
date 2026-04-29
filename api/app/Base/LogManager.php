<?php

namespace App\Base;

class LogManager
{
    public static function logInfo(string $channel, string $message, array $context = []): void
    {
        self::write($channel, 'INFO', $message, $context);
    }

    public static function logError(string $channel, string $message, array $context = []): void
    {
        self::write($channel, 'ERROR', $message, $context);
    }

    public static function logWarning(string $channel, string $message, array $context = []): void
    {
        self::write($channel, 'WARNING', $message, $context);
    }

    public static function addEmptyLine(string $channel): void
    {
        $path = self::path($channel);
        self::ensureDir($path);
        file_put_contents($path, PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function write(string $channel, string $level, string $message, array $context): void
    {
        $path = self::path($channel);
        self::ensureDir($path);

        $ts      = date('Y-m-d H:i:s');
        $ctx     = $context ? ' ' . json_encode($context) : '';
        $entry   = "[{$ts}] {$level}: {$message}{$ctx}" . PHP_EOL;

        file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
    }

    private static function path(string $channel): string
    {
        $name = strtolower(str_replace(['\\', '/'], '_', $channel));
        $date = date('Y/m/d');
        return dirname(__DIR__, 2) . "/storage/logs/{$date}/{$name}.log";
    }

    private static function ensureDir(string $filePath): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir))
            mkdir($dir, 0755, true);
    }
}
