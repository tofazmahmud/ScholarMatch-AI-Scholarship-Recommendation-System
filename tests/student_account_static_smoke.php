<?php
/** Static safety checks only. A real MySQL + browser end-to-end test is still required. */
$root=dirname(__DIR__);
function check_sm(bool $condition, string $title): void {
    if (!$condition) { fwrite(STDERR,"FAIL: $title\n"); exit(1); }
    echo "PASS: $title\n";
}
$sql=file_get_contents($root.'/database/03_STUDENT_AUTH_SAFE_ADDITIVE.sql');
$php=file_get_contents($root.'/includes/student_auth.php');
$reg=file_get_contents($root.'/student_register.php');
$login=file_get_contents($root.'/student_login.php');
$logout=file_get_contents($root.'/student_logout.php');
check_sm((bool)preg_match('/CREATE TABLE IF NOT EXISTS student_account/i',$sql),'Additive student_account table present');
check_sm((bool)preg_match('/REFERENCES\s+profile\(id\)/i',$sql),'Original profile is linked by foreign key');
check_sm(!preg_match('/\b(?:DROP\s+TABLE|ALTER\s+TABLE|TRUNCATE\s+TABLE|DELETE\s+FROM|UPDATE\s+profile)\b/i',preg_replace('/--[^\r\n]*/','',$sql)),'Migration does not alter or delete old data');
check_sm(str_contains($reg,'password_hash('),'Registration uses password hashing');
check_sm(str_contains($login,'password_verify('),'Login verifies password hashes');
check_sm(str_contains($php,'session_regenerate_id(true)'),'Login/logout rotate session IDs');
check_sm(str_contains($logout,'sm_verify_student_csrf'),'Logout validates CSRF');
check_sm(str_contains($php,'sm_student_token'),'Existing session-to-profile bridge remains');
echo "Note: static checks are NOT a substitute for MySQL/browser E2E tests.\n";
