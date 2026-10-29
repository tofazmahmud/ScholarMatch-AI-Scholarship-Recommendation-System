
<?php
require_once __DIR__ . '/../includes/admin_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!valid_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid request.');
}

require_once __DIR__.'/../config/db.php';
admin_log($pdo,'auth.logout','admin',(int)($_SESSION['admin_id']??0));
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite']
    ]);
}

session_destroy();

header('Location: login.php');
exit;
