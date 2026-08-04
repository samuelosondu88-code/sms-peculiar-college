<?php

declare(strict_types=1);

/**
 * New procedural helpers for the modularised application.
 *
 * IMPORTANT: These helper names MUST NOT clash with the legacy global
 * functions already defined in includes/functions.php. This file only adds
 * NEW helpers introduced by the refactor.
 *
 * Loaded automatically by Composer ("files") and by the fallback autoloader.
 */

if (!function_exists('logger')) {
    /**
     * Return (and lazily create) a bored logging instance.
     *
     * Uses Monolog when available (Composer install); falls back to a simple
     * daily file logger so the app still works on shared hosts.
     *
     * @param string|null $channel Logical channel (e.g. 'auth', 'payment', 'results').
     */
    function logger(?string $channel = null): object
    {
        static $instances = [];

        $channel ??= 'app';

        if (isset($instances[$channel])) {
            return $instances[$channel];
        }

        if (class_exists(\Monolog\Logger::class)) {
            $logger = new \Monolog\Logger($channel);
            $handler = new \Monolog\Handler\RotatingFileHandler(
                __DIR__ . '/../../storage/logs/' . $channel . '.log',
                14,  // keep 14 daily files
                \Monolog\Level::Debug,
                true
            );
            $handler->setFormatter(new \Monolog\Formatter\LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s'
            ));
            $logger->pushHandler($handler);

            return $instances[$channel] = $logger;
        }

        // ── Fallback: minimal PSR-3-ish file logger ──────────────────────
        $instances[$channel] = new class($channel) {
            /* @var string */
            private $channel;

            public function __construct(string $channel)
            {
                $this->channel = $channel;
            }

            public function __call(string $method, array $args): void
            {
                $level = strtoupper($method);
                $dir = __DIR__ . '/../../storage/logs';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                $line = sprintf(
                    "[%s] %s.%s %s\n",
                    date('Y-m-d H:i:s'),
                    $this->channel,
                    $level,
                    is_string($args[0] ?? null) ? $args[0] : json_encode($args[0] ?? '')
                );
                @file_put_contents(
                    $dir . '/' . $this->channel . '-' . date('Y-m-d') . '.log',
                    $line,
                    FILE_APPEND | LOCK_EX
                );
            }
        };

        return $instances[$channel];
    }
}

if (!function_exists('storage_path')) {
    /**
     * Absolute path to a path under the storage directory.
     */
    function storage_path(string $path = ''): string
    {
        return __DIR__ . '/../../storage' . ($path !== '' ? '/' . $path : '');
    }
}

if (!function_exists('config_path')) {
    /**
     * Absolute path to a path under the config directory.
     */
    function config_path(string $path = ''): string
    {
        return __DIR__ . '/../../config' . ($path !== '' ? '/' . $path : '');
    }
}

if (!function_exists('app_env')) {
    /**
     * Returns the current application environment value.
     */
    function app_env(): string
    {
        return (string) (getenv('APP_ENV') ?: 'production');
    }
}

if (!function_exists('app_debug')) {
    /**
     * Whether debug mode is enabled from the environment.
     */
    function app_debug(): bool
    {
        return filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('uuid_v4')) {
    /**
     * Returns a UUID v4 string. Prefers ramsey/uuid when available.
     */
    function uuid_v4(): string
    {
        if (class_exists(\Ramsey\Uuid\Uuid::class)) {
            try {
                return \Ramsey\Uuid\Uuid::uuid4()->toString();
            } catch (Throwable $e) {
                // fall through
            }
        }

        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }
}

if (!function_exists('is_maintenance_mode')) {
    /**
     * Whether maintenance mode is enabled via the APP_MAINTENANCE env flag.
     */
    function is_maintenance_mode(): bool
    {
        return filter_var(getenv('APP_MAINTENANCE') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('maintenance_bypass_token')) {
    /**
     * A secret token (derived from APP_KEY) that lets an admin bypass the
     * maintenance page. Empty when APP_KEY is not configured.
     */
    function maintenance_bypass_token(): string
    {
        $key = getenv('APP_KEY') ?: '';
        return $key ? hash_hmac('sha256', 'maintenance-bypass', $key) : '';
    }
}

if (!function_exists('send_email_template')) {
    /**
     * Send a templated email through App\Services\MailService.
     *
     * @param string      $template Template basename without extension.
     * @param string      $to       Recipient email.
     * @param string      $subject  Email subject.
     * @param array       $data     Template variables.
     * @param string|null $name     Recipient display name.
     */
    function send_email_template(string $template, string $to, string $subject, array $data = [], ?string $name = null): bool
    {
        return \App\Services\MailService::sendTemplate($template, $to, $subject, $data, $name);
    }
}