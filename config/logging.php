<?php
/**
 * Production Error Logging Configuration
 * Include at the top of public/index.php or via auto_prepend_file
 */

// ── Error handler ──
function productionErrorHandler(int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    $logEntry = sprintf(
        "[%s] %s: %s in %s:%d\n",
        date('Y-m-d H:i:s T'),
        match ($severity) {
            E_WARNING, E_USER_WARNING => 'WARNING',
            E_NOTICE, E_USER_NOTICE => 'NOTICE',
            E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
            E_STRICT => 'STRICT',
            default => 'ERROR'
        },
        $message,
        $file,
        $line
    );
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
    @file_put_contents($logDir . '/php_errors.log', $logEntry, FILE_APPEND | LOCK_EX);
    return true;
}

function productionExceptionHandler(Throwable $e): void {
    $logEntry = sprintf(
        "[%s] UNCAUGHT: %s in %s:%d\nStack trace:\n%s\n",
        date('Y-m-d H:i:s T'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
    @file_put_contents($logDir . '/fatal_errors.log', $logEntry, FILE_APPEND | LOCK_EX);

    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        require __DIR__ . '/../error-500.php';
    }
    exit(1);
}

function productionShutdownHandler(): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logEntry = sprintf(
            "[%s] FATAL: %s in %s:%d\n",
            date('Y-m-d H:i:s T'),
            $error['message'],
            $error['file'],
            $error['line']
        );
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        @file_put_contents($logDir . '/fatal_errors.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// ── Register handlers ──
if (getenv('PHP_DISPLAY_ERRORS') === '0') {
    set_error_handler('productionErrorHandler');
    set_exception_handler('productionExceptionHandler');
    register_shutdown_function('productionShutdownHandler');
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// ── Create logs directory ──
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . '/.htaccess', "Deny from all\n");
}
