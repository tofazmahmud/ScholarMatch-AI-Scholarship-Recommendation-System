<?php
// ScholarMatch Stable Admin V3 — include from admin_auth.php once via require_once.
// This file intentionally has no dependence on other V3 helper files.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
}
function adm_h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function csrf_token(): string {
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) ||
        !preg_match('/^[a-f0-9]{64}$/D', $_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function valid_csrf(string $token): bool {
    return $token !== '' && hash_equals(csrf_token(), $token);
}
function admin_access(PDO $pdo, int $adminId): ?array {
    $s = $pdo->prepare('SELECT a.id,a.username,x.role,x.is_active,x.display_name,x.email,x.last_login_at
        FROM admin a INNER JOIN admin_access x ON x.admin_id=a.id WHERE a.id=? LIMIT 1');
    $s->execute([$adminId]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
function require_admin(): void {
    if (empty($_SESSION['admin_id']) || !filter_var($_SESSION['admin_id'], FILTER_VALIDATE_INT,
         ['options' => ['min_range' => 1]])) {
        header('Location: login.php'); exit;
    }
    // Important: PDO in global scope, not a local variable hidden by require_once.
    global $pdo;
    require_once __DIR__ . '/../config/db.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        http_response_code(503); exit('Database connection unavailable. Check config/db.php.');
    }
    try {
        $a = admin_access($pdo, (int)$_SESSION['admin_id']);
    } catch (Throwable $e) {
        error_log('ScholarMatch admin access: '.$e->getMessage());
        http_response_code(503);
        exit('Admin setup missing or database unavailable. Follow INSTALL_FIRST_BN.txt and run System Preflight.');
    }
    if (!$a || (int)$a['is_active'] !== 1) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: login.php'); exit;
    }
    $_SESSION['admin_id'] = (int)$a['id'];
    $_SESSION['admin_username'] = (string)$a['username'];
    $_SESSION['admin_role'] = (string)$a['role'];
}
function admin_can_edit(): bool {
    return in_array((string)($_SESSION['admin_role'] ?? ''), ['superadmin', 'editor'], true);
}
function admin_is_super(): bool {
    return ($_SESSION['admin_role'] ?? '') === 'superadmin';
}
function require_admin_editor(): void {
    if (!admin_can_edit()) { http_response_code(403); exit('Editor or Super Admin permission required.'); }
}
function require_admin_super(): void {
    if (!admin_is_super()) { http_response_code(403); exit('Super Admin permission required.'); }
}
function admin_log(PDO $pdo, string $action, string $type, int $id = 0, string $detail = ''): void {
    try {
        $detail = function_exists('mb_substr')
            ? mb_substr($detail, 0, 500, 'UTF-8') : substr($detail, 0, 500);
        $s=$pdo->prepare('INSERT INTO admin_audit_log
            (admin_id,action,subject_type,subject_id,detail) VALUES (?,?,?,?,?)');
        $s->execute([$_SESSION['admin_id'] ?? null,$action,$type,$id > 0 ? $id : null,$detail]);
    } catch (Throwable $e) {
        // Earlier code deliberately permits content actions even when the log is unavailable.
        error_log('ScholarMatch admin audit failure: '.$e->getMessage());
    }
}
function admin_setting(PDO $pdo, string $key): string {
    try {
        $s=$pdo->prepare('SELECT setting_value FROM admin_site_setting WHERE setting_key=? LIMIT 1');
        $s->execute([$key]);
        $v=$s->fetchColumn();
        return $v === false ? '' : (string)$v;
    } catch (Throwable $e) { return ''; }
}
