<?php
declare(strict_types=1);
/**
 * Emergency removal of a person's two-step sign-in (for example the only tech admin lost their phone and backup codes):
 *   docker exec -it university-journal php /var/www/app/bin/reset-2fa.php <university-id or login-number>
 * Also clears the "too many tries" lock for that person. They can set up a new second step after signing in.
 * If the whole feature is the problem, switch it off in Tech portal > Settings > Two-step sign-in instead.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

use App\Core\{Audit, DB, Identity};
use App\Services\TwoFactor;

$uid = $argv[1] ?? '';
if ($uid === '') { fwrite(STDERR, "Usage: php reset-2fa.php <university-id or login-number>\n"); exit(1); }

$byUid = fn() => DB::one('SELECT id, uid, serial_no FROM users WHERE uid = ?', [$uid]);
$bySerial = fn() => ctype_digit($uid) ? DB::one('SELECT id, uid, serial_no FROM users WHERE serial_no = ?', [(int) $uid]) : null;
$u = Identity::sequential() ? ($bySerial() ?? $byUid()) : ($byUid() ?? $bySerial());
if (!$u) { fwrite(STDERR, "No user with university ID or login number '$uid'.\n"); exit(1); }

$id = (int) $u['id'];
TwoFactor::resetAll($id);
DB::exec('DELETE FROM login_attempts WHERE ident IN (?, ?)', ['~2fa:' . $id, '~pw:' . $id]);
Audit::log('2fa.reset.cli', Identity::tag($u), null, $id);
echo "Two-step sign-in removed for '" . Identity::loginId($u) . "'.\n";
