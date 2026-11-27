<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{DB, Settings, Smtp, Theme};

/** Outgoing email: builds the message (plain + HTML, UTF-8) and sends it through the SMTP server set in the Tech portal. */
final class Mailer
{
    /** @var list<array{0:string,1:string,2:string,3:?string,4:string}> */
    private static array $queue = [];
    private static bool $hooked = false;

    public static function configured(): bool
    {
        return Settings::get('smtp_enabled', '0') === '1'
            && trim((string) Settings::get('smtp_host', '')) !== ''
            && self::fromAddress() !== '';
    }

    public static function fromAddress(): string
    {
        $a = trim((string) Settings::get('mail_from_email', ''));
        return filter_var($a, FILTER_VALIDATE_EMAIL) !== false ? $a : '';
    }

    /** A header value with no line breaks (stops header injection). */
    private static function clean(string $s): string { return trim(preg_replace('/[\r\n\0]+/', ' ', $s) ?? ''); }

    private static function encodeHeader(string $s): string
    {
        $s = self::clean($s);
        return preg_match('/^[\x20-\x7e]*$/', $s) === 1 ? $s : mb_encode_mimeheader($s, 'UTF-8', 'B', "\r\n");
    }

    /**
     * Send now. Never throws.
     * @return array{ok:bool, error:?string}
     */
    public static function send(string $to, string $subject, string $text, ?string $html = null, string $kind = 'other'): array
    {
        $to = self::clean($to);
        $subject = self::clean($subject);
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) return ['ok' => false, 'error' => 'Not a valid address'];
        if (!self::configured()) return ['ok' => false, 'error' => 'Email is not set up (Tech portal > Settings > Email)'];

        try {
            $from = self::fromAddress();
            $fromName = trim((string) Settings::get('mail_from_name', '')) ?: site_name();
            $host = preg_replace('/[^A-Za-z0-9.-]/', '', substr((string) strstr($from, '@'), 1)) ?: 'localhost';
            $boundary = '=_' . bin2hex(random_bytes(12));
            $h = [
                'Date: ' . date('r'),
                'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>',
                'To: <' . $to . '>',
                'Subject: ' . self::encodeHeader($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . '>',
                'MIME-Version: 1.0',
                'Auto-Submitted: auto-generated',
                'X-Auto-Response-Suppress: All',
            ];
            $reply = trim((string) Settings::get('mail_reply_to', ''));
            if ($reply !== '' && filter_var($reply, FILTER_VALIDATE_EMAIL) !== false) $h[] = 'Reply-To: <' . $reply . '>';

            $b64 = static fn(string $s): string => chunk_split(base64_encode($s), 76, "\r\n");
            if ($html === null) {
                $h[] = 'Content-Type: text/plain; charset=UTF-8';
                $h[] = 'Content-Transfer-Encoding: base64';
                $body = $b64($text);
            } else {
                $h[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
                $body = '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($text)
                    . '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($html)
                    . '--' . $boundary . "--\r\n";
            }

            $smtp = new Smtp(
                trim((string) Settings::get('smtp_host', '')),
                max(1, min(65535, (int) Settings::get('smtp_port', '587'))),
                in_array(Settings::get('smtp_security', 'starttls'), ['none', 'starttls', 'ssl'], true) ? (string) Settings::get('smtp_security', 'starttls') : 'starttls',
                Settings::get('smtp_verify_cert', '1') === '1'
            );
            $smtp->send(trim((string) Settings::get('smtp_user', '')), (string) Settings::get('smtp_password', ''), $from, [$to], implode("\r\n", $h) . "\r\n\r\n" . $body);
            self::log($to, $subject, $kind, true, null);
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            self::log($to, $subject, $kind, false, $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Send after the page has been delivered, so the visitor does not wait for the mail server. */
    public static function later(string $to, string $subject, string $text, ?string $html = null, string $kind = 'other'): void
    {
        if (!self::configured()) return;
        self::$queue[] = [$to, $subject, $text, $html, $kind];
        if (self::$hooked) return;
        self::$hooked = true;
        register_shutdown_function(static function (): void {
            if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            foreach (self::$queue as [$to, $s, $t, $h, $k]) self::send($to, $s, $t, $h, $k);
            self::$queue = [];
        });
    }

    private static function log(string $to, string $subject, string $kind, bool $ok, ?string $err): void
    {
        try {
            DB::exec(
                'INSERT INTO email_log (to_email, subject, kind, status, error) VALUES (?, ?, ?, ?, ?)',
                [mb_substr($to, 0, 190), mb_substr($subject, 0, 200), mb_substr($kind, 0, 30), $ok ? 'sent' : 'failed', $err === null ? null : mb_substr($err, 0, 400)]
            );
            if (random_int(1, 50) === 1) DB::exec('DELETE FROM email_log WHERE created_at < (NOW() - INTERVAL 90 DAY)');
        } catch (\Throwable $e) {
            error_log('email log failed: ' . $e->getMessage());
        }
    }

    public static function recent(int $n = 10): array
    {
        $n = max(1, min(50, $n));
        return DB::all("SELECT to_email, subject, kind, status, error, created_at FROM email_log ORDER BY id DESC LIMIT $n");
    }

    // ---- message layout ------------------------------------------------------------------------------

    /**
     * Build a ready message in the CURRENT interface language.
     * @param list<string> $lines paragraphs (plain text)
     * @return array{0:string,1:string} [plain text, html]
     */
    public static function compose(string $heading, array $lines, ?string $buttonLabel = null, ?string $buttonUrl = null, string $footer = ''): array
    {
        $site = site_name();
        $brand = Theme::primary();
        $text = $heading . "\n\n" . implode("\n\n", $lines);
        $btnHtml = '';
        if ($buttonLabel !== null && $buttonUrl !== null) {
            $text .= "\n\n" . $buttonLabel . ":\n" . $buttonUrl;
            $btnHtml = '<p style="margin:24px 0"><a href="' . e($buttonUrl) . '" style="background:' . e($brand) . ';color:' . e(Theme::ink($brand)) . ';text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold;display:inline-block">' . e($buttonLabel) . '</a></p>'
                . '<p style="font-size:12px;color:#667085;word-break:break-all">' . e($buttonUrl) . '</p>';
        }
        if ($footer !== '') $text .= "\n\n--\n" . $footer;
        $text .= "\n\n" . $site . ' - ' . abs_url('/') . "\n";

        $paras = '';
        foreach ($lines as $l) $paras .= '<p style="margin:0 0 14px;line-height:1.6">' . nl2br(e($l)) . '</p>';
        $html = '<!doctype html><html lang="' . e(\App\Core\I18n::locale()) . '"><body style="margin:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1d2430">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden">'
            . '<tr><td style="background:' . e($brand) . ';color:' . e(Theme::ink($brand)) . ';padding:18px 24px;font-size:18px;font-weight:bold">' . e($site) . '</td></tr>'
            . '<tr><td style="padding:24px"><h1 style="font-size:20px;margin:0 0 16px">' . e($heading) . '</h1>' . $paras . $btnHtml . '</td></tr>'
            . '<tr><td style="padding:14px 24px;background:#f9fafb;font-size:12px;color:#667085">' . ($footer !== '' ? e($footer) . '<br>' : '') . e($site) . ' · <a href="' . e(abs_url('/')) . '" style="color:#667085">' . e(abs_url('/')) . '</a></td></tr>'
            . '</table></td></tr></table></body></html>';
        return [$text, $html];
    }
}
