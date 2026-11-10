<?php
require_once __DIR__ . '/includes/student_auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !sm_verify_student_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403); exit('Invalid logout request.');
}
sm_auth_sign_out();
header('Location: index.php');
exit;
