<?php
require_once __DIR__ . '/includes/student_auth.php';
sm_auth_require_setup($pdo);
if (sm_auth_current($pdo)) { header('Location: dashboard.php'); exit; }
$errors = [];
$name = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sm_verify_student_csrf($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid request. Please refresh and retry.'); }
    $name = trim(is_string($_POST['full_name'] ?? null) ? $_POST['full_name'] : '');
    $email = strtolower(trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : ''));
    $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    if ($name === '' || strlen($name) > 120 || preg_match('/[\x00-\x1F\x7F]/', $name)) $errors[] = 'Enter a name of at most 120 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $errors[] = 'Enter a valid email address.';
    if (strlen($pass) < 10 || strlen($pass) > 72) $errors[] = 'Password must contain 10 to 72 characters.';
    if ($pass !== $confirm) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $token = (string)$_SESSION['sm_student_token'];
            // Reuse any current anonymous profile and its bookmarks/other data.
            $stmt = $pdo->prepare('SELECT id FROM profile WHERE session_id=? LIMIT 1 FOR UPDATE');
            $stmt->execute([$token]);
            $pid = (int)($stmt->fetchColumn() ?: 0);
            if ($pid === 0) {
                $stmt = $pdo->prepare('INSERT INTO profile (session_id) VALUES (?)');
                $stmt->execute([$token]);
                $pid = (int)$pdo->lastInsertId();
            }
            $stmt = $pdo->prepare('INSERT INTO student_account (profile_id, full_name, email, password_hash) VALUES (?,?,?,?)');
            $stmt->execute([$pid, $name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $accountId = (int)$pdo->lastInsertId();
            $pdo->commit();
            sm_auth_sign_in(['id'=>$accountId,'session_id'=>$token]);
            header('Location: dashboard.php'); exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() === '23000') $errors[] = 'This email or profile is already linked to an account. Please log in instead.';
            else $errors[] = sm_auth_error_message($e);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = sm_auth_error_message($e);
        }
    }
}
require_once __DIR__ . '/includes/v2_layout.php';
sm_v2_head('Create student account');
sm_v2_begin('STUDENT ACCOUNT','Create your ScholarMatch account','Keep your profile, saved scholarships and application progress across visits.');
?>
<section class="v2-card sm-auth-panel">
<h2>Register</h2>
<?php foreach($errors as $error): ?><div class="v2-alert" role="alert"><?= sm_h($error) ?></div><?php endforeach; ?>
<form method="post" class="v2-stack" autocomplete="on">
<input type="hidden" name="csrf_token" value="<?= sm_h(sm_student_csrf()) ?>">
<label for="full_name">Full name</label><input id="full_name" name="full_name" maxlength="120" required autocomplete="name" value="<?= sm_h($name) ?>">
<label for="email">Email</label><input id="email" name="email" type="email" maxlength="190" required autocomplete="email" value="<?= sm_h($email) ?>">
<label for="password">Password (10–72 characters)</label><input id="password" name="password" type="password" minlength="10" maxlength="72" required autocomplete="new-password">
<label for="confirm_password">Confirm password</label><input id="confirm_password" name="confirm_password" type="password" minlength="10" maxlength="72" required autocomplete="new-password">
<button class="v2-btn" type="submit">Create account</button>
</form>
<p class="v2-muted">Already registered? <a href="student_login.php">Log in</a>.</p>
<p class="v2-muted">If you already created an anonymous profile in this browser, registering now will link that existing profile to your new account.</p>
</section></main><?php sm_v2_footer(); ?>
