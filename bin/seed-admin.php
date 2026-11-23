<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use App\Core\{DB, Env};

$uid = Env::get('INITIAL_ADMIN_UID');
$pass = Env::get('INITIAL_ADMIN_PASSWORD');
if (!$uid || !$pass) { echo "[seed] no INITIAL_ADMIN_* set, skipping\n"; return; }

if (DB::val("SELECT COUNT(*) FROM users WHERE role = 'tech_admin'") > 0) {
    echo "[seed] a tech admin already exists, skipping\n";
    return;
}
DB::exec(
    "INSERT INTO users (uid, password_hash, role, status, must_change_password) VALUES (?, ?, 'tech_admin', 'active', 1)",
    [$uid, password_hash($pass, PASSWORD_DEFAULT)]
);
DB::exec('INSERT INTO user_profiles (user_id, full_name) VALUES (?, ?)', [DB::lastId(), 'Tech Admin']);
echo "[seed] tech admin '$uid' created (must change password at first login)\n";
