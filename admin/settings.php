<?php
require_once __DIR__.'/../includes/admin_layout.php';require_admin();require_admin_super();require_once __DIR__.'/../config/db.php';
$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!valid_csrf((string)($_POST['csrf_token']??''))){http_response_code(403);exit('Invalid token');}
 $announcement=trim((string)($_POST['public_announcement']??''));
 $notice=trim((string)($_POST['catalogue_notice']??''));
 $email=trim((string)($_POST['support_email']??''));
 if(strlen($announcement)>350 || strlen($notice)>350 || ($email!=='' && (!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)))$error='Announcement/notice max 350 characters; use a valid email.';
 else{
  $pdo->beginTransaction();try{$stmt=$pdo->prepare('INSERT INTO admin_site_setting(setting_key,setting_value,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)');
    foreach(['public_announcement'=>$announcement,'catalogue_notice'=>$notice,'support_email'=>$email] as $k=>$v)$stmt->execute([$k,$v,(int)$_SESSION['admin_id']]);$pdo->commit();}
  catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  admin_log($pdo,'settings.update','settings',0,'Updated public messages/contact');$success='Settings saved. Announcement appears on homepage; catalogue notice appears on Explore.';
 }
}
$values=[];foreach(['public_announcement','catalogue_notice','support_email'] as $k)$values[$k]=admin_setting($pdo,$k);
sm_admin_header('Site Settings','settings');
?>
<div class="admin-v3-note">Settings here control the public homepage announcement, Explore catalogue notice, and support contact. No passwords/API secrets should be entered here.</div>
<?php if($error):?><div class="admin-v3-danger"><?=adm_h($error)?></div><?php endif;?><?php if($success):?><div class="admin-v3-success"><?=adm_h($success)?></div><?php endif;?>
<section class="v2-card"><form method="post" class="v2-stack"><input type="hidden" name="csrf_token" value="<?=adm_h(csrf_token())?>"><label>Homepage announcement (optional)<textarea name="public_announcement" maxlength="350" rows="3"><?=adm_h($values['public_announcement'])?></textarea></label><label>Explore catalogue notice (optional)<textarea name="catalogue_notice" maxlength="350" rows="3"><?=adm_h($values['catalogue_notice'])?></textarea></label><label>Support email (optional)<input name="support_email" type="email" maxlength="254" value="<?=adm_h($values['support_email'])?>"></label><div><button class="v2-btn">Save Settings</button></div></form></section>
<?php sm_admin_footer();
