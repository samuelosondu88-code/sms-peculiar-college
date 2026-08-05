<?php
if (defined('ENV_LOADED')) return;
define('ENV_LOADED', true);

function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');
            // Always populate the real environment (putenv) so getenv() works,
            // even when phpdotenv may have already set $_ENV/$_SERVER only.
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function env(string $key, $default = null) {
    $value = getenv($key);
    if ($value !== false && $value !== null && $value !== '') {
        return $value;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }
    return $default;
}

$envPaths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../.env.local',
];
foreach ($envPaths as $p) {
    if (file_exists($p)) { loadEnv($p); break; }
}
