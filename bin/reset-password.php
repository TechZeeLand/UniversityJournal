<?php
declare(strict_types=1);
/**
 * Emergency password reset from the command line (for example when the only tech admin is locked out):
 *   docker exec -it university-journal php /var/www/app/bin/reset-password.php <university-id or login-number>
 * Prints a temporary password; the user must change it at the next login. All their sessions are ended.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

use App\Core\{Audit, Auth, DB, Identity, Password};

$uid = $argv[1] ?? '';
if ($uid === '') { fwrite(STDERR, "Usage: php reset-password.php <university-id or login-number>\n"); exit(1); }

$byUid = fn() => DB::one('SELECT id, uid, serial_no FROM users WHERE uid = ?', [$uid]);
$bySerial = fn() => ctype_digit($uid) ? DB::one('SELECT id, uid, serial_no FROM users WHERE serial_no = ?', [(int) $uid]) : null;
// Try the ID system that is currently in use first, then the other one.
$u = Identity::sequential() ? ($bySerial() ?? $byUid()) : ($byUid() ?? $bySerial());
if (!$u) { fwrite(STDERR, "No user with university ID or login number '$uid'.\n"); exit(1); }

$pw = Password::generate();
Auth::setPassword((int) $u['id'], $pw, true);
DB::exec('DELETE FROM login_attempts WHERE ident = ?', [mb_strtolower($uid)]);
DB::exec('DELETE FROM login_attempts WHERE ident = ?', [Identity::loginId($u)]);
$who = Identity::loginId($u);
Audit::log('password.reset.cli', Identity::tag($u), null, (int) $u['id']);
echo "Temporary password for '$who': $pw\n";
