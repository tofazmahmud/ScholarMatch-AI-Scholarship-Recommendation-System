<?php
require_once __DIR__.'/../includes/admin_layout.php';require_admin();require_once __DIR__.'/../config/db.php';
$queries=[
 'Scholarships'=>'SELECT COUNT(*) FROM scholarship',
 'Active'=>'SELECT COUNT(*) FROM scholarship WHERE is_active=1',
 'Expired'=>'SELECT COUNT(*) FROM scholarship WHERE deadline < CURDATE()',
 'Requirements'=>'SELECT COUNT(*) FROM requirement',
 'Student sessions'=>'SELECT COUNT(*) FROM profile',
 'Recommendations'=>'SELECT COUNT(*) FROM recommendation',
 'Team accounts'=>'SELECT COUNT(*) FROM admin_access WHERE is_active=1',
 'Logged actions'=>'SELECT COUNT(*) FROM admin_audit_log'
];
$stats=[];foreach($queries as $label=>$sql){try{$stats[$label]=(int)$pdo->query($sql)->fetchColumn();}catch(Throwable $e){$stats[$label]=null;}}
$needs=$pdo->query('SELECT s.id,s.name FROM scholarship s LEFT JOIN requirement r ON s.id=r.scholarship_id WHERE r.id IS NULL ORDER BY s.id DESC LIMIT 8')->fetchAll();
$recent=$pdo->query('SELECT l.created_at,l.action,a.username FROM admin_audit_log l LEFT JOIN admin a ON l.admin_id=a.id ORDER BY l.id DESC LIMIT 7')->fetchAll();
sm_admin_header('Administration Overview','dashboard');
?>
<p class="admin-v3-muted">Welcome, <?=adm_h($_SESSION['admin_username'])?>. Keep your scholarship catalogue accurate, current and explainable.</p>
<div class="v2-stats"><?php foreach($stats as $label=>$value):?><div class="v2-stat"><b><?=($value===null?'—':number_format($value))?></b><span><?=adm_h($label)?></span></div><?php endforeach;?></div>
<div class="admin-v3-dash-grid">
<a class="v2-card admin-v3-link-card" href="scholarships.php"><strong>Scholarship catalogue →</strong><small>Create, edit and deactivate scholarships</small></a>
<a class="v2-card admin-v3-link-card" href="requirements.php"><strong>Eligibility rules →</strong><small>Manage GPA, degree, field and skill requirements</small></a>
<a class="v2-card admin-v3-link-card" href="source_quality.php"><strong>Source verification →</strong><small>Review official URLs and verification dates</small></a>
<a class="v2-card admin-v3-link-card" href="import.php"><strong>CSV bulk import →</strong><small>Validate and import scholarship records</small></a>
<a class="v2-card admin-v3-link-card" href="documents.php"><strong>RAG evidence library →</strong><small>Upload guideline documents for retrieval</small></a>
<a class="v2-card admin-v3-link-card" href="reports.php"><strong>Analytics & exports →</strong><small>Country, funding, results and deadlines</small></a>
<a class="v2-card admin-v3-link-card" href="health.php"><strong>System health →</strong><small>Check all database tables, including bookmark</small></a>
<a class="v2-card admin-v3-link-card" href="account.php"><strong>My Admin Account →</strong><small>Update email, display name and password</small></a>
</div>
<div class="v2-two"><section class="v2-card"><h2>Needs eligibility requirements</h2><?php if(!$needs):?><p>All catalogue records have at least one rule.</p><?php endif;?><?php foreach($needs as $r):?><div class="v2-line"><span><?=adm_h($r['name'])?></span><a href="requirements.php?scholarship_id=<?=(int)$r['id']?>">Review →</a></div><?php endforeach;?></section>
<section class="v2-card"><h2>Recent admin activity</h2><?php if(!$recent):?><p>No activity recorded yet.</p><?php endif;?><?php foreach($recent as $r):?><div class="v2-line"><span><strong><?=adm_h($r['action'])?></strong><small><?=adm_h($r['username']??'Former admin')?> · <?=adm_h($r['created_at'])?></small></span></div><?php endforeach;?><p><a href="audit.php">Full audit log →</a></p></section></div>
<p class="admin-v3-muted">This dashboard deliberately avoids querying the V2 bookmark table directly. Use System Health if it is still inaccessible after migration.</p>
<?php sm_admin_footer();
