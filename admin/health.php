<?php
require_once __DIR__.'/../includes/admin_layout.php';require_admin();require_once __DIR__.'/../config/db.php';
$tables=['admin','admin_access','admin_audit_log','admin_site_setting','profile','preference','scholarship','requirement','recommendation','bookmark','application_tracker','recommendation_state','scholarship_embedding','scholarship_document','scholarship_chunk'];
$checks=[];
foreach($tables as $name){
 try {$pdo->query('SELECT 1 FROM `'.$name.'` LIMIT 1');$checks[$name]='OK';}
 catch(Throwable $e){$checks[$name]='UNAVAILABLE — check migration/table integrity';}
}
$ext=[];foreach(['pdo_mysql','curl','fileinfo','zip'] as $e)$ext[$e]=extension_loaded($e)?'Available':'Unavailable';
sm_admin_header('System Health','health');
?>
<div class="admin-v3-note">Read-only diagnostics. A phpMyAdmin table listed in Structure can still be broken in InnoDB; these checks attempt to read it. Never remove physical .ibd/.frm files from this page.</div>
<div class="v2-two"><section class="v2-card"><h2>Database table checks</h2><div class="admin-v3-table-wrap"><table class="admin-v3-table"><tr><th>Table</th><th>Status</th></tr><?php foreach($checks as $name=>$status):?><tr><td><?=adm_h($name)?></td><td><?=adm_h($status)?></td></tr><?php endforeach;?></table></div></section><section class="v2-card"><h2>PHP environment</h2><p>PHP version: <strong><?=adm_h(PHP_VERSION)?></strong></p><table class="admin-v3-table"><tr><th>Extension</th><th>Status</th></tr><?php foreach($ext as $e=>$v):?><tr><td><?=adm_h($e)?></td><td><?=adm_h($v)?></td></tr><?php endforeach;?></table><p class="admin-v3-muted">PHP 8.0 is end-of-life: upgrade your XAMPP PHP runtime before public production deployment.</p><hr><p><a class="v2-btn secondary" href="reports.php">View analytics</a></p><p><a class="v2-btn secondary" href="dashboard.php">Admin overview</a></p></section></div>
<?php sm_admin_footer();
