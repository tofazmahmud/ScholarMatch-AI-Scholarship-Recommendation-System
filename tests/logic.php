<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}
require_once __DIR__.'/../includes/manual_explainer.php';
require_once __DIR__.'/../includes/cv_rule_extractor.php';
$tests=0;$failed=0;
function check(string $label, bool $ok): void {global $tests,$failed;$tests++;if(!$ok)$failed++;echo ($ok?'PASS':'FAIL').' · '.$label."\n";}
function evaluate($gpa): string {
 $e=['student'=>['degree'=>'Bachelor','field'=>'Computer Science','gpa'=>$gpa],
 'scholarship'=>['name'=>'Fictional scholarship'],'requirements'=>[
 ['id'=>1,'type'=>'gpa','operator'=>'>=','value'=>'3.50','certainty'=>'explicit'],
 ['id'=>2,'type'=>'degree','operator'=>'=','value'=>'Bachelor','certainty'=>'explicit'],
 ['id'=>3,'type'=>'field','operator'=>'IN','value'=>'Computer Science, Software Engineering','certainty'=>'explicit']]];
 return sm_manual_status(sm_manual_breakdown($e));
}
check('GPA 3.90 => Eligible',evaluate('3.90')==='Eligible');
check('GPA 3.20 => Not Eligible',evaluate('3.20')==='Not Eligible');
check('Missing GPA => Unknown/Manual Check',evaluate(null)==='Unknown/Manual Check');
$e=['student'=>['degree'=>'Bachelor','field'=>'Computer Science','gpa'=>'3.2'],'scholarship'=>['name'=>'Demo'],'requirements'=>[['id'=>5,'type'=>'gpa','operator'=>'>=','value'=>'3.5','certainty'=>'explicit']]];
$exp=sm_manual_explanation($e,sm_manual_breakdown($e));
check('Manual explanation references failing rule',str_contains($exp,'does not meet rule #5'));
$doc="Education: BSc in CSE\nCompleted Degree: Bachelor\nField: Computer Science\nCGPA: 3.90 out of 4.00\nSkills: PHP, Python";
$fields=sm_cv_local_extract($doc);
check('Manual CV finds explicit GPA',($fields['gpa']??null)==='3.90');
check('Manual CV finds explicit degree',($fields['degree']??null)==='Bachelor');
check('Manual CV finds field',($fields['field']??null)==='Computer Science');
check('Never invent GPA without 4.0 scale',sm_cv_local_extract('Student achieved CGPA 3.90')['gpa']==='');
echo "\n$tests checks, $failed failed\n";exit($failed?1:0);
