<?php
declare(strict_types=1);
namespace App\Core;

/** Writes security-relevant events to audit_logs. Never throws: auditing must not break a request. */
final class Audit
{
    public static function log(string $action, ?string $target = null, ?string $detail = null, ?int $userId = null): void
    {
        try {
            $uid = $userId ?? (Auth::user()['id'] ?? null);
            DB::exec(
                'INSERT INTO audit_logs (user_id, action, target, detail, ip) VALUES (?, ?, ?, ?, ?)',
                [$uid, mb_substr($action, 0, 80), $target === null ? null : mb_substr($target, 0, 190), $detail, Request::ip()]
            );
        } catch (\Throwable $e) {
            error_log('audit failed: ' . $e->getMessage());
        }
    }
}
