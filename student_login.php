<?php
require_once __DIR__ . '/includes/student_auth.php';
sm_auth_require_setup($pdo);
if (sm_auth_current($pdo)) { header('Location: dashboard.php'); exit; }
$errors=[]; $email='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!sm_verify_student_csrf($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid request. Please refresh and retry.'); }
    $email = strtolower(trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : ''));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $now = time();
    if (($now - (int)($_SESSION['sm_auth_window_start'] ?? 0)) > 900) {
        $_SESSION['sm_auth_window_start']=$now;
        $_SESSION['sm_auth_failures']=0;
    }
    if ((int)($_SESSION['sm_auth_failures'] ?? 0) >= 5) {
        $errors[]='Too many failed attempts in this browser. Try again in 15 minutes.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email)>190 || $password==='') {
        $errors[]='Enter a valid email and password.';
    } else {
        try {
            $stmt=$pdo->prepare('SELECT a.id,a.password_hash,p.session_id FROM student_account a JOIN profile p ON p.id=a.profile_id WHERE a.email=? LIMIT 1');
            $stmt->execute([$email]);
            $account=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$account || !password_verify($password, (string)$account['password_hash'])) {
                $_SESSION['sm_auth_failures']=(int)($_SESSION['sm_auth_failures'] ?? 0)+1;
                $errors[]='Invalid email or password.';
            } else {
                if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
                    $pdo->prepare('UPDATE student_account SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT),(int)$account['id']]);
                }
                $pdo->prepare('UPDATE student_account SET last_login_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$account['id']]);
                sm_auth_sign_in($account);
                header('Location: dashboard.php'); exit;
            }
        } catch (Throwable $e) { $errors[]=sm_auth_error_message($e); }
    }
}
require_once __DIR__ . '/includes/v2_layout.php';
sm_v2_head('Student login'); sm_v2_begin('WELCOME BACK','Log in to ScholarMatch','Access your existing academic profile, bookmarks and application tracker.');
?>
<section class="v2-card sm-auth-panel"><h2>Student login</h2>
<?php foreach($errors as $error): ?><div class="v2-alert" role="alert"><?= sm_h($error) ?></div><?php endforeach; ?>
<form method="post" class="v2-stack" autocomplete="on">
<input type="hidden" name="csrf_token" value="<?= sm_h(sm_student_csrf()) ?>">
<label for="email">Email</label><input id="email" name="email" type="email" maxlength="190" required autocomplete="email" value="<?= sm_h($email) ?>">
<label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password">
<button class="v2-btn" type="submit">Log in</button></form>
<p class="v2-muted">New student? <a href="student_register.php">Create an account</a>.</p>
<p class="v2-muted">Admin accounts use the separate <a href="admin/login.php">Admin login</a>.</p>
</section></main><?php sm_v2_footer(); ?>
