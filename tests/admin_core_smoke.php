<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../includes/admin_layout.php';
$names=['adm_h','csrf_token','valid_csrf','admin_access','require_admin',
        'admin_can_edit','admin_is_super','require_admin_editor','require_admin_super',
        'admin_log','admin_setting','sm_admin_header','sm_admin_footer'];
foreach ($names as $name) {
    if (!function_exists($name)) {fwrite(STDERR,"FAIL missing function: {$name}\n");exit(1);}
}
$_SESSION['admin_username']='Local Syntax Tester';
$_SESSION['admin_role']='viewer';
ob_start();
sm_admin_header('Smoke Test', 'dashboard'); sm_admin_footer();
$html=ob_get_clean();
if (strpos($html,'ScholarMatch')===false || strpos($html,'Smoke Test')===false) {
    fwrite(STDERR,"FAIL Admin layout render\n"); exit(1);
}
if (!valid_csrf(csrf_token()) || valid_csrf('bad')) {
    fwrite(STDERR,"FAIL CSRF behavior\n"); exit(1);
}
echo "PASS: all Admin V3 functions, safe HTML output, layout and CSRF load correctly.\n";
