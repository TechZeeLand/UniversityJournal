<?php
namespace App\Controllers;

use App\Core\{Audit, Auth, DB, Flash, Markdown, View};
use App\Services\{ArticleImages, Articles, Profile};

/** Students and teachers: write, save drafts, submit for review. */
final class WriterController
{
    private static function writer(): array
    {
        $u = Auth::need();
        if (!Articles::canWrite($u)) abort(403);
        return $u;
    }

    private static function mine(array $u, string $id): array
    {
        $a = ctype_digit($id) ? Articles::find((int) $id) : null;
        if ($a === null || (int) $a['author_id'] !== (int) $u['id']) abort(404);
        return $a;
    }

    public static function index(): void
    {
        $u = self::writer();
        View::render('writer/index', ['title' => t('write.my'), 'rows' => Articles::byAuthor((int) $u['id']), 'complete' => (int) ($u['profile_complete'] ?? 0) === 1]);
    }

    public static function newForm(): void
    {
        $u = self::writer();
        $dept = DB::val('SELECT department_id FROM user_profiles WHERE user_id = ?', [(int) $u['id']]);
        $a = ['id' => 0, 'title' => '', 'summary' => '', 'body' => '', 'lang' => (\App\Core\I18n::locale() === 'bn' ? 'bn' : 'en'), 'type_id' => null, 'department_id' => $dept === null ? null : (int) $dept, 'cover_path' => null, 'status' => 'draft', 'review_note' => null];
        self::form($a, []);
    }

    public static function editForm(string $id): void
    {
        $u = self::writer();
        $a = self::mine($u, $id);
        if (!in_array($a['status'], ['draft', 'changes_requested', 'rejected'], true)) {
            Flash::add('info', t('write.locked'));
            redirect(Articles::url($a));
        }
        self::form($a, []);
    }

    private static function form(array $a, array $errors, int $code = 200): void
    {
        http_response_code($code);
        View::render('writer/edit', [
            'title' => $a['id'] ? t('write.edit') : t('write.new'), 'a' => $a, 'errors' => $errors,
            'types' => Articles::types(), 'departments' => Profile::departments(),
        ]);
    }

    /** Create (id = '') or update an article from the form. */
    public static function save(string $id = ''): void
    {
        $u = self::writer();
        $existing = $id === '' ? null : self::mine($u, $id);
        if ($existing && !in_array($existing['status'], ['draft', 'changes_requested', 'rejected'], true)) abort(403);

        $submit = post('action') === 'submit';
        $d = [
            'title' => mb_substr(post('title'), 0, 220),
            'summary' => mb_substr(post('summary'), 0, 500),
            'body' => str_replace("\r", '', (string) ($_POST['body'] ?? '')),
            'lang' => in_array(post('lang'), Articles::LANGS, true) ? post('lang') : 'en',
            'type_id' => ctype_digit(post('type_id')) && post('type_id') !== '0' ? (int) post('type_id') : null,
            'department_id' => ctype_digit(post('department_id')) && post('department_id') !== '0' ? (int) post('department_id') : null,
            'cover_path' => $existing['cover_path'] ?? null,
        ];
        if (mb_strlen($d['body']) > 200000) $d['body'] = mb_substr($d['body'], 0, 200000);

        $errors = [];
        if ($d['title'] === '') $errors['title'] = t('write.err_title');
        if ($d['type_id'] !== null && DB::val('SELECT 1 FROM article_types WHERE id = ? AND active = 1', [$d['type_id']]) === null) $d['type_id'] = null;
        if ($d['department_id'] !== null && DB::val('SELECT 1 FROM departments WHERE id = ?', [$d['department_id']]) === null) $d['department_id'] = null;
        if ($submit) {
            if (mb_strlen(trim($d['body'])) < 100) $errors['body'] = t('write.err_body');
            if ($d['type_id'] === null) $errors['type_id'] = t('write.err_type');
            if ((int) ($u['profile_complete'] ?? 0) !== 1) $errors['profile'] = t('write.err_profile');
        }

        // cover picture
        $newCover = null;
        if (isset($_FILES['cover']) && (int) ($_FILES['cover']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
            try { $newCover = ArticleImages::cover($_FILES['cover'], (int) $u['id']); }
            catch (\RuntimeException $e) { $errors['cover'] = t('profile.' . $e->getMessage(), ['mb' => max(1, (int) setting('upload_max_image_mb', '5'))]); }
        }
        if ($errors) {
            if ($newCover) ArticleImages::delete($newCover);
            $a = array_merge($existing ?? ['id' => 0, 'status' => 'draft', 'review_note' => null], $d);
            self::form($a, $errors, 422);
            return;
        }
        $old = $d['cover_path'];
        if ($newCover) { $d['cover_path'] = $newCover; }
        elseif (post('remove_cover') === '1') { $d['cover_path'] = null; }
        if ($old && $old !== $d['cover_path']) ArticleImages::delete($old);

        if ($existing === null) $aid = Articles::create((int) $u['id'], $d);
        else { Articles::update((int) $existing['id'], $d); $aid = (int) $existing['id']; }

        if ($submit) {
            $st = Articles::submit($aid);
            Audit::log('article.submit', (string) $aid, $st, (int) $u['id']);
            if ($st === 'submitted') { $full = Articles::find($aid); if ($full !== null) \App\Services\Notify::articleSubmitted($full); }
            Flash::add('ok', t($st === 'published' ? 'write.published' : 'write.submitted'));
            redirect('/write');
        }
        Flash::add('ok', t('write.saved'));
        redirect('/write/' . $aid);
    }

    /**
     * Automatic draft saving (JSON, called by the editor every few seconds while the person types).
     * Saves only the text fields; the cover picture and the submit button keep using the normal form.
     * A brand-new article becomes a draft on the first call and the editor switches to its address.
     */
    public static function autosave(): void
    {
        $u = self::writer();
        header('Content-Type: application/json; charset=utf-8');
        $id = post('id');
        $existing = ($id !== '' && $id !== '0') ? self::mine($u, $id) : null;
        if ($existing && !in_array($existing['status'], ['draft', 'changes_requested', 'rejected'], true)) {
            http_response_code(409);
            echo json_encode(['ok' => false]);
            return;
        }
        $title = mb_substr(post('title'), 0, 220);
        $body = mb_substr(str_replace("\r", '', (string) ($_POST['body'] ?? '')), 0, 200000);
        if ($existing === null && $title === '' && trim($body) === '') { echo json_encode(['ok' => true, 'id' => 0, 'skipped' => true]); return; }
        if ($title === '') $title = $existing !== null ? (string) $existing['title'] : t('write.untitled');
        $typeId = ctype_digit(post('type_id')) && post('type_id') !== '0' ? (int) post('type_id') : null;
        $deptId = ctype_digit(post('department_id')) && post('department_id') !== '0' ? (int) post('department_id') : null;
        if ($typeId !== null && DB::val('SELECT 1 FROM article_types WHERE id = ? AND active = 1', [$typeId]) === null) $typeId = null;
        if ($deptId !== null && DB::val('SELECT 1 FROM departments WHERE id = ?', [$deptId]) === null) $deptId = null;
        $d = [
            'title' => $title, 'summary' => mb_substr(post('summary'), 0, 500), 'body' => $body,
            'lang' => in_array(post('lang'), Articles::LANGS, true) ? post('lang') : 'en',
            'type_id' => $typeId, 'department_id' => $deptId, 'cover_path' => $existing['cover_path'] ?? null,
        ];
        if ($existing === null) $aid = Articles::create((int) $u['id'], $d);
        else { Articles::update((int) $existing['id'], $d); $aid = (int) $existing['id']; }
        echo json_encode(['ok' => true, 'id' => $aid, 'at' => date('H:i')]);
    }

    public static function withdraw(string $id): void
    {
        $u = self::writer();
        $a = self::mine($u, $id);
        if ($a['status'] === 'submitted') {
            DB::exec("UPDATE articles SET status = 'draft' WHERE id = ?", [(int) $a['id']]);
            Flash::add('ok', t('write.withdrawn'));
        }
        redirect('/write');
    }

    public static function delete(string $id): void
    {
        $u = self::writer();
        $a = self::mine($u, $id);
        if (!in_array($a['status'], ['draft', 'changes_requested', 'rejected'], true)) abort(403);
        ArticleImages::delete($a['cover_path']);
        Articles::delete((int) $a['id']);
        Audit::log('article.delete', (string) $a['id'], null, (int) $u['id']);
        Flash::add('ok', t('write.deleted'));
        redirect('/write');
    }

    /** Live preview of the Markdown text (JSON, called by the editor). */
    public static function preview(): void
    {
        self::writer();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['html' => Markdown::render(mb_substr((string) ($_POST['body'] ?? ''), 0, 200000))], JSON_UNESCAPED_UNICODE);
    }

    /** A picture inserted inside the text. */
    public static function image(): void
    {
        $u = self::writer();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $rel = ArticleImages::inline($_FILES['image'] ?? [], (int) $u['id']);
            echo json_encode(['ok' => true, 'url' => '/uploads/' . $rel]);
        } catch (\RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => t('profile.' . $e->getMessage(), ['mb' => max(1, (int) setting('upload_max_image_mb', '5'))])]);
        }
    }
}
