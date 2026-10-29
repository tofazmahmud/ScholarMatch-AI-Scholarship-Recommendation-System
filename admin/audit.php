<?php
require_once __DIR__.'/../includes/admin_layout.php';require_admin();require_once __DIR__.'/../config/db.php';
$page=max(1,min(1000,(int)($_GET['page']??1)));$limit=40;$skip=($page-1)*$limit;$filter=trim((string)($_GET['action']??''));
if(strlen($filter)>60)$filter='';
$filters='';$args=[];
if($filter!==''){$filters=' WHERE l.action = ?';$args[]=$filter;}
$q=$pdo->prepare('SELECT COUNT(*) FROM admin_audit_log l'.$filters);$q->execute($args);$count=(int)$q->fetchColumn();
$q=$pdo->prepare('SELECT l.id,l.created_at,l.action,l.subject_type,l.subject_id,l.detail,a.username FROM admin_audit_log l LEFT JOIN admin a ON a.id=l.admin_id'.$filters.' ORDER BY l.id DESC LIMIT '.$limit.' OFFSET '.$skip);$q->execute($args);$rows=$q->fetchAll();
sm_admin_header('Activity & Audit Log','audit');
?>
<p class="admin-v3-muted">Who changed what and when. This log stores metadata, not passwords, API keys or complete student profiles. Viewer role can inspect logs.</p>
<form method="get" class="v2-actions"><label>Action filter <input name="action" maxlength="60" value="<?=adm_h($filter)?>" placeholder="e.g. account.password"></label><button class="v2-btn">Filter</button><a class="v2-btn secondary" href="audit.php">Clear</a></form>
<section class="v2-card"><h2><?=number_format($count)?> recorded actions</h2><div class="admin-v3-table-wrap"><table class="admin-v3-table"><thead><tr><th>Date/time</th><th>Admin</th><th>Action</th><th>Record</th><th>Detail</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><?=adm_h($row['created_at'])?></td><td><?=adm_h($row['username']??'Removed admin')?></td><td><?=adm_h($row['action'])?></td><td><?=adm_h($row['subject_type'])?> #<?=adm_h($row['subject_id'])?></td><td><?=adm_h($row['detail'])?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="5">No activity logged yet.</td></tr><?php endif;?></tbody></table></div><div class="v2-actions"><?php if($page>1):?><a class="v2-btn secondary" href="?page=<?=$page-1?>&action=<?=urlencode($filter)?>">← Previous</a><?php endif;?><?php if($skip+$limit<$count):?><a class="v2-btn secondary" href="?page=<?=$page+1?>&action=<?=urlencode($filter)?>">Next →</a><?php endif;?></div></section>
<?php sm_admin_footer();
