
<?php
// includes/student_session.php

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $secure = !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Private identifier for this anonymous student.
// Do not use this value in URLs.
if (empty($_SESSION['sm_student_token'])) {
    $_SESSION['sm_student_token'] =
        bin2hex(random_bytes(32));
}

// CSRF protection token.
if (empty($_SESSION['sm_student_csrf'])) {
    $_SESSION['sm_student_csrf'] =
        bin2hex(random_bytes(32));
}

function sm_student_csrf(): string
{
    return $_SESSION['sm_student_csrf'];
}

function sm_verify_student_csrf($token): bool
{
    return is_string($token)
        && hash_equals(sm_student_csrf(), $token);
}
