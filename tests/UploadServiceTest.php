<?php

namespace App\Tests;

use App\Services\UploadService;
use PHPUnit\Framework\TestCase;

final class UploadServiceTest extends TestCase
{
    private const PNG =
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function tmpFile(string $content): string
    {
        $f = tempnam(sys_get_temp_dir(), 'up_');
        file_put_contents($f, $content);
        return $f;
    }

    private function uploadEntry(string $name, string $tmp, int $size): array
    {
        return ['name' => $name, 'type' => '', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => $size];
    }

    public function testValidPngAccepted(): void
    {
        $png = base64_decode(self::PNG, true);
        $tmp = $this->tmpFile($png);
        try {
            $r = UploadService::validate($this->uploadEntry('photo.png', $tmp, strlen($png)), ['png'], 2_000_000);
            self::assertTrue($r['ok'], implode('; ', $r['errors']));
        } finally {
            @unlink($tmp);
        }
    }

    public function testPhpContentDisguisedAsPngRejected(): void
    {
        $tmp = $this->tmpFile('<?php echo 1; ?>');
        try {
            $r = UploadService::validate($this->uploadEntry('photo.png', $tmp, 16), ['png'], 2_000_000);
            self::assertFalse($r['ok']);
        } finally {
            @unlink($tmp);
        }
    }

    public function testDoubleExtensionRejected(): void
    {
        $png = base64_decode(self::PNG, true);
        $tmp = $this->tmpFile($png);
        try {
            $r = UploadService::validate($this->uploadEntry('shell.php.gif', $tmp, strlen($png)), ['gif'], 2_000_000);
            self::assertFalse($r['ok']);
        } finally {
            @unlink($tmp);
        }
    }

    public function testDisallowedExtensionRejected(): void
    {
        $png = base64_decode(self::PNG, true);
        $tmp = $this->tmpFile($png);
        try {
            $r = UploadService::validate($this->uploadEntry('malware.exe', $tmp, strlen($png)), ['png'], 2_000_000);
            self::assertFalse($r['ok']);
            self::assertStringContainsString('not allowed', implode('; ', $r['errors']));
        } finally {
            @unlink($tmp);
        }
    }

    public function testOversizeRejected(): void
    {
        $png = base64_decode(self::PNG, true);
        $tmp = $this->tmpFile($png);
        try {
            $r = UploadService::validate($this->uploadEntry('photo.png', $tmp, strlen($png)), ['png'], 10);
            self::assertFalse($r['ok']);
            self::assertStringContainsString('limit', implode('; ', $r['errors']));
        } finally {
            @unlink($tmp);
        }
    }

    public function testUploadErrorRejected(): void
    {
        $r = UploadService::validate(
            ['name' => 'a.png', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0],
            ['png'],
            1_000_000
        );
        self::assertFalse($r['ok']);
    }
}