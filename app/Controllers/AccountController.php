<?php
namespace App\Controllers;

use App\Core\{Audit, Auth, DB, Flash, Password, View};
use App\Services\{Avatar, Profile};

final class AccountController
{
    public static function dashboard(): void
    {
        $u = Auth::need();
        if (Auth::isAdmin($u)) redirect(Auth::homeFor($u['role']));

        $p = DB::one('SELECT * FROM user_profiles WHERE user_id = ?', [(int) $u['id']]) ?: [];
        $missing = Profile::missing($p, $u['role']);
        if (((int) ($p['profile_complete'] ?? 0) === 1) !== ($missing === [])) Profile::recalc((int) $u['id']);

        View::render('account/dashboard', ['title' => t('dash.title'), 'u' => $u, 'p' => $p, 'missing' => $missing]);
    }

    // ---- password ------------------------------------------------------------------------------

    public static function passwordForm(): void
    {
        $u = Auth::need();
        self::showPassword($u, null);
    }

    public static function passwordSave(): void
    {
        $u = Auth::need();
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        $conf = (string) ($_POST['confirm'] ?? '');

        $hash = DB::val('SELECT password_hash FROM users WHERE id = ?', [(int) $u['id']]);
        $err = null;
        if (!is_string($hash) || $cur === '' || !password_verify($cur, $hash)) {
            $err = t('pw.err_current');
        } elseif ($new !== $conf) {
            $err = t('pw.err_mismatch');
        } elseif ($new === $cur) {
            $err = t('pw.err_same_old');
        } elseif (($k = Password::validate($new, $u['uid'])) !== null) {
            $err = t('pw.' . $k, ['n' => Password::minLength()]);
        }
        if ($err !== null) {
            http_response_code(422);
            self::showPassword($u, $err);
            return;
        }

        // Every other session of this user is signed out; this one continues with the new epoch.
        $_SESSION['epoch'] = Auth::setPassword((int) $u['id'], $new, false);
        session_regenerate_id(true);
        Audit::log('password.changed', $u['uid'], null, (int) $u['id']);
        Flash::add('ok', t('pw.changed'));
        redirect(Auth::homeFor($u['role']));
    }

    private static function showPassword(array $u, ?string $error): void
    {
        View::render('account/password', [
            'title' => t('pw.change_title'),
            'forced' => (int) $u['must_change_password'] === 1,
            'error' => $error,
            'min' => Password::minLength(),
        ]);
    }

    // ---- profile -------------------------------------------------------------------------------

    public static function profileForm(): void
    {
        $u = Auth::need();
        $p = DB::one('SELECT * FROM user_profiles WHERE user_id = ?', [(int) $u['id']]) ?: [];
        $old = [
            'full_name' => (string) ($p['full_name'] ?? ''),
            'full_name_bn' => (string) ($p['full_name_bn'] ?? ''),
            'department_id' => (string) ($p['department_id'] ?? ''),
            'academic_session' => (string) ($p['academic_session'] ?? ''),
            'designation' => (string) ($p['designation'] ?? ''),
            'bio' => (string) ($p['bio'] ?? ''),
            'interests' => (string) ($p['interests'] ?? ''),
            'links' => implode("\n", self::decodeLinks($p['links'] ?? null)),
            'email' => (string) ($u['email'] ?? ''),
        ];
        self::showProfile($u, $old, (string) ($p['photo_path'] ?? ''), []);
    }

    public static function profileSave(): void
    {
        $u = Auth::need();
        $id = (int) $u['id'];
        $role = $u['role'];
        $p = DB::one('SELECT * FROM user_profiles WHERE user_id = ?', [$id]) ?: [];
        $oldPhoto = (string) ($p['photo_path'] ?? '');

        $in = [
            'full_name' => mb_substr(post('full_name'), 0, 190),
            'full_name_bn' => mb_substr(post('full_name_bn'), 0, 190),
            'department_id' => post('department_id'),
            'academic_session' => mb_substr(post('academic_session'), 0, 40),
            'designation' => mb_substr(post('designation'), 0, 120),
            'bio' => mb_substr(str_replace("\r", '', post('bio')), 0, 2000),
            'interests' => mb_substr(post('interests'), 0, 500),
            'links' => post('links'),
            'email' => mb_substr(post('email'), 0, 190),
        ];
        // Students have a session, everyone else a designation; keep the other value untouched.
        if ($role === 'student') $in['designation'] = (string) ($p['designation'] ?? '');
        else $in['academic_session'] = (string) ($p['academic_session'] ?? '');

        $errors = [];
        if ($in['email'] !== '' && !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = t('profile.err_email');

        $dept = null;
        if ($in['department_id'] !== '') {
            $dept = ctype_digit($in['department_id']) ? DB::val('SELECT id FROM departments WHERE id = ?', [(int) $in['department_id']]) : null;
            if ($dept === null) $errors['department_id'] = t('profile.err_dept');
        }

        $links = [];
        foreach (preg_split('/\R/u', $in['links']) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $scheme = strtolower((string) parse_url($line, PHP_URL_SCHEME));
            if (strlen($line) > 255 || !in_array($scheme, ['http', 'https'], true) || filter_var($line, FILTER_VALIDATE_URL) === false) {
                $errors['links'] = t('profile.err_links');
                break;
            }
            $links[] = $line;
        }
        if (count($links) > 5) $errors['links'] = t('profile.err_links');

        // Photo: only touched when the form validated, so a failed save leaves no stray files.
        $photo = $oldPhoto;
        $newPhoto = null;
        if (!$errors) {
            $file = $_FILES['photo'] ?? null;
            if (post('remove_photo') === '1') {
                $photo = '';
            }
            if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    $newPhoto = Avatar::store($file, $id);
                    $photo = $newPhoto;
                } catch (\RuntimeException $e) {
                    $errors['photo'] = t('profile.' . $e->getMessage(), ['mb' => max(1, (int) setting('upload_max_image_mb', '5'))]);
                }
            }
        }

        if ($errors) {
            http_response_code(422);
            self::showProfile($u, $in, $oldPhoto, $errors);
            return;
        }

        DB::exec(
            'INSERT INTO user_profiles (user_id, full_name, full_name_bn, department_id, academic_session, designation, bio, interests, links, photo_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), full_name_bn = VALUES(full_name_bn), department_id = VALUES(department_id),
                academic_session = VALUES(academic_session), designation = VALUES(designation), bio = VALUES(bio),
                interests = VALUES(interests), links = VALUES(links), photo_path = VALUES(photo_path)',
            [
                $id, nn($in['full_name']), nn($in['full_name_bn']), $dept === null ? null : (int) $dept,
                nn($in['academic_session']), nn($in['designation']), nn($in['bio']), nn($in['interests']),
                $links ? json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                nn($photo),
            ]
        );
        DB::exec('UPDATE users SET email = ? WHERE id = ?', [nn($in['email']), $id]);
        if ($oldPhoto !== '' && $oldPhoto !== $photo) Avatar::delete($oldPhoto);

        Profile::recalc($id);
        Audit::log('profile.updated', $u['uid'], null, $id);
        Flash::add('ok', t('profile.saved'));
        redirect('/profile');
    }

    private static function showProfile(array $u, array $old, string $photo, array $errors): void
    {
        View::render('account/profile', [
            'title' => t('profile.title'),
            'u' => $u,
            'old' => $old,
            'photo' => $photo,
            'errors' => $errors,
            'departments' => Profile::departments(),
            'required' => Profile::required($u['role']),
            'maxMb' => max(1, (int) setting('upload_max_image_mb', '5')),
        ]);
    }

    private static function decodeLinks(?string $json): array
    {
        $a = $json ? json_decode($json, true) : [];
        return is_array($a) ? array_values(array_filter($a, 'is_string')) : [];
    }
}
