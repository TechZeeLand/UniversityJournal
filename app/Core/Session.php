<?php
declare(strict_types=1);
namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_name('ujsess');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_save_path(Paths::ensure(Paths::runtime('sessions')));
        // PHP deletes session files after 24 minutes by default, which would sign people out early.
        ini_set('session.gc_maxlifetime', '28800');
        session_start();
    }
}
