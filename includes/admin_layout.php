<?php
require_once __DIR__ . '/admin_auth.php';
if (!function_exists('adm_h') || !function_exists('admin_is_super')) {
    http_response_code(503); exit('Admin core files are inconsistent. Use the single complete package.');
}
function sm_admin_header(string $title,string $area=''): void {
    $role=adm_h($_SESSION['admin_role']??'');
    $name=adm_h($_SESSION['admin_username']??'Admin');
    $titleE=adm_h($title);
    $links=[
      ['dashboard.php','Overview','dashboard'],
      ['scholarships.php','Scholarships','scholarships'],
      ['requirements.php','Requirements','requirements'],
      ['source_quality.php','Source Quality','source'],
      ['documents.php','RAG Documents','documents'],
      ['import.php','CSV Import','import'],
      ['reports.php','Reports','reports'],
      ['health.php','System Health','health'],
      ['audit.php','Audit Log','audit'],
      ['account.php','My Account','account'],
    ];
    if(admin_is_super()) { $links[]=['staff.php','Staff & Roles','staff'];$links[]=['settings.php','Site Settings','settings']; }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$titleE.' · ScholarMatch Admin</title><link rel="stylesheet" href="../assets/css/admin.css"><link rel="stylesheet" href="../assets/css/v2.css"><link rel="stylesheet" href="../assets/css/admin_v3.css"></head><body class="dashboard-body"><div class="admin-v3-shell"><aside class="admin-v3-sidebar"><div class="admin-v3-brand">🎓 ScholarMatch <small>Administration</small></div><nav aria-label="Admin navigation">';
    foreach($links as $l){echo '<a '.($area===$l[2]?'class="active" ':'').'href="'.adm_h($l[0]).'">'.adm_h($l[1]).'</a>';}
    echo '</nav><div class="admin-v3-sidebottom"><strong>'.$name.'</strong><small>'.$role.'</small><form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="'.adm_h(csrf_token()).'"><button class="v2-btn secondary" type="submit">Log out</button></form></div></aside><div class="admin-v3-main"><header class="admin-v3-topbar"><strong>'.$titleE.'</strong><span>'.$name.' · '.$role.'</span></header><main class="v2-wrap"><div class="v2-page-heading"><span class="v2-kicker">SCHOLARMATCH CONTROL CENTER</span><h1>'.$titleE.'</h1></div>';
}
function sm_admin_footer(): void {echo '</main></div></div></body></html>';}
