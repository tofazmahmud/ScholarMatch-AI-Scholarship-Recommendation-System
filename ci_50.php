<?php
/**
 * ScholarMatch 50-case CI evidence suite.
 * Cases combine executable PHP logic checks with source/schema contract checks.
 * Source checks prove implementation markers exist; they are NOT browser or DB E2E tests.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$root = dirname(__DIR__);
require_once $root . '/includes/manual_explainer.php';
require_once $root . '/includes/cv_rule_extractor.php';
$results = [];
function sm_case(array &$results, string $id, string $title, bool $ok, string $method): void {
    $results[] = ['id'=>$id,'test_case'=>$title,'status'=>$ok?'PASS':'FAIL','method'=>$method];
    echo ($ok?'PASS':'FAIL') . " {$id} - {$title}\n";
}
function sm_src(string $relative): string {
    global $root;
    $p = $root . '/' . $relative;
    return is_file($p) ? (string)file_get_contents($p) : '';
}
function sm_has(string $relative, string $needle): bool { return $needle !== '' && str_contains(sm_src($relative), $needle); }
function sm_re(string $relative, string $pattern): bool { return (bool)preg_match($pattern, sm_src($relative)); }
function sm_eval($gpa): string {
    $e=['student'=>['degree'=>'Bachelor','field'=>'Computer Science','gpa'=>$gpa], 'scholarship'=>['name'=>'CI fixture'], 'requirements'=>[
      ['id'=>1,'type'=>'gpa','operator'=>'>=','value'=>'3.50','certainty'=>'explicit'],
      ['id'=>2,'type'=>'degree','operator'=>'=','value'=>'Bachelor','certainty'=>'explicit'],
      ['id'=>3,'type'=>'field','operator'=>'IN','value'=>'Computer Science, Software Engineering','certainty'=>'explicit']]];
    return sm_manual_status(sm_manual_breakdown($e));
}
// TC-01..08: executable eligibility and CV parser behavior.
$doc="Education: BSc in CSE\nCompleted Degree: Bachelor\nField: Computer Science\nCGPA: 3.90 out of 4.00\nSkills: PHP, Python";
$f=sm_cv_local_extract($doc);
sm_case($results,'TC-01','GPA 3.90 meets explicit 3.50 threshold',sm_eval('3.90')==='Eligible','PHP logic');
sm_case($results,'TC-02','GPA 3.20 fails explicit 3.50 threshold',sm_eval('3.20')==='Not Eligible','PHP logic');
sm_case($results,'TC-03','Missing GPA returns Unknown/Manual Check',sm_eval(null)==='Unknown/Manual Check','PHP logic');
$e=['student'=>['degree'=>'Bachelor','field'=>'Computer Science','gpa'=>'3.2'],'scholarship'=>['name'=>'CI fixture'],'requirements'=>[['id'=>5,'type'=>'gpa','operator'=>'>=','value'=>'3.5','certainty'=>'explicit']]];
sm_case($results,'TC-04','Explanation identifies failed requirement rule #5',str_contains(sm_manual_explanation($e,sm_manual_breakdown($e)),'does not meet rule #5'),'PHP logic');
sm_case($results,'TC-05','CV parser extracts explicit GPA',($f['gpa']??'')==='3.90','PHP logic');
sm_case($results,'TC-06','CV parser extracts Bachelor degree',($f['degree']??'')==='Bachelor','PHP logic');
sm_case($results,'TC-07','CV parser extracts Computer Science field',($f['field']??'')==='Computer Science','PHP logic');
$noScale=sm_cv_local_extract('Student achieved CGPA 3.90');
sm_case($results,'TC-08','CV parser does not infer GPA without scale',($noScale['gpa']??'')==='','PHP logic');
// TC-09..50: repository implementation/source/schema contract checks.
$checks = [
['TC-09','Admin helper functions are defined','includes/admin_auth.php','function adm_h'],
['TC-10','Admin CSRF token generation exists','includes/admin_auth.php','function csrf_token'],
['TC-11','Admin CSRF validation exists','includes/admin_auth.php','function valid_csrf'],
['TC-12','Admin role access helper exists','includes/admin_auth.php','function admin_can_edit'],
['TC-13','Admin superuser guard exists','includes/admin_auth.php','function require_admin_super'],
['TC-14','Admin audit logging helper exists','includes/admin_auth.php','function admin_log'],
['TC-15','Scholarship catalogue page exists','explore.php','SELECT'],
['TC-16','Scholarship details page exists','scholarship_details.php','scholarship'],
['TC-17','Scholarship detail page exposes provider application URL','scholarship_details.php','official_url'],
['TC-18','Student profile page exists','profile.php','profile'],
['TC-19','Profile update uses parameterized SQL','profile.php','->prepare('],
['TC-20','Preferences page exists','preferences.php','preference'],
['TC-21','Preferences save uses parameterized SQL','preferences.php','->prepare('],
['TC-22','Matching engine module exists','includes/matching_engine.php','function'],
['TC-23','Matching engine loads requirement records','includes/matching_engine.php','requirement'],
['TC-24','Recommendation page exists','recommendations.php','recommendation'],
['TC-25','Manual eligibility has Unknown/Manual Check state','includes/manual_explainer.php','Unknown/Manual Check'],
['TC-26','Eligibility explanation warns against official admission decision','includes/manual_explainer.php','not an official admission decision'],
['TC-27','AI client has non-200 response handling','includes/ai_client.php','$httpCode !== 200'],
['TC-28','RAG generator module exists','includes/rag_generator.php','function'],
['TC-29','RAG retriever module exists','includes/rag_retriever.php','function'],
['TC-30','Admin login verifies password hash','admin/login.php','password_verify('],
['TC-31','Admin login rotates session ID','admin/login.php','session_regenerate_id(true)'],
['TC-32','Admin login validates CSRF','admin/login.php','valid_csrf('],
['TC-33','Admin dashboard requires authenticated admin','admin/dashboard.php','require_admin()'],
['TC-34','Scholarship admin mutation validates CSRF','admin/scholarships.php','valid_csrf('],
['TC-35','Scholarship admin CRUD uses prepared statements','admin/scholarships.php','->prepare('],
['TC-36','Requirement admin page validates CSRF','admin/requirements.php','valid_csrf('],
['TC-37','Requirement admin CRUD uses prepared statements','admin/requirements.php','->prepare('],
['TC-38','Staff creation hashes passwords','admin/staff.php','password_hash('],
['TC-39','Staff roles are constrained to supported values','admin/staff.php',"['superadmin','editor','viewer']"],
['TC-40','Admin account page verifies current password','admin/account.php','password_verify('],
['TC-41','Admin account page hashes new password','admin/account.php','password_hash('],
['TC-42','Admin audit page reads audit log table','admin/audit.php','admin_audit_log'],
['TC-43','Admin announcement settings are escaped on output','admin/settings.php','adm_h('],
['TC-44','CSV import enforces 200-record cap','admin/import.php','Maximum 200 records per import'],
['TC-45','CSV import checks duplicate scholarship entries','admin/import.php','SELECT id FROM scholarship WHERE name = ? AND provider = ?'],
['TC-46','Document chunking module exists','includes/document_chunks.php','function sm_document_chunks'],
['TC-47','Document chunks are retrieved through scholarship relationship','includes/document_chunks.php','JOIN scholarship_document'],
['TC-48','Bookmark add avoids duplicate active rows','bookmarks.php','INSERT IGNORE INTO bookmark'],
['TC-49','Bookmark removal operation exists','bookmarks.php',"'DELETE FROM bookmark WHERE profile_id=? AND scholarship_id=?'"],
['TC-50','Application tracker validates allowed status values','application_tracker.php','in_array($status,$statuses,true)']
];
foreach ($checks as [$id,$title,$file,$needle]) sm_case($results,$id,$title,sm_has($file,$needle),'Source contract');
// Write machine-readable and human-readable evidence in the CI workspace.
$outDir = $root . '/ci-results';
if (!is_dir($outDir)) mkdir($outDir, 0777, true);
$csv = fopen($outDir.'/50-test-results.csv','w');
fputcsv($csv,['Test ID','Test case','Status','Verification method']);
foreach ($results as $r) fputcsv($csv,[$r['id'],$r['test_case'],$r['status'],$r['method']]);
fclose($csv);
$passed=count(array_filter($results,fn($r)=>$r['status']==='PASS'));
$failed=count($results)-$passed;
$md = "# ScholarMatch – 50-Case CI Evidence\n\n";
$md .= "- Executed: **".count($results)."**\n- Passed: **{$passed}**\n- Failed: **{$failed}**\n\n";
$md .= "> Scope: TC-01–TC-08 are executable PHP logic checks. TC-09–TC-50 are source-contract checks. Source-contract checks confirm implementation markers in repository files; they do not prove database persistence, HTTP behavior, browser interaction, accessibility, or performance.\n\n";
$md .= "| ID | Test case | Result | Method |\n|---|---|---|---|\n";
foreach ($results as $r) $md .= '| '.$r['id'].' | '.str_replace('|','\\|',$r['test_case']).' | '.$r['status'].' | '.$r['method']." |\n";
file_put_contents($outDir.'/50-test-report.md',$md);
echo "\n50-CASE SUMMARY: {$passed} PASS, {$failed} FAIL\n";
exit($failed ? 1 : 0);
