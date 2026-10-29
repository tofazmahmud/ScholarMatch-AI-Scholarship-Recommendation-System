<?php
require_once __DIR__.'/includes/v2_layout.php';
require_once __DIR__.'/includes/rag_retriever.php';
require_once __DIR__.'/includes/rag_generator.php';
require_once __DIR__.'/includes/manual_explainer.php';
$scholarshipId=filter_input(INPUT_GET,'scholarship_id',FILTER_VALIDATE_INT);
$p=sm_v2_profile($pdo);$pid=(int)($p['id']??0);
if(!$pid||!$scholarshipId){http_response_code(404);exit('Create a profile and generate recommendations first.');}
$evidence=sm_rag_retrieve($pdo,(string)$_SESSION['sm_student_token'],(int)$scholarshipId);
if(!$evidence){http_response_code(404);exit('No recommendation for this student session.');}
$details=sm_manual_breakdown($evidence);$computed=sm_manual_status($details);
$stale=sm_v2_matches_stale($pdo,$pid);
$manual=sm_manual_explanation($evidence,$details);$explanation=$manual;$mode='Manual PHP';$error='';
$evidence['evaluation']=$details;
// Reuse our trusted deterministic calculation, never let AI set eligibility.
$evidence['match']['status']=$computed;
if($_SERVER['REQUEST_METHOD']==='POST'){
 sm_v2_post_guard();
 $request=(string)($_POST['mode']??'manual');
 if(!in_array($request,['manual','ai','auto'],true)){$error='Invalid mode.';}
 elseif($stale){$error='Your eligibility data changed. Refresh matches first.';}
 elseif($request!=='manual'){
   if(($_POST['consent']??'')!=='yes')$error='AI processing consent is required.';
   else {
      try{$generated=sm_rag_generate($evidence);$explanation='PHP eligibility: '.$computed.'. '.trim($generated);$mode='Gemini RAG (AI-assisted)';}
      catch(Throwable $e){$error=$request==='ai'?'Gemini could not generate this explanation. Showing manual explanation instead.':'AI was unavailable; manual explanation shown.';
        error_log('ScholarMatch RAG: '.$e->getMessage());$mode='Manual fallback';}
   }
 }
}
$s=$evidence['scholarship'];$url=(string)($s['official_url']??'');$valid=filter_var($url,FILTER_VALIDATE_URL)&&in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true);
sm_v2_head('Why this fits');sm_v2_begin('EVIDENCE & EXPLANATION','Why this fits','View the exact recorded rules and choose a manual or AI-assisted explanation.');
?>
<a href="recommendations.php" class="v2-back">← Recommendations</a>
<?php if($stale): ?><div class="v2-alert">Profile or catalogue data changed since matching. <a href="find_matches.php">Refresh matches</a>. AI generation is disabled until then.</div><?php endif; ?>
<div class="v2-two"><section class="v2-card"><span class="v2-kicker">SCHOLARSHIP EVIDENCE</span><h2><?= sm_h($s['name']) ?></h2><p><?= sm_h($s['provider']) ?> · <?= sm_h($s['country']) ?></p><p>Degree: <?= sm_h($s['degree_level']) ?> · Field: <?= sm_h($s['field']) ?></p><p>Deadline: <?= sm_h($s['deadline']?:'Unspecified') ?></p><?php if($valid): ?><a target="_blank" rel="noopener noreferrer" href="<?= sm_h($url) ?>">Provider website ↗</a><?php endif; ?><p class="v2-muted">Provider link may be a demo placeholder. Verify the provider and any application URL independently.</p></section>
<section class="v2-card"><span class="v2-kicker">PHP ELIGIBILITY ENGINE</span><h2><?= sm_v2_badge($computed) ?></h2><p>Preference fit: <?= sm_h($evidence['match']['suitability']?:'Not scored') ?></p><p class="v2-muted">Computed from recorded requirements, not by Gemini. Explanations do not guarantee admission.</p></section></div>
<section class="v2-card"><h2>Requirement-by-requirement evaluation</h2>
<?php if(!$details): ?><p>No structured requirements recorded. Eligibility cannot be verified.</p><?php else: ?><div class="v2-table-wrap"><table class="v2-table"><thead><tr><th>Source rule</th><th>Requirement</th><th>Your information</th><th>Evaluation</th></tr></thead><tbody><?php foreach($details as $d): ?><tr><td>#<?= (int)$d['id'] ?></td><td><?= sm_h(ucwords($d['type']).' '.$d['operator'].' '.$d['required']) ?></td><td><?= sm_h($d['actual']===null||$d['actual']===''?'Not provided':$d['actual']) ?></td><td><span class="v2-pill <?= sm_h($d['result']) ?>"><?= sm_h(ucfirst($d['result'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php if(!empty($evidence['document_sources'])): ?><section class="v2-card"><h2>Supporting document passages</h2><p class="v2-muted">Retrieved excerpts are supporting context. Unverified documents must not be treated as authoritative eligibility rules.</p><?php foreach($evidence['document_sources'] as $src): ?><div class="v2-source"><strong><?= sm_h($src['title']) ?> · passage #<?= (int)$src['sequence_no'] ?></strong> <span class="v2-pill <?= $src['is_verified']?'pass':'unknown' ?>"><?= $src['is_verified']?'Admin verified':'Unverified' ?></span><p><?= sm_h(substr((string)$src['chunk_text'],0,1050)) ?></p><?php if(filter_var($src['source_url'],FILTER_VALIDATE_URL)): ?><a href="<?= sm_h($src['source_url']) ?>" target="_blank" rel="noopener noreferrer">Source URL ↗</a><?php endif; ?></div><?php endforeach; ?></section><?php endif; ?>
<section class="v2-card"><div class="v2-split"><div><span class="v2-kicker">EXPLANATION</span><h2><?= sm_h($mode) ?></h2></div><span class="v2-pill"><?= $mode==='Gemini RAG (AI-assisted)'?'AI':'No AI needed' ?></span></div>
<?php if($error): ?><p class="v2-alert" role="alert"><?= sm_h($error) ?></p><?php endif; ?>
<div class="v2-explanation"><?= nl2br(sm_h($explanation)) ?></div>
<form method="POST" class="v2-form"><input type="hidden" name="csrf_token" value="<?= sm_h(sm_student_csrf()) ?>"><div><label for="mode">Explanation mode</label><select id="mode" name="mode"><option value="manual">Manual PHP (always available)</option><option value="ai">Gemini RAG</option><option value="auto">Automatic fallback: AI → PHP</option></select></div><div><label><input type="checkbox" name="consent" value="yes"> I consent to send relevant profile/scholarship facts to Gemini if AI or Auto is selected.</label></div><button class="v2-btn" <?= $stale?'disabled':'' ?>>Generate explanation</button></form><p class="v2-muted">Manual is deterministic and offline. AI/Auto may incur API usage and transmit relevant academic data.</p></section>
</main><?php sm_v2_footer(); ?>
