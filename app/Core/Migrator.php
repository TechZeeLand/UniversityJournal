<?php
declare(strict_types=1);
namespace App\Core;

use PDO;

/** Runs migrations/*.sql once each, in filename order. Each file may contain many statements. */
final class Migrator
{
    public static function run(string $dir): void
    {
        $pdo = new PDO(
            'mysql:unix_socket=' . Env::get('DB_SOCKET', '/run/mysqld/mysqld.sock') . ';dbname=' . Env::get('DB_NAME', 'university_journal') . ';charset=utf8mb4',
            Env::get('DB_USER', 'journal'),
            Env::get('DB_PASSWORD', ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true]
        );
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            name VARCHAR(190) PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $done = $pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob(rtrim($dir, '/') . '/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            echo "[migrate] applying $name\n";
            $pdo->exec((string) file_get_contents($file));
            $pdo->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$name]);
        }
        echo "[migrate] up to date\n";
    }
}
