<?php
// Initial administrator setup. CLI only. Prefer Staff & Roles for later staff.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require_once __DIR__.'/../config/db.php';
if(!$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='admin_access'")->fetchColumn()){
 exit("Run database/02_ADMIN_V3_SAFE_EXISTING.sql first.\n");
}
echo "=== ScholarMatch V3 Admin Setup ===\n";
echo "Username: ";$username=trim((string)fgets(STDIN));
echo "Password (min 12 characters; visible while typing): ";$password=rtrim((string)fgets(STDIN),"\r\n");
if(!preg_match('/^[A-Za-z0-9_.-]{3,50}$/',$username) || strlen($password)<12 || strlen($password)>200)exit("Invalid username or password length.\n");
$q=$pdo->prepare('SELECT id FROM admin WHERE username=?');$q->execute([$username]);if($q->fetchColumn())exit("Username already exists.\n");
$existing=(int)$pdo->query('SELECT COUNT(*) FROM admin_access WHERE role=\'superadmin\' AND is_active=1')->fetchColumn();
$role=$existing?'viewer':'superadmin';
$pdo->beginTransaction();try{
 $q=$pdo->prepare('INSERT INTO admin(username,password_hash) VALUES(?,?)');$q->execute([$username,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)$pdo->lastInsertId();
 $q=$pdo->prepare('INSERT INTO admin_access(admin_id,role,is_active) VALUES(?,?,1)');$q->execute([$id,$role]);$pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());exit("Could not create administrator.\n");}
echo "Account created as $role. Existing Super Admins can assign further roles in Staff & Roles.\n";
