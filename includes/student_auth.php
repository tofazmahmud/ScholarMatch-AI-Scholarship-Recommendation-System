<?php
/** Student accounts bridge to the EXISTING profile/session_id design.
 *  No change to existing profile, preference, bookmark, or tracker schema.
 */
require_once __DIR__ . '/student_session.php';
require_once __DIR__ . '/student_helpers.php';

function sm_auth_ready(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT id FROM student_account LIMIT 0');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function sm_auth_require_setup(PDO $pdo): void
{
    if (!sm_auth_ready($pdo)) {
        http_response_code(503);
        exit('Student accounts are not installed yet. Import database/03_STUDENT_AUTH_SAFE_ADDITIVE.sql into your existing ScholarMatch database. This does not modify existing tables.');
    }
}

function sm_auth_current(PDO $pdo): ?array
{
    $id = (int)($_SESSION['sm_student_account_id'] ?? 0);
    if ($id < 1) return null;
    try {
        $stmt = $pdo->prepare('SELECT a.id, a.full_name, a.email, a.profile_id FROM student_account a JOIN profile p ON p.id=a.profile_id WHERE a.id=? AND p.session_id=? LIMIT 1');
        $stmt->execute([$id, (string)$_SESSION['sm_student_token']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    } catch (Throwable $e) {
        error_log('ScholarMatch student account lookup failed: ' . $e->getMessage());
    }
    unset($_SESSION['sm_student_account_id']);
    return null;
}

function sm_auth_sign_in(array $account): void
{
    // Rotate session ID to prevent session fixation; discard drafts from other users.
    session_regenerate_id(true);
    unset($_SESSION['sm_cv_draft'], $_SESSION['sm_cv_last_attempt']);
    $_SESSION['sm_student_account_id'] = (int)$account['id'];
    $_SESSION['sm_student_token'] = (string)$account['session_id'];
    $_SESSION['sm_student_csrf'] = bin2hex(random_bytes(32));
    unset($_SESSION['sm_auth_failures'], $_SESSION['sm_auth_window_start']);
}

function sm_auth_sign_out(): void
{
    session_regenerate_id(true);
    unset($_SESSION['sm_student_account_id'], $_SESSION['sm_cv_draft'], $_SESSION['sm_cv_last_attempt']);
    // New anonymous identity: no access to the signed-out account's profile.
    $_SESSION['sm_student_token'] = bin2hex(random_bytes(32));
    $_SESSION['sm_student_csrf'] = bin2hex(random_bytes(32));
    unset($_SESSION['sm_auth_failures'], $_SESSION['sm_auth_window_start']);
}

function sm_auth_error_message(Throwable $e): string
{
    error_log('ScholarMatch account operation: ' . $e->getMessage());
    return 'Could not complete the request. Please try again.';
}

function sm_auth_nav(PDO $pdo): void
{
    $a = sm_auth_current($pdo);
    if ($a) {
        echo '<span class="sm-auth-user" title="Signed in">' . sm_h($a['full_name']) . '</span>';
        echo '<form action="student_logout.php" method="post" class="sm-auth-nav-form">';
        echo '<input type="hidden" name="csrf_token" value="' . sm_h(sm_student_csrf()) . '">';
        echo '<button type="submit" class="sm-auth-nav-link">Log out</button></form>';
    } else {
        echo '<a href="student_login.php">Log in</a><a href="student_register.php" class="sm-auth-nav-join">Register</a>';
    }
}
