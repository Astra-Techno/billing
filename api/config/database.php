<?php

return [
    'default' => 'mysql',
    'prefix'  => $_ENV['DB_PREFIX'] ?? '',
    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => $_ENV['DB_HOST']     ?? '127.0.0.1',
            'port'     => $_ENV['DB_PORT']     ?? '3306',
            'database' => $_ENV['DB_DATABASE'] ?? 'billing',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset'  => 'utf8mb4',
        ],
    ],
];
