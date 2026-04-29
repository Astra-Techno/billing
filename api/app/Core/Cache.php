<?php

namespace App\Core;

class Cache
{
    private static string $path = '';

    public static function init(string $storagePath): void
    {
        self::$path = $storagePath;
        if (!is_dir(self::$path))
            mkdir(self::$path, 0755, true);
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $file = self::filePath($key);

        if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
            $data = unserialize(file_get_contents($file));
            if ($data !== false)
                return $data;
        }

        $value = $callback();
        file_put_contents($file, serialize($value), LOCK_EX);
        return $value;
    }

    public static function put(string $key, mixed $value, int $ttl = 86400): void
    {
        file_put_contents(self::filePath($key), serialize($value), LOCK_EX);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $file = self::filePath($key);
        if (!file_exists($file))
            return $default;

        $data = unserialize(file_get_contents($file));
        return $data !== false ? $data : $default;
    }

    public static function forget(string $key): void
    {
        $file = self::filePath($key);
        if (file_exists($file))
            unlink($file);
    }

    public static function flush(): void
    {
        foreach (glob(self::$path . '/*.cache') as $file)
            unlink($file);
    }

    private static function filePath(string $key): string
    {
        return self::$path . '/' . md5($key) . '.cache';
    }
}
