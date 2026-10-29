<?php
require_once __DIR__ . '/student_session.php';
require_once __DIR__ . '/student_helpers.php';
require_once __DIR__ . '/matching_engine.php';

function sm_v2_profile(PDO $pdo): ?array {
    $stmt = $pdo->prepare('SELECT * FROM profile WHERE session_id=? LIMIT 1');
    $stmt->execute([$_SESSION['sm_student_token']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
function sm_v2_profile_id(PDO $pdo): int {
    $p = sm_v2_profile($pdo);
    return $p ? (int)$p['id'] : 0;
}
function sm_v2_tables_ready(PDO $pdo): bool {
    static $ready = null;
    if ($ready === null) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('bookmark','application_tracker','recommendation_state','scholarship_embedding')");
        $ready = (int)$stmt->fetchColumn() === 4;
    }
    return $ready;
}
function sm_v2_require_install(PDO $pdo): void {
    if (!sm_v2_tables_ready($pdo)) {
        http_response_code(503);
        exit('ScholarMatch V2 setup needed: import database/01_V2_SAFE_EXISTING.sql (after preflight) in phpMyAdmin. Existing data will be preserved.');
    }
}
function sm_v2_snapshot(PDO $pdo, int $profileId): string {
    $p = $pdo->prepare('SELECT education,degree,field,gpa,skills,interests,experience,publications,projects FROM profile WHERE id=?');
    $p->execute([$profileId]);
    $pref = $pdo->prepare('SELECT degree_level,field,country,funding_type,deadline_from,deadline_to FROM preference WHERE profile_id=?');
    $pref->execute([$profileId]);
    $ss = $pdo->query('SELECT id,name,provider,country,degree_level,field,funding_type,deadline,description_text,is_active FROM scholarship ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $rs = $pdo->query('SELECT scholarship_id,requirement_type,comparison_operator,value,certainty FROM requirement ORDER BY scholarship_id,id')->fetchAll(PDO::FETCH_ASSOC);
    return hash('sha256', json_encode([$p->fetch(PDO::FETCH_ASSOC),$pref->fetch(PDO::FETCH_ASSOC),$ss,$rs],JSON_INVALID_UTF8_SUBSTITUTE));
}
function sm_v2_record_matches(PDO $pdo, int $profileId): void {
    if (!sm_v2_tables_ready($pdo)) return;
    $hash = sm_v2_snapshot($pdo,$profileId);
    $stmt = $pdo->prepare('INSERT INTO recommendation_state (profile_id,source_hash) VALUES (?,?) ON DUPLICATE KEY UPDATE source_hash=VALUES(source_hash), generated_at=CURRENT_TIMESTAMP');
    $stmt->execute([$profileId,$hash]);
}
function sm_v2_matches_stale(PDO $pdo, int $profileId): bool {
    if (!sm_v2_tables_ready($pdo)) return false;
    $stmt = $pdo->prepare('SELECT source_hash FROM recommendation_state WHERE profile_id=?');
    $stmt->execute([$profileId]);
    $hash = $stmt->fetchColumn();
    return !$hash || !hash_equals((string)$hash, sm_v2_snapshot($pdo,$profileId));
}
// Real InnoDB access check; information_schema alone cannot detect orphaned .ibd issues.
function sm_v2_table_healthy(PDO $pdo, string $name): bool {
    if (!in_array($name, ['bookmark','application_tracker','recommendation_state',
                          'scholarship_embedding','scholarship_document','scholarship_chunk'], true)) return false;
    try { $pdo->query('SELECT 1 FROM `'.$name.'` LIMIT 1'); return true; }
    catch (Throwable $e) { error_log('ScholarMatch table unavailable: '.$name.' - '.$e->getMessage()); return false; }
}
function sm_v2_profile_percent(?array $p): int {
    if (!$p) return 0;
    $keys=['education','degree','field','gpa','skills','interests','projects','experience'];
    $count=0;
    foreach($keys as $k) if(trim((string)($p[$k]??''))!=='') $count++;
    return (int)round($count/count($keys)*100);
}
function sm_v2_post_guard(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !sm_verify_student_csrf($_POST['csrf_token']??null)) {
        http_response_code(403); exit('Invalid request.');
    }
}
function sm_v2_url(string $file,array $params=[]): string { return $file . ($params ? '?'.http_build_query($params) : ''); }
function sm_v2_badge(string $status): string {
    $cls=['Eligible'=>'eligible','Not Eligible'=>'ineligible','Potentially Eligible'=>'potential','Unknown/Manual Check'=>'unknown'];
    return '<span class="v2-pill '.($cls[$status]??'unknown').'">'.sm_h($status).'</span>';
}
