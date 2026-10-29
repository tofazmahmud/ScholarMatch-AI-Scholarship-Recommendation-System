<?php
require_once __DIR__.'/../includes/admin_layout.php';require_admin();require_once __DIR__.'/../config/db.php';
$id=(int)$_SESSION['admin_id'];$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!valid_csrf((string)($_POST['csrf_token']??''))){http_response_code(403);exit('Invalid security token');}
 $action=(string)($_POST['action']??'');$current=(string)($_POST['current_password']??'');
 $s=$pdo->prepare('SELECT password_hash FROM admin WHERE id=?');$s->execute([$id]);$hash=$s->fetchColumn();
 if(!$hash || !password_verify($current,(string)$hash)){$error='Current password does not match.';}
 elseif($action==='details'){
   $display=trim((string)($_POST['display_name']??''));$email=trim((string)($_POST['email']??''));
   if(strlen($display)>120 || ($email!=='' && (strlen($email)>254 || !filter_var($email,FILTER_VALIDATE_EMAIL))))$error='Invalid display name or email.';
   else{$q=$pdo->prepare('UPDATE admin_access SET display_name=?,email=? WHERE admin_id=?');$q->execute([$display?:null,$email?:null,$id]);admin_log($pdo,'account.details','admin',$id);$success='Your account details have been updated.';}
 }elseif($action==='password'){
   $new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');
   if(strlen($new)<12 || strlen($new)>200 || !hash_equals($new,$confirm))$error='New password must contain 12–200 characters and both copies must match.';
   elseif(hash_equals($current,$new))$error='Choose a new password different from the current one.';
   else{$q=$pdo->prepare('UPDATE admin SET password_hash=? WHERE id=?');$q->execute([password_hash($new,PASSWORD_DEFAULT),$id]);session_regenerate_id(true);admin_log($pdo,'account.password','admin',$id);$success='Password changed successfully.';}
 }else{$error='Unknown action.';}
}
$a=admin_access($pdo,$id);sm_admin_header('My Admin Account','account');
?>
<p class="admin-v3-muted">Update your administrator profile and password. Password verification is required for both actions.</p>
<?php if($error):?><div class="admin-v3-danger" role="alert"><?=adm_h($error)?></div><?php endif;?>
<?php if($success):?><div class="admin-v3-success" role="status"><?=adm_h($success)?></div><?php endif;?>
<div class="v2-two"><section class="v2-card"><h2>Account information</h2><p><b>Username:</b> <?=adm_h($a['username'])?></p><p><b>Role:</b> <?=adm_h($a['role'])?></p><p><b>Last login:</b> <?=adm_h($a['last_login_at']??'Not tracked')?></p>
<form method="post" class="v2-stack"><input type="hidden" name="csrf_token" value="<?=adm_h(csrf_token())?>"><input type="hidden" name="action" value="details">
<label>Display name <input name="display_name" maxlength="120" value="<?=adm_h($a['display_name'])?>"></label>
<label>Email (optional) <input type="email" maxlength="254" name="email" value="<?=adm_h($a['email'])?>"></label>
<label>Current password <input type="password" name="current_password" autocomplete="current-password" required></label><button class="v2-btn">Update Profile</button></form></section>
<section class="v2-card"><h2>Change password</h2><p class="admin-v3-muted">Use a unique password (minimum 12 characters). Changing your password regenerates this session ID.</p>
<form method="post" class="v2-stack"><input type="hidden" name="csrf_token" value="<?=adm_h(csrf_token())?>"><input type="hidden" name="action" value="password"><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><label>New password<input type="password" name="new_password" minlength="12" maxlength="200" autocomplete="new-password" required></label><label>Confirm new password<input type="password" name="confirm_password" minlength="12" maxlength="200" autocomplete="new-password" required></label><button class="v2-btn">Change Password</button></form></section></div>
<?php sm_admin_footer();
