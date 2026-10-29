
<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (!valid_csrf($token)) {
        $error = 'Invalid request. Refresh the page.';
    } elseif (
        $_SESSION['login_attempts'] >= 5 &&
        time() - ($_SESSION['last_attempt'] ?? 0) < 900
    ) {
        $error = 'Too many attempts. Try again after 15 minutes.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {

        // Reset expired login-attempt counter
        if (time() - ($_SESSION['last_attempt'] ?? 0) >= 900) {
            $_SESSION['login_attempts'] = 0;
        }

        $stmt = $pdo->prepare(
            "SELECT id, username, password_hash
             FROM admin
             WHERE username = ?
             LIMIT 1"
        );

        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if (
            $admin &&
            password_verify($password, $admin['password_hash'])
        ) {
            // Must be enabled in V3 admin_access (no bypass for disabled accounts).
            try { $access = admin_access($pdo, (int)$admin['id']); }
            catch (Throwable $e) { $access = null; }
            if (!$access || !(int)$access['is_active']) {
                $error = 'Account unavailable or Admin V3 migration not installed.';
            } else {
            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $access['role'];
            $_SESSION['login_attempts'] = 0;
            $pdo->prepare('UPDATE admin_access SET last_login_at=NOW() WHERE admin_id=?')->execute([(int)$admin['id']]);
            admin_log($pdo, 'auth.login','admin',(int)$admin['id']);
            header('Location: dashboard.php');
            exit;
            }
        }

        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt'] = time();

        $error = 'Invalid username or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - ScholarMatch</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/v2.css">
</head>

<body class="login-body">

    <div class="login-container">

        <div class="login-logo">🎓</div>

        <h1>ScholarMatch</h1>
        <p class="subtitle">Admin Login</p>

        <?php if ($error !== ''): ?>
            <div class="error-message">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
            >

            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                placeholder="Enter username"
                autocomplete="username"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter password"
                autocomplete="current-password"
                required
            >

            <button type="submit" class="btn-primary">
                Login
            </button>

        </form>

        <p class="footer-text">
            ScholarMatch Administration
        </p>

    </div>

</body>
</html>
