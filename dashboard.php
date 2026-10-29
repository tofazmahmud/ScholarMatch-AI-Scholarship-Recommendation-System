<?php
require_once __DIR__.'/includes/v2_layout.php';
sm_v2_require_install($pdo);
$p=sm_v2_profile($pdo);$id=$p?(int)$p['id']:0;
$counts=['matches'=>0,'eligible'=>0,'saved'=>0,'tracking'=>0];$recent=[];
$bookmarkHealthy=sm_v2_table_healthy($pdo,'bookmark');
if($id){
  foreach(['matches'=>"SELECT COUNT(*) FROM recommendation WHERE profile_id=?",'eligible'=>"SELECT COUNT(*) FROM recommendation WHERE profile_id=? AND eligibility_status='Eligible'",'tracking'=>"SELECT COUNT(*) FROM application_tracker WHERE profile_id=?"] as $k=>$sql){$s=$pdo->prepare($sql);$s->execute([$id]);$counts[$k]=(int)$s->fetchColumn();}
  $s=$pdo->prepare('SELECT s.id,s.name,s.provider,s.country,s.deadline,r.eligibility_status FROM recommendation r JOIN scholarship s ON s.id=r.scholarship_id WHERE r.profile_id=? AND s.is_active=1 AND s.deadline>=CURDATE() ORDER BY (r.eligibility_status=\'Eligible\') DESC,r.match_score DESC LIMIT 5');$s->execute([$id]);$recent=$s->fetchAll();
}
$upcoming=[];
if ($id && $bookmarkHealthy) {
    try {
        $s = $pdo->prepare('SELECT COUNT(*) FROM bookmark WHERE profile_id=?');
        $s->execute([$id]); $counts['saved']=(int)$s->fetchColumn();
    } catch (Throwable $e) { $bookmarkHealthy=false; }
}
if($id && $bookmarkHealthy){$s=$pdo->prepare('SELECT s.id,s.name,s.deadline FROM bookmark b JOIN scholarship s ON s.id=b.scholarship_id WHERE b.profile_id=? AND s.is_active=1 AND s.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) ORDER BY s.deadline LIMIT 5');$s->execute([$id]);$upcoming=$s->fetchAll();}
$stale=$id && sm_v2_matches_stale($pdo,$id);
sm_v2_head('Your dashboard'); sm_v2_begin('STUDENT WORKSPACE','Your scholarship journey','One workspace for your profile, recommendations, saved opportunities and applications.');
?>
<?php if(!$bookmarkHealthy): ?><div class="v2-alert">Bookmarks database table is unavailable (InnoDB issue). Dashboard remains available; see Admin → System Health and installation guide. Do not delete database files.</div><?php endif; ?>
<?php if($stale): ?><div class="v2-alert">Your profile or scholarship rules changed. <a href="find_matches.php">Refresh recommendations</a> before trusting saved eligibility.</div><?php endif; ?>
<div class="v2-stats"><div class="v2-stat"><b><?= sm_v2_profile_percent($p) ?>%</b><span>Profile complete</span></div><div class="v2-stat"><b><?= $counts['eligible'] ?></b><span>Eligible matches</span></div><div class="v2-stat"><b><?= $counts['saved'] ?></b><span>Saved scholarships</span></div><div class="v2-stat"><b><?= $counts['tracking'] ?></b><span>Applications tracked</span></div></div>
<section class="v2-card"><div class="v2-split"><div><h2>Make your next move</h2><p>Complete your profile, find scholarships and follow your application progress.</p></div><div class="v2-actions"><a class="v2-btn" href="<?= $p?'profile.php':'upload_cv.php' ?>"><?= $p?'Edit profile':'Upload a CV' ?></a><a class="v2-btn secondary" href="find_matches.php">Find matches</a></div></div>
<div class="v2-progress" role="progressbar" aria-valuenow="<?= sm_v2_profile_percent($p) ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?= sm_v2_profile_percent($p) ?>%"></span></div>
<p class="v2-muted">Profile strength is a completion indicator, not an eligibility score.</p></section>
<div class="v2-two"><section class="v2-card"><div class="v2-split"><h2>Your recent matches</h2><a href="recommendations.php">View all →</a></div>
<?php if(!$recent): ?><p>No recommendations yet. Complete your profile and generate matches.</p><?php endif; ?>
<?php foreach($recent as $r): ?><div class="v2-line"><div><strong><?= sm_h($r['name']) ?></strong><small><?= sm_h($r['provider']) ?> · <?= sm_h($r['country']) ?></small></div><?= sm_v2_badge($r['eligibility_status']) ?><a href="scholarship_details.php?id=<?= (int)$r['id'] ?>">Details</a></div><?php endforeach; ?></section>
<section class="v2-card"><h2>Upcoming saved deadlines</h2><?php if(!$upcoming): ?><p>No saved scholarships due within 30 days.</p><?php endif; ?><?php foreach($upcoming as $u): ?><div class="v2-line"><a href="scholarship_details.php?id=<?= (int)$u['id'] ?>"><?= sm_h($u['name']) ?></a><strong><?= sm_h($u['deadline']) ?></strong></div><?php endforeach; ?><a href="deadline_calendar.php">View calendar →</a><hr style="margin:18px 0;border:0;border-top:1px solid #e1e7f1"><h2>Explore your tools</h2><div class="v2-tool-grid"><a href="semantic_search.php">⌕ Semantic search</a><a href="bookmarks.php">☆ Bookmarks</a><a href="compare.php">⇄ Compare</a><a href="application_tracker.php">✓ Application tracker</a><a href="deadline_calendar.php">▦ Deadline calendar</a><a href="upload_cv.php">▧ CV extraction</a></div></section></div></main><?php sm_v2_footer(); ?>
