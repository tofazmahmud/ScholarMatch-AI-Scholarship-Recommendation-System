<?php
require_once __DIR__.'/includes/v2_layout.php';
$month=(string)($_GET['month']??date('Y-m'));
if(!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/',$month))$month=date('Y-m');
$start=$month.'-01';$end=date('Y-m-t',strtotime($start));
$s=$pdo->prepare('SELECT id,name,provider,deadline FROM scholarship WHERE is_active=1 AND deadline BETWEEN ? AND ? ORDER BY deadline');$s->execute([$start,$end]);$rows=$s->fetchAll();
$byDay=[];foreach($rows as $r)$byDay[(int)date('j',strtotime($r['deadline']))][]=$r;
if(($_GET['download']??'')==='ics'){
    header('Content-Type:text/calendar; charset=utf-8');header('Content-Disposition:attachment; filename="scholarmatch-deadlines.ics"');
    echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//ScholarMatch//EN\r\n";
    foreach($rows as $r){$d=str_replace('-','',$r['deadline']);$summary=str_replace(["\r","\n",',',';'],[' ',' ','\\,','\\;'],$r['name']);echo "BEGIN:VEVENT\r\nUID:scholarmatch-".(int)$r['id']."-".$d."@local\r\nDTSTART;VALUE=DATE:$d\r\nSUMMARY:$summary\r\nEND:VEVENT\r\n";}
    echo "END:VCALENDAR\r\n";exit;
}
$prev=date('Y-m',strtotime($start.' -1 month'));$next=date('Y-m',strtotime($start.' +1 month'));
$days=(int)date('t',strtotime($start));$offset=(int)date('N',strtotime($start))-1;
sm_v2_head('Deadline calendar');sm_v2_begin('PLAN AHEAD','Scholarship deadlines','Upcoming deadlines based on your current scholarship catalogue. Verify dates with the provider.');
?>
<div class="v2-actions v2-between"><a class="v2-btn secondary" href="?month=<?= sm_h($prev) ?>">← Previous</a><h2><?= sm_h(date('F Y',strtotime($start))) ?></h2><a class="v2-btn secondary" href="?month=<?= sm_h($next) ?>">Next →</a><a class="v2-btn" href="?month=<?= sm_h($month) ?>&download=ics">Export .ics</a></div>
<div class="v2-calendar"><?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?><div class="v2-weekday"><?= $d ?></div><?php endforeach; ?>
<?php for($i=0;$i<$offset;$i++): ?><div class="v2-date blank"></div><?php endfor; ?>
<?php for($d=1;$d<=$days;$d++): ?><div class="v2-date"><strong><?= $d ?></strong><?php foreach(($byDay[$d]??[]) as $r): ?><a href="scholarship_details.php?id=<?= (int)$r['id'] ?>" title="<?= sm_h($r['name']) ?>"><?= sm_h($r['name']) ?></a><?php endforeach; ?></div><?php endfor; ?></div>
<p class="v2-muted">Calendar exports only the selected month's entries. Time zones and closing hours are not specified.</p></main><?php sm_v2_footer(); ?>
