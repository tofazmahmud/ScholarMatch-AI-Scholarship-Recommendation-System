<?php
require_once __DIR__.'/includes/v2_layout.php';sm_v2_require_install($pdo);
$p=sm_v2_profile($pdo);$pid=(int)($p['id']??0);$statuses=['Interested','Preparing','Applied','Interview','Awarded','Rejected'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 sm_v2_post_guard();if(!$pid){header('Location: profile.php');exit;}
 $sid=filter_input(INPUT_POST,'scholarship_id',FILTER_VALIDATE_INT);$status=(string)($_POST['status']??'');
 if(!$sid||!in_array($status,$statuses,true)){http_response_code(400);exit('Invalid tracker update.');}
 $notes=trim((string)($_POST['notes']??''));if(strlen($notes)>2000){http_response_code(400);exit('Notes too long.');}
 $s=$pdo->prepare('INSERT INTO application_tracker(profile_id,scholarship_id,status,notes) SELECT ?,id,?,? FROM scholarship WHERE id=? AND is_active=1 ON DUPLICATE KEY UPDATE status=VALUES(status),notes=VALUES(notes)');
 $s->execute([$pid,$status,$notes,$sid]);header('Location: application_tracker.php');exit;
}
$rows=[];if($pid){$s=$pdo->prepare('SELECT t.*,s.name,s.provider,s.country,s.deadline FROM application_tracker t JOIN scholarship s ON s.id=t.scholarship_id WHERE t.profile_id=? ORDER BY t.updated_at DESC');$s->execute([$pid]);$rows=$s->fetchAll();}
sm_v2_head('Application tracker');sm_v2_begin('PROGRESS','My applications','Privately track application stages. ScholarMatch does not submit applications for you.');
?>
<?php if(!$pid): ?><div class="v2-alert">Create a <a href="profile.php">profile</a> first.</div><?php else: ?>
<section class="v2-card"><h2>Add a scholarship</h2><form method="POST" class="v2-form"><input type="hidden" name="csrf_token" value="<?= sm_h(sm_student_csrf()) ?>"><div><label for="sid">Scholarship</label><select name="scholarship_id" id="sid" required><option value="">Choose a scholarship</option>
<?php $all=$pdo->query('SELECT id,name FROM scholarship WHERE is_active=1 ORDER BY name LIMIT 500')->fetchAll();foreach($all as $r): ?><option value="<?= (int)$r['id'] ?>"><?= sm_h($r['name']) ?></option><?php endforeach; ?></select></div><div><label for="status">Status</label><select name="status" id="status"><?php foreach($statuses as $status): ?><option><?= sm_h($status) ?></option><?php endforeach; ?></select></div><div class="v2-wide"><label for="notes">Notes (optional)</label><textarea id="notes" name="notes" maxlength="2000" rows="2" placeholder="Checklist or follow-up information"></textarea></div><button class="v2-btn">Save progress</button></form></section>
<div class="v2-grid"><?php foreach($rows as $r): ?><section class="v2-card"><span class="v2-kicker"><?= sm_h($r['status']) ?></span><h3><?= sm_h($r['name']) ?></h3><p><?= sm_h($r['provider']) ?> · <?= sm_h($r['country']) ?></p><p>Deadline: <?= sm_h($r['deadline']?:'Unknown') ?></p>
<form method="POST" class="v2-stack"><input type="hidden" name="csrf_token" value="<?= sm_h(sm_student_csrf()) ?>"><input type="hidden" name="scholarship_id" value="<?= (int)$r['scholarship_id'] ?>"><label>Stage <select name="status"><?php foreach($statuses as $st): ?><option <?= $st===$r['status']?'selected':'' ?>><?= sm_h($st) ?></option><?php endforeach; ?></select></label><label>Notes <textarea name="notes" rows="2" maxlength="2000"><?= sm_h($r['notes']) ?></textarea></label><button class="v2-btn secondary">Update</button></form></section><?php endforeach; ?></div>
<?php if(!$rows): ?><p class="v2-muted">No application progress recorded yet.</p><?php endif; ?><?php endif; ?></main><?php sm_v2_footer(); ?>
