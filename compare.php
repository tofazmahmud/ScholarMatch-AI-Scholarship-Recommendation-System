<?php
require_once __DIR__.'/includes/v2_layout.php';
$ids=$_GET['ids']??[];if(!is_array($ids))$ids=[];
$ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn($n)=>$n>0)));
$ids=array_slice($ids,0,3);$rows=[];$rules=[];
if($ids){$q=implode(',',array_fill(0,count($ids),'?'));
 $s=$pdo->prepare("SELECT * FROM scholarship WHERE id IN ($q) AND is_active=1");$s->execute($ids);$found=$s->fetchAll();
 foreach($ids as $id) foreach($found as $r) if((int)$r['id']===$id)$rows[]=$r;
 $s=$pdo->prepare("SELECT scholarship_id, requirement_type,comparison_operator,value,certainty FROM requirement WHERE scholarship_id IN ($q) ORDER BY scholarship_id,id");$s->execute($ids);foreach($s->fetchAll() as $r)$rules[$r['scholarship_id']][]=$r;
}
sm_v2_head('Compare scholarships');sm_v2_begin('SIDE-BY-SIDE','Compare opportunities','Choose two or three saved scholarships to compare available criteria.');
?>
<?php if(count($rows)<2): ?><div class="v2-card">Choose 2–3 scholarships from <a href="bookmarks.php">Saved Scholarships</a> to compare them.</div><?php else: ?>
<div class="v2-table-wrap"><table class="v2-table"><thead><tr><th>Criteria</th><?php foreach($rows as $r): ?><th><?= sm_h($r['name']) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach(['provider'=>'Provider','country'=>'Country','degree_level'=>'Degree','field'=>'Field','funding_type'=>'Funding','deadline'=>'Deadline'] as $key=>$label): ?><tr><th><?= sm_h($label) ?></th><?php foreach($rows as $r): ?><td><?= sm_h($r[$key]?:'Not specified') ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<tr><th>Recorded eligibility rules</th><?php foreach($rows as $r): ?><td><?php foreach(($rules[$r['id']]??[]) as $rule): ?><p><?= sm_h($rule['requirement_type'].' '.$rule['comparison_operator'].' '.$rule['value']) ?> (<?= sm_h($rule['certainty']) ?>)</p><?php endforeach; ?><?php if(empty($rules[$r['id']])): ?>Not recorded<?php endif; ?></td><?php endforeach; ?></tr>
<tr><th>Source</th><?php foreach($rows as $r): ?><td><?php if(filter_var($r['official_url'],FILTER_VALIDATE_URL)): ?><a href="<?= sm_h($r['official_url']) ?>" target="_blank" rel="noopener noreferrer">Provider link ↗</a><?php endif; ?></td><?php endforeach; ?></tr>
</tbody></table></div><?php endif; ?><p class="v2-muted">Comparisons use saved catalogue data, not a guarantee of admission.</p></main><?php sm_v2_footer(); ?>
