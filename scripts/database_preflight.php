<?php
// Read-only preflight. Run with C:\xampp\php\php.exe scripts\database_preflight.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../config/db.php';
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
echo "\nScholarMatch Database Preflight — READ-ONLY\n";
echo "Connected database: {$database}\n";
if ($database !== 'scholarmatch') {echo "STOP: expected database 'scholarmatch'.\n";exit(2);}
$base=['admin','profile','preference','scholarship','requirement','recommendation'];
$v2=['bookmark','application_tracker','recommendation_state','scholarship_embedding','scholarship_document','scholarship_chunk'];
$v3=['admin_access','admin_audit_log','admin_site_setting'];
$broken=[];$missing=[];$good=[];
foreach (array_merge($base,$v2,$v3) as $t) {
    try {
        $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $q->execute([$t]);
        if (!$q->fetchColumn()) { $missing[]=$t; echo "MISSING: {$t}\n"; continue; }
        $pdo->query('SELECT 1 FROM `'.$t.'` LIMIT 1');
        $good[]=$t; echo "OK: {$t}\n";
    } catch (Throwable $e) {
        $broken[]=$t;
        echo "BROKEN: {$t} — SQLSTATE " . ($e instanceof PDOException ? ($e->getCode()) : 'error') . "\n";
    }
}
foreach (['source_status','source_verified_at'] as $c) {
    try {
        $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $q->execute(['scholarship',$c]); echo (($q->fetchColumn()) ? 'OK' : 'MISSING').": scholarship.{$c}\n";
    }catch(Throwable $e){echo "UNKNOWN: scholarship.{$c}\n";}
}
if ($broken) {
    echo "\nSTOP: BROKEN InnoDB table(s): ".implode(', ',$broken)."\n";
    echo "Do NOT rerun migrations, DROP databases, or delete/rename .ibd files.\n";
    echo "Restore a verified backup or obtain database recovery assistance.\n";
    exit(2);
}
if (array_intersect($base,$missing)) {
    echo "\nSTOP: core tables missing. Do not run V2/V3 migrations on an incomplete original database.\n";
    exit(2);
}
if ($missing) {
    echo "\nSAFE TO CONSIDER ADDITIVE MIGRATIONS after backup. Missing: ".implode(', ',$missing)."\n";
    exit(1);
}
echo "\nPASS: 15 tables can be queried. Check add-on columns above.\n";
exit(0);
