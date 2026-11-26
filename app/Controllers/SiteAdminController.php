<?php
namespace App\Controllers;

use App\Core\{Audit, Auth, DB, Flash, View};
use App\Services\{Backups, Menus, Pages, Texts};

/** Tech portal: editable pages, menus, interface texts and backups (everything visitors see, without touching code). */
final class SiteAdminController
{
    private static function actor(): array { return Auth::needRole('tech_admin'); }

    // ---- pages ---------------------------------------------------------------------------------

    public static function pagesIndex(): void
    {
        $actor = self::actor();
        View::render('tech/pages', ['title' => t('pages.title'), 'actor' => $actor, 'pages' => Pages::all()]);
    }

    public static function pageNew(): void
    {
        $actor = self::actor();
        self::showPage($actor, null, ['slug' => '', 'title_en' => '', 'title_bn' => '', 'body_en' => '', 'body_bn' => '', 'status' => 'published'], []);
    }

    public static function pageCreate(): void
    {
        $actor = self::actor();
        $in = self::pageInput();
        $errors = self::pageCheck($in, 0, false);
        if ($errors) {
            http_response_code(422);
            self::showPage($actor, null, $in, $errors);
            return;
        }
        DB::exec(
            'INSERT INTO pages (slug, title_en, title_bn, body_en, body_bn, status, is_system, updated_by) VALUES (?, ?, ?, ?, ?, ?, 0, ?)',
            [$in['slug'], $in['title_en'], nn($in['title_bn']), nn($in['body_en']), nn($in['body_bn']), $in['status'], (int) $actor['id']]
        );
        $id = DB::lastId();
        Audit::log('page.created', $in['slug']);
        Flash::add('ok', t('pages.created'));
        redirect('/tech/pages/' . $id);
    }

    public static function pageEdit(string $id): void
    {
        $actor = self::actor();
        $page = self::page($id);
        self::showPage($actor, $page, $page, []);
    }

    public static function pageUpdate(string $id): void
    {
        $actor = self::actor();
        $page = self::page($id);
        $in = self::pageInput();
        $system = (int) $page['is_system'] === 1;
        if ($system) $in['slug'] = (string) $page['slug'];          // built-in pages keep their address
        $errors = self::pageCheck($in, (int) $page['id'], $system);
        if ($errors) {
            http_response_code(422);
            self::showPage($actor, $page, $in, $errors);
            return;
        }
        DB::exec(
            'UPDATE pages SET slug = ?, title_en = ?, title_bn = ?, body_en = ?, body_bn = ?, status = ?, updated_by = ? WHERE id = ?',
            [$in['slug'], $in['title_en'], nn($in['title_bn']), nn($in['body_en']), nn($in['body_bn']), $in['status'], (int) $actor['id'], (int) $page['id']]
        );
        Audit::log('page.updated', $in['slug'], $in['status'] !== $page['status'] ? 'status: ' . $page['status'] . ' -> ' . $in['status'] : null);
        Flash::add('ok', t('pages.saved'));
        redirect('/tech/pages/' . (int) $page['id']);
    }

    public static function pageDelete(string $id): void
    {
        self::actor();
        $page = self::page($id);
        if ((int) $page['is_system'] === 1) abort(403);
        DB::exec('DELETE FROM pages WHERE id = ?', [(int) $page['id']]);
        Audit::log('page.deleted', (string) $page['slug']);
        Flash::add('ok', t('pages.deleted'));
        redirect('/tech/pages');
    }

    private static function page(string $id): array
    {
        $p = ctype_digit($id) ? Pages::byId((int) $id) : null;
        if ($p === null) abort(404);
        return $p;
    }

    private static function pageInput(): array
    {
        return [
            'slug' => mb_strtolower(mb_substr(post('slug'), 0, 60)),
            'title_en' => mb_substr(post('title_en'), 0, 190),
            'title_bn' => mb_substr(post('title_bn'), 0, 190),
            'body_en' => mb_substr(str_replace("\r", '', (string) ($_POST['body_en'] ?? '')), 0, 60000),
            'body_bn' => mb_substr(str_replace("\r", '', (string) ($_POST['body_bn'] ?? '')), 0, 60000),
            'status' => post('status') === 'draft' ? 'draft' : 'published',
        ];
    }

    /** @return array<string,string> error messages by field */
    private static function pageCheck(array $in, int $exceptId, bool $system): array
    {
        $e = [];
        if (!$system) {
            if (!Pages::validSlug($in['slug'])) $e['slug'] = t('pages.err_slug');
            elseif (Pages::slugTaken($in['slug'], $exceptId)) $e['slug'] = t('pages.err_slug_taken');
        }
        if (trim($in['title_en']) === '') $e['title_en'] = t('set.err_required');
        return $e;
    }

    private static function showPage(array $actor, ?array $page, array $old, array $errors): void
    {
        View::render('tech/page_edit', [
            'title' => t('pages.title'), 'actor' => $actor, 'page' => $page, 'old' => $old, 'errors' => $errors,
        ]);
    }

    // ---- menus ---------------------------------------------------------------------------------

    public static function menus(): void
    {
        $actor = self::actor();
        $rows = [];
        $n = 1;
        foreach (Menus::LOCATIONS as $loc) {
            foreach (Menus::all($loc) as $r) {
                $rows[$loc][] = ['key' => (string) $r['id'], 'label_en' => $r['label_en'], 'label_bn' => (string) $r['label_bn'],
                                 'url' => $r['url'], 'sort_order' => (int) $r['sort_order'], 'visible' => (int) $r['visible']];
            }
            for ($i = 0; $i < 3; $i++, $n++) {      // spare rows to add new links without any JavaScript
                $rows[$loc][] = ['key' => 'n' . $n, 'label_en' => '', 'label_bn' => '', 'url' => '', 'sort_order' => 900 + $n, 'visible' => 1];
            }
        }
        View::render('tech/menus', ['title' => t('menus.title'), 'actor' => $actor, 'rows' => $rows, 'errors' => []]);
    }

    public static function menusSave(): void
    {
        $actor = self::actor();
        $posted = isset($_POST['m']) && is_array($_POST['m']) ? $_POST['m'] : [];
        $existing = [];
        foreach (Menus::LOCATIONS as $loc) {
            foreach (Menus::all($loc) as $r) $existing[(int) $r['id']] = $loc;
        }

        $rows = ['header' => [], 'footer' => []];
        $errors = [];
        $deletes = [];
        foreach ($posted as $key => $r) {
            $key = (string) $key;
            if (!is_array($r) || preg_match('/^(\d{1,9}|n\d{1,2})$/', $key) !== 1) continue;
            $loc = (string) ($r['location'] ?? '');
            if (!in_array($loc, Menus::LOCATIONS, true)) continue;
            $isNew = $key[0] === 'n';
            if (!$isNew && !isset($existing[(int) $key])) continue;
            $row = [
                'key' => $key,
                'label_en' => mb_substr(trim((string) ($r['label_en'] ?? '')), 0, 80),
                'label_bn' => mb_substr(trim((string) ($r['label_bn'] ?? '')), 0, 80),
                'url' => trim((string) ($r['url'] ?? '')),
                'sort_order' => max(0, min(100000, (int) ($r['sort'] ?? 0))),
                'visible' => (($r['visible'] ?? '') === '1') ? 1 : 0,
            ];
            if ($isNew && $row['label_en'] === '' && $row['url'] === '') { $rows[$loc][] = $row; continue; }    // untouched spare row
            if (!$isNew && (($r['delete'] ?? '') === '1')) { $deletes[] = (int) $key; continue; }
            if ($row['label_en'] === '') $errors[$key]['label_en'] = t('set.err_required');
            if (!Menus::validUrl($row['url'])) $errors[$key]['url'] = t('set.err_link');
            $rows[$loc][] = $row;
        }
        foreach (Menus::LOCATIONS as $loc) {
            $used = count(array_filter($rows[$loc], fn($r) => $r['label_en'] !== '' || $r['url'] !== ''));
            if ($used > Menus::MAX_PER_LOCATION) $errors['_' . $loc] = t('menus.err_max', ['max' => Menus::MAX_PER_LOCATION]);
        }
        if ($errors) {
            http_response_code(422);
            View::render('tech/menus', ['title' => t('menus.title'), 'actor' => $actor, 'rows' => $rows, 'errors' => $errors]);
            return;
        }

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($deletes as $id) DB::exec('DELETE FROM menu_items WHERE id = ?', [$id]);
            foreach (Menus::LOCATIONS as $loc) {
                foreach ($rows[$loc] as $r) {
                    if ($r['key'][0] === 'n') {
                        if ($r['label_en'] === '' && $r['url'] === '') continue;
                        DB::exec('INSERT INTO menu_items (location, label_en, label_bn, url, sort_order, visible) VALUES (?, ?, ?, ?, ?, ?)',
                            [$loc, $r['label_en'], nn($r['label_bn']), $r['url'], $r['sort_order'], $r['visible']]);
                    } else {
                        DB::exec('UPDATE menu_items SET label_en = ?, label_bn = ?, url = ?, sort_order = ?, visible = ? WHERE id = ?',
                            [$r['label_en'], nn($r['label_bn']), $r['url'], $r['sort_order'], $r['visible'], (int) $r['key']]);
                    }
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        Audit::log('menus.updated', null, count($deletes) . ' removed');
        Flash::add('ok', t('menus.saved'));
        redirect('/tech/menus');
    }

    // ---- interface texts -----------------------------------------------------------------------

    public static function textsIndex(): void
    {
        self::actor();
        redirect('/tech/texts/home');
    }

    public static function texts(string $group): void
    {
        $actor = self::actor();
        if (!isset(Texts::GROUPS[$group])) abort(404);
        self::showTexts($actor, $group, Texts::overrides(), []);
    }

    public static function textsSave(string $group): void
    {
        $actor = self::actor();
        if (!isset(Texts::GROUPS[$group])) abort(404);
        [$n, $errors] = Texts::save($group, $_POST);
        if ($errors) {
            http_response_code(422);
            $posted = isset($_POST['t']) && is_array($_POST['t']) ? $_POST['t'] : [];
            self::showTexts($actor, $group, ['en' => (array) ($posted['en'] ?? []), 'bn' => (array) ($posted['bn'] ?? [])], $errors);
            return;
        }
        if ($n > 0) Audit::log('texts.updated', $group, $n . ' changed');
        Flash::add('ok', $n > 0 ? t('set.saved') : t('set.nochange'));
        redirect('/tech/texts/' . $group);
    }

    private static function showTexts(array $actor, string $group, array $values, array $errors): void
    {
        View::render('tech/texts', [
            'title' => t('texts.title'), 'actor' => $actor, 'group' => $group, 'keys' => Texts::keys($group),
            'values' => $values, 'errors' => $errors,
            'defaults' => ['en' => \App\Core\I18n::defaults('en'), 'bn' => \App\Core\I18n::defaults('bn')],
        ]);
    }

    // ---- backups -------------------------------------------------------------------------------

    public static function backups(): void
    {
        $actor = self::actor();
        View::render('tech/backups', [
            'title' => t('backups.title'), 'actor' => $actor, 'list' => Backups::list(),
            'auto' => Backups::auto(), 'every' => Backups::everyHours(), 'keep' => Backups::keep(),
            'dir' => \App\Core\Paths::data(),
        ]);
    }

    public static function backupCreate(): void
    {
        self::actor();
        try {
            $name = Backups::create();
            $removed = Backups::prune(Backups::keep());
            Audit::log('backup.created', $name, $removed ? $removed . ' old removed' : null);
            Flash::add('ok', t('backups.created', ['name' => $name]));
        } catch (\Throwable $e) {
            error_log((string) $e);
            Flash::add('error', t('backups.err') . ' ' . $e->getMessage());
        }
        redirect('/tech/backups');
    }

    public static function backupDownload(string $name): void
    {
        self::actor();
        $path = Backups::path($name);
        if ($path === null) abort(404);
        Audit::log('backup.downloaded', $name);
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }

    public static function backupDelete(string $name): void
    {
        self::actor();
        if (Backups::delete($name)) {
            Audit::log('backup.deleted', $name);
            Flash::add('ok', t('backups.deleted'));
        }
        redirect('/tech/backups');
    }
}
