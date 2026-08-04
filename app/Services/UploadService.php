<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Hardened file-upload validation and storage.
 *
 * - Validates the upload error code, size, extension and real MIME (magic bytes).
 * - Rejects path-traversal/double-extension spoofing.
 * - Persists under a cryptographically random name so the served filename can
 *   never be attacker-controlled.
 */
final class UploadService
{
    /** extension => expected concrete MIME type of real file content. */
    private const MIME_MAP = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
    ];

    /**
     * Validate an uploaded file entry.
     *
     * @return array{ok: bool, errors: string[]}
     */
    public static function validate(array $file, array $allowedExtensions, int $maxSize): array
    {
        $errors = [];

        if (!isset($file['error'], $file['name'], $file['tmp_name'], $file['size'])) {
            return ['ok' => false, 'errors' => ['Invalid upload payload.']];
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'errors' => ['File upload failed with error code: ' . $file['error']]];
        }

        $ext = self::extension($file['name']);
        if ($ext === '' || !in_array($ext, $allowedExtensions, true)) {
            $errors[] = "File type '$ext' is not allowed. Allowed: " . implode(', ', $allowedExtensions);
        }

        // Reject obvious double-extension / traversal spoofing in the client name.
        if (preg_match('/\.(php|phtml|php\d|phar|sh|exe|msi|bat|cmd|js|svg|htaccess|py|rb|so|dll)(\.|$)/i', (string) $file['name'])) {
            $errors[] = 'File name looks unsafe and was rejected.';
        }

        $size = (int) $file['size'];
        if ($size <= 0 || $size > $maxSize) {
            $errors[] = 'File size exceeds the ' . (int) round($maxSize / (1024 * 1024)) . 'MB limit.';
        }

        // Verify real content MIME matches the declared extension.
        $mime = self::detectMime((string) $file['tmp_name']);
        if ($ext !== '' && isset(self::MIME_MAP[$ext]) && $mime !== '' && !in_array($mime, self::MIME_MAP[$ext], true)) {
            $errors[] = 'File content (' . $mime . ') does not match its extension.';
        } elseif ($ext !== '' && $mime === '' && is_file((string) $file['tmp_name'])) {
            $errors[] = 'Unable to read file content for validation.';
        }

        return ['ok' => empty($errors), 'errors' => $errors];
    }

    /**
     * Validate and move an upload into storage. Returns the stored relative path
     * (e.g. "documents/<rand>.<ext>") or null on failure.
     */
    public static function store(array $file, string $subdir, array $allowedExtensions, int $maxSize): ?string
    {
        $valid = self::validate($file, $allowedExtensions, $maxSize);
        if (!$valid['ok']) {
            logger('upload')->warning('Upload rejected', ['errors' => $valid['errors']]);
            return null;
        }

        $ext = self::extension((string) $file['name']);
        $root = dirname(__DIR__, 2); // project root
        $targetDir = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . trim((string) $subdir, '/\\');
        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                return null;
            }
        }

        $newName = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $newName;

        if (!\is_uploaded_file((string) $file['tmp_name']) || !\move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
            logger('upload')->error('move_uploaded_file failed', ['target' => $targetPath]);
            return null;
        }

        return $subdir . '/' . $newName;
    }

    private static function extension(string $name): string
    {
        return strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    }

    private static function detectMime(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            return (string) $finfo->file($path);
        }
        return '';
    }
}