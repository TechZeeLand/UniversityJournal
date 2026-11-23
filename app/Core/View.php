<?php
declare(strict_types=1);
namespace App\Core;

final class View
{
    /** Render views/<name>.php inside views/layout.php. */
    public static function render(string $name, array $data = [], bool $layout = true): void
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require APP_PATH . '/views/' . $name . '.php';
        $content = ob_get_clean();
        if ($layout) {
            $pageTitle = $data['title'] ?? null;
            require APP_PATH . '/views/layout.php';
        } else {
            echo $content;
        }
    }
}
