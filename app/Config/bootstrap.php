<?php

declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Loads the environment, the autoloader and the logging stack.
 * This is the single place where framework-level wiring happens.
 * Existing pages keep their current require chains; they are NOT affected.
 */

require_once __DIR__ . '/../../autoload.php';

use Dotenv\Dotenv;

// ── Environment ──────────────────────────────────────────────────────────────
$envFile = __DIR__ . '/../../.env';

if (class_exists(Dotenv::class) && is_file($envFile)) {
    try {
        $dotenv = Dotenv::createImmutable(dirname($envFile), '.env');
        $dotenv->safeLoad();
    } catch (Throwable $e) {
        // Fall back to the legacy env loader; never break the app here.
        require_once __DIR__ . '/../../config/env.php';
    }
} else {
    // Legacy loader (works without phpdotenv).
    require_once __DIR__ . '/../../config/env.php';
}

// Guarantee the real environment (putenv/getenv) is populated even when
// phpdotenv handled the load (its immutable adapter only sets $_ENV/$_SERVER,
// leaving getenv() empty — which the legacy config files rely on).
require_once __DIR__ . '/../../config/env.php';

// ── Logger ───────────────────────────────────────────────────────────────────
// Initialises the shared Monolog logger (or a file fallback when Composer is
// unavailable). Available globally via logger() helper.
if (!function_exists('logger')) {
    require_once __DIR__ . '/../Helpers/helpers.php';
}

// Only log the boot event for CLI runs; on web requests this would be noise.
if (PHP_SAPI === 'cli') {
    logger('bootstrap')->info('Application bootstrapped', ['env' => app_env() ?: 'production']);
}