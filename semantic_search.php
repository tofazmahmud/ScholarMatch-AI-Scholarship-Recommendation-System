<?php
require_once __DIR__.'/includes/v2_layout.php';require_once __DIR__.'/includes/semantic.php';
$q=trim(substr((string)($_GET['q']??''),0,160));$country=trim(substr((string)($_GET['country']??''),0,80));$mode=(string)($_GET['mode']??'hybrid');
if(!in_array($mode,['hybrid','keyword'],true))$mode='hybrid';
$warning='';$semantic=false;
$filters=['s.is_active=1','s.deadline>=CURDATE()'];$params=[];
if($country!==''){$filters[]='s.country LIKE ?';$params[]='%'.$country.'%';}
$sql='SELECT s.*,e.vector_json,e.content_hash FROM scholarship s LEFT JOIN scholarship_embedding e ON e.scholarship_id=s.id WHERE '.implode(' AND ',$filters).' ORDER BY s.deadline LIMIT 500';
try{$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();}
catch(Throwable $e){$rows=[];$warning='Run database/01_V2_SAFE_EXISTING.sql (after preflight) to enable semantic indexing.';}
$vector=null;
if($q!=='' && $mode==='hybrid' && $rows){
 $hasIndex=false;foreach($rows as $r) if($r['vector_json']){$hasIndex=true;break;}
 if($hasIndex){try{$vector=sm_semantic_embedding($q);$semantic=true;}catch(Throwable $e){$warning='Embedding service unavailable. Results use keyword relevance instead.';}}
 else $warning='No embeddings indexed yet. Using keyword relevance. Run the CLI indexing command in the README to enable semantic search.';
}
$ranked=[];
if($q!=='') foreach($rows as $r){
 $doc=sm_semantic_document($r);$lex=sm_semantic_lexical($q,$doc);
 $sim=0.;$valid=false;
 if($vector!==null && $r['vector_json'] && hash_equals((string)$r['content_hash'],hash('sha256',$doc))){
   $stored=json_decode($r['vector_json'],true);
   if(is_array($stored) && count($stored)===768){$sim=sm_semantic_cosine($vector,$stored);$valid=true;}
 }
 // Strictly relevance; no eligibility claims are made.
 $score=$valid?(0.7*max(0,$sim)+0.3*$lex):$lex;
 if($score>0) {$r['_score']=$score;$ranked[]=$r;}
}
usort($ranked,fn($a,$b)=>($b['_score']<=>$a['_score']) ?: strcmp((string)$a['deadline'],(string)$b['deadline']));
$ranked=array_slice($ranked,0,30);
sm_v2_head('Smart scholarship search');sm_v2_begin('DISCOVER BY MEANING','Find opportunities in your own words','Hybrid discovery uses semantic embeddings when indexed and available; keyword search remains the reliable fallback.');
?>
<form class="v2-card v2-form" action="semantic_search.php" method="GET"><div class="v2-wide"><label for="q">What are you looking for?</label><input id="q" name="q" maxlength="160" value="<?= sm_h($q) ?>" placeholder="e.g. funding for artificial intelligence and medical imaging research" required></div><div><label for="country">Country (optional)</label><input id="country" name="country" maxlength="80" value="<?= sm_h($country) ?>"></div><div><label for="mode">Search mode</label><select name="mode" id="mode"><option value="hybrid" <?= $mode==='hybrid'?'selected':'' ?>>Hybrid / semantic when available</option><option value="keyword" <?= $mode==='keyword'?'selected':'' ?>>Keyword only</option></select></div><button class="v2-btn">Search scholarships</button></form>
<?php if($warning): ?><div class="v2-alert"><?= sm_h($warning) ?></div><?php endif; ?>
<?php if($q!==''): ?><p class="v2-muted"><?= count($ranked) ?> results · <?= $semantic?'Semantic embeddings + keywords':'Keyword fallback' ?> · relevance is not eligibility.</p><div class="v2-grid"><?php foreach($ranked as $r): ?><article class="v2-card"><span class="v2-kicker"><?= sm_h($r['country']) ?> · <?= sm_h($r['funding_type']) ?></span><h3><?= sm_h($r['name']) ?></h3><p><?= sm_h($r['provider']) ?></p><p><?= sm_h($r['field']) ?> · <?= sm_h($r['degree_level']) ?></p><p class="v2-muted">Relevance: <?= (int)round($r['_score']*100) ?> (not an eligibility score)</p><a class="v2-btn secondary" href="scholarship_details.php?id=<?= (int)$r['id'] ?>">View scholarship →</a></article><?php endforeach; ?></div><?php if(!$ranked): ?><section class="v2-card">No relevant opportunities found with the current catalogue. Try broader wording.</section><?php endif; ?><?php endif; ?>
</main><?php sm_v2_footer(); ?>
