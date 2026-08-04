<?php

/**
 * Application entry autoloader.
 *
 * If Composer has been installed (vendor/autoload.php exists) it is used to
 * load all dependencies and the PSR-4 "App\" namespace. Otherwise a lightweight
 * PSR-4 autoloader is registered so the application still boots on environments
 * where Composer is not available (e.g. some free shared hosts).
 *
 * Usage: require_once __DIR__ . '/autoload.php';
 */

declare(strict_types=1);

// Guard against double inclusion.
if (defined('APP_AUTOLOAD_LOADED')) {
    return;
}
define('APP_AUTOLOAD_LOADED', true);

$vendorAutoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($vendorAutoload)) {
    // Composer autoloader (also registers App\ PSR-4 and loads Helpers/helpers.php).
    require_once $vendorAutoload;
    return;
}

// ── Fallback lightweight PSR-4 autoloader (no Composer) ─────────────────────
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// Load legacy global helper functions (kept compatible with existing pages).
if (is_file($legacy = __DIR__ . '/app/Helpers/helpers.php')) {
    require_once $legacy;
}