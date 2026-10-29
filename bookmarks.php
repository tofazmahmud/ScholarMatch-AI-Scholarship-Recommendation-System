<?php
require_once __DIR__.'/includes/v2_layout.php';sm_v2_require_install($pdo);
$p=sm_v2_profile($pdo);$pid=(int)($p['id']??0);$message='';
$bookmarkHealthy=sm_v2_table_healthy($pdo, 'bookmark');
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!$bookmarkHealthy){http_response_code(503);exit('Bookmark table unavailable. See Admin System Health.');}
 sm_v2_post_guard();
 if(!$pid){header('Location: profile.php');exit;}
 $sid=filter_input(INPUT_POST,'scholarship_id',FILTER_VALIDATE_INT);
 if(!$sid){http_response_code(400);exit('Invalid scholarship.');}
 if(($_POST['action']??'')==='save'){
  $s=$pdo->prepare('INSERT IGNORE INTO bookmark(profile_id,scholarship_id) SELECT ?,id FROM scholarship WHERE id=? AND is_active=1');$s->execute([$pid,$sid]);
 }elseif(($_POST['action']??'')==='remove'){$s=$pdo->prepare('DELETE FROM bookmark WHERE profile_id=? AND scholarship_id=?');$s->execute([$pid,$sid]);}
 header('Location: bookmarks.php');exit;
}
$rows=[];
if($pid && $bookmarkHealthy){$s=$pdo->prepare('SELECT s.id,s.name,s.provider,s.country,s.degree_level,s.funding_type,s.deadline FROM bookmark b JOIN scholarship s ON s.id=b.scholarship_id WHERE b.profile_id=? ORDER BY s.deadline ASC');$s->execute([$pid]);$rows=$s->fetchAll();}
sm_v2_head('Saved scholarships'); sm_v2_begin('YOUR SHORTLIST','Saved scholarships','Save a scholarship from Explore or Recommendations, and find it here later in this browser session.');
?>
<?php if(!$bookmarkHealthy): ?><div class="v2-alert">Bookmark table unavailable due to database problem. Check Admin System Health; no data has been modified.</div><?php elseif(!$pid): ?><div class="v2-alert">Create a <a href="profile.php">student profile</a> to save scholarships.</div><?php elseif(!$rows): ?><div class="v2-card">No saved scholarships yet. <a href="explore.php">Explore opportunities →</a></div><?php else: ?>
<form action="compare.php" method="GET"><div class="v2-grid">
<?php foreach($rows as $r): ?><article class="v2-card"><label class="v2-compare-check"><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>"> Compare</label><span class="v2-kicker"><?= sm_h($r['country']) ?></span><h3><?= sm_h($r['name']) ?></h3><p><?= sm_h($r['provider']) ?></p><p><?= sm_h($r['degree_level']) ?> · <?= sm_h($r['funding_type']) ?></p><p>Deadline: <?= sm_h($r['deadline']?:'Not specified') ?></p><div class="v2-actions"><a class="v2-btn secondary" href="scholarship_details.php?id=<?= (int)$r['id'] ?>">Details</a></div></article><?php endforeach; ?>
</div><button class="v2-btn" type="submit">Compare selected (2–3)</button></form>
<?php endif; ?></main><?php sm_v2_footer(); ?>
