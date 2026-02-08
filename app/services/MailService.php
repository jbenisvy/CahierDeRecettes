<?php

class MailService
{
    public static function send(string $to, string $subject, string $html, string $text = ''): bool
    {
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
            $mail->Host = getenv('SMTP_HOST') ?: '';
            $mail->SMTPAuth = true;
            $mail->Username = getenv('SMTP_USER') ?: '';
            $mail->Password = getenv('SMTP_PASS') ?: '';
            $mail->Port = (int) (getenv('SMTP_PORT') ?: 465);
            $mail->SMTPSecure = getenv('SMTP_SECURE') ?: 'ssl';

            $from = getenv('MAIL_FROM') ?: 'support@localhost';
            $fromName = getenv('MAIL_FROM_NAME') ?: 'Memoire de Saveurs';

            $mail->setFrom($from, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text ?: strip_tags($html);
            $mail->isHTML(true);

            return $mail->send();
        } catch (Throwable $e) {
            error_log('Mail error: ' . $e->getMessage());
            return false;
        }
    }

    private static function sendWithMail(string $to, string $subject, string $html): bool
    {
        $from = getenv('MAIL_FROM') ?: 'support@localhost';
        $fromName = getenv('MAIL_FROM_NAME') ?: 'Memoire de Saveurs';
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $fromName . ' <' . $from . '>';

        return mail($to, $subject, $html, implode("\r\n", $headers));
    }
}
