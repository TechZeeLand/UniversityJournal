<?php
declare(strict_types=1);
namespace App\Core;

/**
 * A small SMTP client (no libraries): implicit TLS (port 465), STARTTLS (587) or plain (25, local relays),
 * AUTH PLAIN / LOGIN. Throws \RuntimeException with a readable message that names the failing step.
 */
final class Smtp
{
    /** @var resource|null */
    private $fp = null;
    /** @var list<string> */
    private array $caps = [];

    public function __construct(
        private string $host,
        private int $port,
        private string $security,      // none | starttls | ssl
        private bool $verifyCert = true,
        private int $timeout = 12
    ) {}

    /**
     * @param list<string> $rcpts bare addresses
     * @param string $data full message (headers + body), any line endings
     */
    public function send(string $user, string $pass, string $from, array $rcpts, string $data): void
    {
        try {
            $this->connect();
            $this->greet();
            if ($this->security === 'starttls') {
                $this->expect($this->cmd('STARTTLS'), [220], 'STARTTLS');
                $this->crypto();
                $this->greet();
            }
            if ($user !== '') $this->auth($user, $pass);
            $this->expect($this->cmd('MAIL FROM:<' . $from . '>'), [250], 'MAIL FROM');
            foreach ($rcpts as $r) $this->expect($this->cmd('RCPT TO:<' . $r . '>'), [250, 251], 'RCPT TO');
            $this->expect($this->cmd('DATA'), [354], 'DATA');
            $data = preg_replace('/\r\n|\r|\n/', "\r\n", $data) ?? $data;
            $data = preg_replace('/^\./m', '..', $data) ?? $data;
            $this->write(rtrim($data, "\r\n") . "\r\n.\r\n");
            $this->expect($this->read(), [250], 'message');
            try { $this->cmd('QUIT'); } catch (\Throwable $e) { /* the message is already accepted */ }
        } finally {
            if (is_resource($this->fp)) @fclose($this->fp);
            $this->fp = null;
        }
    }

    private function connect(): void
    {
        $remote = ($this->security === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $ctx = stream_context_create(['ssl' => $this->sslOptions()]);
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!is_resource($fp)) {
            throw new \RuntimeException('Cannot connect to ' . $this->host . ':' . $this->port . ' (' . ($errstr !== '' ? $errstr : 'error ' . $errno) . ')');
        }
        stream_set_timeout($fp, $this->timeout);
        $this->fp = $fp;
        $this->expect($this->read(), [220], 'connect');
    }

    private function sslOptions(): array
    {
        return [
            'verify_peer' => $this->verifyCert,
            'verify_peer_name' => $this->verifyCert,
            'allow_self_signed' => !$this->verifyCert,
            'peer_name' => $this->host,
            'SNI_enabled' => true,
        ];
    }

    private function crypto(): void
    {
        foreach ($this->sslOptions() as $k => $v) stream_context_set_option($this->fp, 'ssl', $k, $v);
        $ok = @stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($ok !== true) throw new \RuntimeException('TLS handshake failed' . ($this->verifyCert ? ' (check the certificate, or turn off "Verify the server certificate")' : ''));
    }

    private function greet(): void
    {
        $name = preg_replace('/[^A-Za-z0-9.-]/', '', (string) parse_url(abs_url('/'), PHP_URL_HOST)) ?: 'localhost';
        $r = $this->cmd('EHLO ' . $name);
        if ($r[0] !== 250) $r = $this->cmd('HELO ' . $name);
        $this->expect($r, [250], 'EHLO');
        $this->caps = array_map('strtoupper', array_map('trim', explode("\n", $r[1])));
    }

    private function auth(string $user, string $pass): void
    {
        $has = static fn(array $caps, string $m): bool => (bool) array_filter($caps, static fn($c) => str_starts_with($c, 'AUTH') && str_contains($c, $m));
        if ($has($this->caps, 'PLAIN')) {
            $this->expect($this->cmd('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), true), [235], 'sign-in (username or password refused)');
            return;
        }
        $this->expect($this->cmd('AUTH LOGIN'), [334], 'AUTH LOGIN');
        $this->expect($this->cmd(base64_encode($user), true), [334], 'sign-in (username)');
        $this->expect($this->cmd(base64_encode($pass), true), [235], 'sign-in (username or password refused)');
    }

    /** @return array{0:int,1:string} reply code and text */
    private function cmd(string $line, bool $secret = false): array
    {
        $this->write($line . "\r\n");
        return $this->read();
    }

    private function write(string $s): void
    {
        $len = strlen($s);
        $sent = 0;
        while ($sent < $len) {
            $n = @fwrite($this->fp, substr($s, $sent));
            if ($n === false || $n === 0) throw new \RuntimeException('Connection lost while sending');
            $sent += $n;
        }
    }

    /** @return array{0:int,1:string} */
    private function read(): array
    {
        $text = [];
        $code = 0;
        while (true) {
            $line = @fgets($this->fp, 1024);
            if ($line === false) {
                $meta = is_resource($this->fp) ? stream_get_meta_data($this->fp) : [];
                throw new \RuntimeException(!empty($meta['timed_out']) ? 'The mail server did not answer in time' : 'The mail server closed the connection');
            }
            $line = rtrim($line, "\r\n");
            if (strlen($line) < 3 || !ctype_digit(substr($line, 0, 3))) continue;
            $code = (int) substr($line, 0, 3);
            $text[] = trim(substr($line, 4));
            if (strlen($line) === 3 || $line[3] === ' ') break;   // last line of the reply
        }
        return [$code, implode("\n", $text)];
    }

    /** @param array{0:int,1:string} $r */
    private function expect(array $r, array $codes, string $step): void
    {
        if (!in_array($r[0], $codes, true)) {
            throw new \RuntimeException('Mail server refused at ' . $step . ': ' . $r[0] . ' ' . mb_substr(str_replace("\n", ' ', $r[1]), 0, 200));
        }
    }
}
