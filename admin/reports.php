<?php
require_once __DIR__.'/../includes/admin_layout.php';require_admin();require_once __DIR__.'/../config/db.php';
$country=$pdo->query('SELECT country,COUNT(*) total FROM scholarship GROUP BY country ORDER BY total DESC,country LIMIT 12')->fetchAll();
$funding=$pdo->query('SELECT funding_type,COUNT(*) total FROM scholarship GROUP BY funding_type ORDER BY total DESC,funding_type')->fetchAll();
$statuses=$pdo->query('SELECT eligibility_status,COUNT(*) total FROM recommendation GROUP BY eligibility_status')->fetchAll();
$upcoming=$pdo->query('SELECT id,name,provider,deadline FROM scholarship WHERE is_active=1 AND deadline>=CURDATE() ORDER BY deadline LIMIT 30')->fetchAll();
$missing=$pdo->query('SELECT s.id,s.name FROM scholarship s LEFT JOIN requirement r ON s.id=r.scholarship_id WHERE r.id IS NULL ORDER BY s.id DESC LIMIT 80')->fetchAll();
if(($_GET['export']??'')==='upcoming'){
 header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="scholarmatch_upcoming.csv"');header('Cache-Control: no-store');
 $fp=fopen('php://output','wb');fputcsv($fp,['Scholarship ID','Name','Provider','Deadline']);
 foreach($upcoming as $r){$vals=[(string)$r['id'],$r['name'],$r['provider'],$r['deadline']];foreach($vals as &$v){$v=(string)$v;if(preg_match('/^[\x00-\x20]*[=+@\-]/',$v))$v="'".$v;}unset($v);fputcsv($fp,$vals);}fclose($fp);exit;
}
sm_admin_header('Reports & Analytics','reports');
?>
<p class="admin-v3-muted">Aggregate statistics and catalogue quality. These are local database counts—not verified claims about external scholarship providers.</p>
<div class="v2-two"><section class="v2-card"><h2>Scholarships by country</h2><table class="admin-v3-table"><thead><tr><th>Country</th><th>Records</th></tr></thead><tbody><?php foreach($country as $r):?><tr><td><?=adm_h($r['country'])?></td><td><?=(int)$r['total']?></td></tr><?php endforeach;?></tbody></table></section><section class="v2-card"><h2>By funding type</h2><table class="admin-v3-table"><thead><tr><th>Funding</th><th>Records</th></tr></thead><tbody><?php foreach($funding as $r):?><tr><td><?=adm_h($r['funding_type'])?></td><td><?=(int)$r['total']?></td></tr><?php endforeach;?></tbody></table><h2>Eligibility results</h2><table class="admin-v3-table"><thead><tr><th>Status</th><th>Records</th></tr></thead><tbody><?php foreach($statuses as $r):?><tr><td><?=adm_h($r['eligibility_status'])?></td><td><?=(int)$r['total']?></td></tr><?php endforeach;?></tbody></table></section></div>
<section class="v2-card"><div class="v2-split"><h2>Upcoming scholarship deadlines</h2><a class="v2-btn secondary" href="?export=upcoming">Export CSV</a></div><div class="admin-v3-table-wrap"><table class="admin-v3-table"><thead><tr><th>Scholarship</th><th>Provider</th><th>Deadline</th></tr></thead><tbody><?php foreach($upcoming as $r):?><tr><td><a href="scholarships.php?edit=<?=(int)$r['id']?>"><?=adm_h($r['name'])?></a></td><td><?=adm_h($r['provider'])?></td><td><?=adm_h($r['deadline'])?></td></tr><?php endforeach;?><?php if(!$upcoming):?><tr><td colspan="3">No upcoming scholarships recorded.</td></tr><?php endif;?></tbody></table></div></section>
<section class="v2-card"><h2>Records missing eligibility requirements · <?=count($missing)?></h2><div class="admin-v3-table-wrap"><table class="admin-v3-table"><thead><tr><th>Scholarship</th><th>Review</th></tr></thead><tbody><?php foreach($missing as $r):?><tr><td><?=adm_h($r['name'])?></td><td><a href="requirements.php?scholarship_id=<?=(int)$r['id']?>">Add requirements →</a></td></tr><?php endforeach;?><?php if(!$missing):?><tr><td colspan="2">All recorded scholarships have at least one requirement.</td></tr><?php endif;?></tbody></table></div></section>
<?php sm_admin_footer();
