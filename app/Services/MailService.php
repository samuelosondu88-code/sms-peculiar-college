<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Centralised email service.
 *
 * Uses PHPMailer (SMTP) when it is installed via Composer; otherwise falls back
 * to PHP's mail() so delivery still works on hosts without SMTP credentials.
 * Supports rendering HTML templates from storage/templates/email with a shared
 * layout.
 *
 * Usage (procedural helpers, see helpers.php):
 *     send_email_template('welcome', 'you@x.com', 'Your account is ready', [...]);
 *     App\Services\MailService::send('you@x.com', 'Subject', '<b>Hi</b>');
 */
final class MailService
{
    /**
     * Maximum template count to prevent path traversal via the template name.
     */
    private const TEMPLATE_DIR = __DIR__ . '/../../storage/templates/email';

    /**
     * Send an HTML email, using PHPMailer/SMTP when available, else mail().
     */
    public static function send(string $to, string $subject, string $htmlBody, ?string $altBody = null, ?string $fromName = null): bool
    {
        self::ensureSchoolParameters();

        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return self::sendViaPhpMailer($to, $subject, $htmlBody, $altBody, $fromName);
        }

        return self::sendViaMailFunction($to, $subject, $htmlBody, $altBody, $fromName);
    }

    /**
     * Render a stored template (optionally wrapped in the shared layout) and send it.
     *
     * @param string $template Template basename without extension (e.g. 'welcome').
     * @param string $to       Recipient email.
     * @param string $subject  Email subject.
     * @param array  $data     Variables passed into the template (under $data).
     * @param string $name     Human-readable recipient name for greeting lines.
     */
    public static function sendTemplate(string $template, string $to, string $subject, array $data = [], ?string $name = null): bool
    {
        self::ensureSchoolParameters();

        $template = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $template);
        $file = self::TEMPLATE_DIR . '/' . $template . '.php';

        if (!is_file($file)) {
            logger('mail')->error('Email template not found', ['template' => $template, 'to' => $to]);
            return false;
        }

        $data['subject'] = $subject;
        $content = self::render($file, ['data' => $data, 'name' => $name]);
        $htmlBody = self::renderLayout((string) $subject, $content);
        $altBody = self::plainTextFromHtml($htmlBody);

        return self::send($to, $subject, $htmlBody, $altBody);
    }

    /**
     * Render a PHP template file into a string via output buffering.
     */
    public static function render(string $templateFile, array $vars = []): string
    {
        self::ensureSchoolParameters();
        extract($vars, EXTR_SKIP);
        ob_start();
        include $templateFile;
        return (string) ob_get_clean();
    }

    private static function renderLayout(string $title, string $content): string
    {
        $vars = ['title' => $title, 'content' => $content];
        if (!is_file(self::TEMPLATE_DIR . '/layout.php')) {
            return $content;
        }
        return self::render(self::TEMPLATE_DIR . '/layout.php', $vars);
    }

    /**
     * Ensure the school/app constants (SCHOOL_NAME, SCHOOL_EMAIL, ...) exist.
     * Templates rely on them; they are normally defined by config/app.php, which
     * may not have been loaded when the service is used in isolation.
     */
    private static function ensureSchoolParameters(): void
    {
        if (defined('SCHOOL_NAME')) {
            return;
        }
        $config = __DIR__ . '/../../config/app.php';
        if (is_file($config)) {
            require_once $config;
        }
    }

    private static function sendViaPhpMailer(string $to, string $subject, string $htmlBody, ?string $altBody, ?string $fromName): bool
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $smtpHost = env('SMTP_HOST');
            if ($smtpHost) {
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->Port = (int)(env('SMTP_PORT') ?: 587);
                $enc = strtolower(env('SMTP_ENCRYPTION') ?: 'tls');
                if ($enc === 'tls') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($enc === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                }
                if (env('SMTP_USERNAME')) {
                    $mail->SMTPAuth = true;
                    $mail->Username = env('SMTP_USERNAME');
                    $mail->Password = env('SMTP_PASSWORD') ?: '';
                }
                $mail->SMTPDebug = 0;
            }

            $mail->CharSet = 'UTF-8';
            $from = env('SMTP_USERNAME') ?: (defined('SCHOOL_EMAIL') ? SCHOOL_EMAIL : 'no-reply@localhost');
            $mail->setFrom($from, $fromName ?: (defined('SCHOOL_NAME') ? SCHOOL_NAME : ''));
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $altBody ?? self::regTextFromHtml($htmlBody);
            return $mail->send();
        } catch (\Throwable $e) {
            logger('mail')->error('Mail failed via SMTP', ['error' => $e->getMessage(), 'to' => $to]);
            return false;
        }
    }

    private static function sendViaMailFunction(string $to, string $subject, string $htmlBody, ?string $altBody, ?string $fromName): bool
    {
        $fromName = $fromName ?: (defined('SCHOOL_NAME') ? SCHOOL_NAME : '');
        $fromEmail = defined('SCHOOL_EMAIL') ? SCHOOL_EMAIL : 'no-reply@localhost';
        $headers = "From: $fromName <$fromEmail>\r\n";
        $headers .= "Reply-To: $fromEmail\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $result = @mail($to, $subject, $htmlBody, $headers);
        if (!$result) {
            logger('mail')->error('mail() send failed', ['to' => $to, 'subject' => $subject]);
        }
        return $result;
    }

    private static function regTextToHtml(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    private static function plainTextFromHtml(string $html): string
    {
        return self::regTextToHtml($html);
    }
}