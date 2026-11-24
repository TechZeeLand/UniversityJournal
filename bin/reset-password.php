<?php
declare(strict_types=1);
/**
 * Emergency password reset from the command line (for example when the only tech admin is locked out):
 *   docker exec -it university-journal php /var/www/app/bin/reset-password.php <university-id>
 * Prints a temporary password; the user must change it at the next login. All their sessions are ended.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';

use App\Core\{Audit, Auth, DB, Password};

$uid = $argv[1] ?? '';
if ($uid === '') { fwrite(STDERR, "Usage: php reset-password.php <university-id>\n"); exit(1); }

$u = DB::one('SELECT id, uid FROM users WHERE uid = ?', [$uid]);
if (!$u) { fwrite(STDERR, "No user with ID '$uid'.\n"); exit(1); }

$pw = Password::generate();
Auth::setPassword((int) $u['id'], $pw, true);
DB::exec('DELETE FROM login_attempts WHERE ident = ?', [mb_strtolower($uid)]);
Audit::log('password.reset.cli', $u['uid'], null, (int) $u['id']);
echo "Temporary password for '{$u['uid']}': $pw\n";
