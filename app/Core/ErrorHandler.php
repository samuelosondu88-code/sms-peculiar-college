<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Central error/exception logging handler.
 *
 * Wires PHP error, exception and shutdown handlers to the shared logger()
 * (Monolog-backed when Composer is installed; daily-file fallback otherwise).
 * In production it hides internal details from the end user.
 */
final class ErrorHandler
{
    private static bool $registered = false;

    /**
     * Register the error, exception and shutdown handlers. Safe to call once.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);

        ini_set('display_errors', app_debug() ? '1' : '0');
        ini_set('log_errors', '1');
    }

    /**
     * Convert a PHP error into a logged entry.
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $level = match ($severity) {
            E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 'warning',
            E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED, E_STRICT => 'notice',
            default => 'error',
        };

        logger('error')->{$level}('[' . self::severityName($severity) . "] {$message} in {$file}:{$line}");

        return true;
    }

    /**
     * Log an uncaught exception and render a friendly page in non-debug mode.
     */
    public static function handleException(\Throwable $e): void
    {
        logger('error')->error(
            sprintf('Uncaught %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()),
            ['trace' => $e->getTraceAsString()]
        );

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, 'Fatal error: ' . $e->getMessage() . PHP_EOL);
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (app_debug()) {
            echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
        } else {
            require self::errorPage('500');
        }
        exit(1);
    }

    /**
     * Capture fatal errors (E_ERROR, E_PARSE, etc.) during shutdown.
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }
        if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        logger('error')->error(
            sprintf('FATAL: %s in %s:%d', $error['message'], $error['file'], $error['line'])
        );
    }

    public static function severityName(int $severity): string
    {
        return match ($severity) {
            E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 'Warning',
            E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED, E_STRICT => 'Notice',
            E_PARSE => 'Parse',
            E_CORE_ERROR, E_COMPILE_ERROR => 'Fatal',
            default => 'Error',
        };
    }

    private static function errorPage(string $code): string
    {
        $file = __DIR__ . '/../../error-' . $code . '.php';
        return is_file($file) ? $file : __DIR__ . '/../../error-500.php';
    }
}