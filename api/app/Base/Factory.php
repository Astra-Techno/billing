<?php

namespace App\Base;

class Factory extends ClassStatic
{
    /**
     * Resolve a class name from a namespace + dotted name.
     *
     * Examples:
     *   findClass('App\Sql', 'User')          → ['App\Sql\User', null]
     *   findClass('App\Sql', 'User.list')     → ['App\Sql\User', 'list']
     *   findClass('App\Task', 'Auth.login')   → ['App\Task\Auth', 'login']
     *   findClass('App\Entity', 'Billing/Invoice.get') → ['App\Entity\Billing\Invoice', 'get']
     */
    public static function findClass(string $namespace, string $name): array
    {
        // Split method from name (dot separator)
        $method = null;
        if (str_contains($name, '.')) {
            [$name, $method] = explode('.', $name, 2);
        }

        // Normalize slashes → namespace separators
        $name = str_replace('/', '\\', $name);

        // Build fully-qualified class name
        $className = rtrim($namespace, '\\') . '\\' . $name;

        if (!class_exists($className))
            self::raiseError("Class {$className} not found!");

        return [$className, $method];
    }

    /**
     * Instantiate a class from namespace + name, optionally calling a method.
     */
    public static function make(string $namespace, string $name, array $input = []): object
    {
        [$className, $method] = self::findClass($namespace, $name);
        $instance = new $className();

        if ($method && method_exists($instance, $method))
            $instance->$method($input);

        return $instance;
    }
}
