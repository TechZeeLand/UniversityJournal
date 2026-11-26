<?php
declare(strict_types=1);
// php bin/backup.php         dump now
// php bin/backup.php auto    dump only if automatic backups are on and one is due (used by docker/backup-loop.sh)
require __DIR__ . '/../app/bootstrap.php';

use App\Services\Backups;

$auto = ($argv[1] ?? '') === 'auto';
if ($auto && (!Backups::auto() || !Backups::due())) exit(0);

try {
    $name = Backups::create();
    $removed = Backups::prune(Backups::keep());
    echo "[backup] created $name" . ($removed ? " (removed $removed old)" : '') . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[backup] ' . $e->getMessage() . "\n");
    exit(1);
}
