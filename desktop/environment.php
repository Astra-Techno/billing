<?php
// Shared by the local router and maintenance CLI. Never reads the online .env.
function desktopEnvironment(): array
{
    $home = getenv('BILLING_DESKTOP_DATA');
    if (!$home || !is_file($home . '/state.json')) throw new RuntimeException('Offline installation is not initialized.');
    $state = json_decode(file_get_contents($home . '/state.json'), true, 512, JSON_THROW_ON_ERROR);
    $port = (int)(getenv('BILLING_DESKTOP_PORT') ?: 18765);
    $networkMode = getenv('BILLING_DESKTOP_NETWORK_MODE') === 'MainPc' ? 'MainPc' : 'SinglePc';
    $host = $networkMode === 'MainPc' ? (getenv('BILLING_DESKTOP_HOST') ?: gethostname()) : '127.0.0.1';
    $_ENV = array_merge($_ENV, [
        'APP_ENV' => 'desktop', 'APP_DEBUG' => 'false', 'DESKTOP_MODE' => 'true',
        'APP_URL' => "http://$host:$port/api", 'FRONTEND_URL' => "http://$host:$port",
        'DESKTOP_NETWORK_MODE' => $networkMode, 'DESKTOP_NETWORK_HOST' => $host,
        'DB_HOST' => '127.0.0.1', 'DB_PORT' => (string)$state['db_port'],
        'DB_DATABASE' => $state['database'], 'DB_USERNAME' => 'root', 'DB_PASSWORD' => $state['password'],
        'DESKTOP_DATA' => $home, 'STORAGE_PATH' => $home . '/' . $state['storage'],
    ]);
    return $state;
}
