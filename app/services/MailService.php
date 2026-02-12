<?php

class MailService
{
    private static ?string $lastError = null;
    private static bool $envLoaded = false;

    private static function ensureEnvLoaded(): void
    {
        if (self::$envLoaded) {
            return;
        }

        $envFile = dirname(__DIR__, 2) . '/config/env.php';
        if (is_readable($envFile)) {
            require_once $envFile;
        }

        self::$envLoaded = true;
    }

    private static function env(string $key, string $default = ''): string
    {
        self::ensureEnvLoaded();

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return (string) $value;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string) $_SERVER[$key];
        }
        return $default;
    }

    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    public static function send(string $to, string $subject, string $html, string $text = ''): bool
    {
        self::$lastError = null;

        if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            if (is_readable($autoload)) {
                require_once $autoload;
            }
        }

        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            return self::sendWithPHPMailer($to, $subject, $html, $text);
        }

        return self::sendWithMail($to, $subject, $html);
    }

    private static function sendWithPHPMailer(string $to, string $subject, string $html, string $text = ''): bool
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = self::env('SMTP_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = self::env('SMTP_USER');
            $mail->Password = self::env('SMTP_PASS');
            $mail->Port = (int) self::env('SMTP_PORT', '465');
            $mail->SMTPSecure = self::env('SMTP_SECURE', 'ssl');
            $mail->Timeout = 30;
            $mail->SMTPAutoTLS = true;

            if (self::env('MAIL_DEBUG', '0') === '1') {
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = static function (string $str, int $level): void {
                    $line = '[SMTP][' . $level . '] ' . $str;
                    error_log($line);
                    @error_log($line . PHP_EOL, 3, dirname(__DIR__, 2) . '/error.log');
                };
            }

            $from = self::env('MAIL_FROM', self::env('SMTP_USER', 'support@localhost'));
            $fromName = self::env('MAIL_FROM_NAME', 'Memoire de Saveurs');

            $mail->setFrom($from, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text ?: strip_tags($html);
            $mail->isHTML(true);

            $ok = $mail->send();
            if (!$ok) {
                self::$lastError = 'PHPMailer send() returned false';
            }
            return $ok;
        } catch (Throwable $e) {
            $line = 'Mail error: ' . $e->getMessage();
            self::$lastError = $e->getMessage();
            error_log($line);
            @error_log($line . PHP_EOL, 3, dirname(__DIR__, 2) . '/error.log');
            return false;
        }
    }

    private static function sendWithMail(string $to, string $subject, string $html): bool
    {
        $from = self::env('MAIL_FROM', self::env('SMTP_USER', 'support@localhost'));
        $fromName = self::env('MAIL_FROM_NAME', 'Memoire de Saveurs');
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $fromName . ' <' . $from . '>';

        $ok = mail($to, $subject, $html, implode("\r\n", $headers));
        if (!$ok) {
            $lastPhpError = error_get_last();
            self::$lastError = $lastPhpError['message'] ?? 'mail() returned false';
            $line = 'Mail error (mail()): ' . self::$lastError;
            error_log($line);
            @error_log($line . PHP_EOL, 3, dirname(__DIR__, 2) . '/error.log');
        }
        return $ok;
    }
}
