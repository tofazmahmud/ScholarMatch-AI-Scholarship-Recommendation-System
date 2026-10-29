<?php
require_once __DIR__.'/../includes/admin_layout.php';require_admin();require_admin_super();require_once __DIR__.'/../config/db.php';
$error='';$success='';$me=(int)$_SESSION['admin_id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!valid_csrf((string)($_POST['csrf_token']??''))){http_response_code(403);exit('Invalid token');}
 $action=(string)($_POST['action']??'');
 try {
 if($action==='create'){
  $username=trim((string)($_POST['username']??''));$pwd=(string)($_POST['password']??'');$role=(string)($_POST['role']??'viewer');
  if(!preg_match('/^[A-Za-z0-9_.-]{3,50}$/',$username)||strlen($pwd)<12||strlen($pwd)>200||!in_array($role,['superadmin','editor','viewer'],true))throw new RuntimeException('Choose a 3–50 character username, a 12+ character password and a valid role.');
  $pdo->beginTransaction();try{$q=$pdo->prepare('INSERT INTO admin(username,password_hash) VALUES (?,?)');$q->execute([$username,password_hash($pwd,PASSWORD_DEFAULT)]);$nid=(int)$pdo->lastInsertId();$q=$pdo->prepare('INSERT INTO admin_access(admin_id,role,is_active) VALUES(?,?,1)');$q->execute([$nid,$role]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  admin_log($pdo,'staff.create','admin',$nid,'Role: '.$role);$success='Staff account created.';
 } elseif($action==='change'){
  $target=filter_var($_POST['target_id']??null,FILTER_VALIDATE_INT);$role=(string)($_POST['role']??'');$active=(string)($_POST['active']??'');
  if(!$target||!in_array($role,['superadmin','editor','viewer'],true)||!in_array($active,['0','1'],true))throw new RuntimeException('Invalid staff change.');
  if($target===$me)throw new RuntimeException('You cannot change your own role or active status here.');
  $q=$pdo->prepare('SELECT role,is_active FROM admin_access WHERE admin_id=?');$q->execute([$target]);$old=$q->fetch();if(!$old)throw new RuntimeException('Staff account not found.');
  if($old['role']==='superadmin'&&($role!=='superadmin'||$active==='0')){
    $remaining=(int)$pdo->query("SELECT COUNT(*) FROM admin_access WHERE role='superadmin' AND is_active=1")->fetchColumn();
    if($remaining<=1)throw new RuntimeException('Cannot deactivate or demote the last active Super Admin.');
  }
  $q=$pdo->prepare('UPDATE admin_access SET role=?,is_active=? WHERE admin_id=?');$q->execute([$role,(int)$active,$target]);admin_log($pdo,'staff.update','admin',$target,'Role: '.$role.'; active: '.$active);$success='Staff permissions updated. Changes apply from the next request.';
 }else throw new RuntimeException('Unknown action.');
 }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Could not save staff changes (possibly duplicate username).';error_log('Staff admin: '.$e->getMessage());}
}
$staff=$pdo->query('SELECT a.id,a.username,x.role,x.is_active,x.display_name,x.last_login_at FROM admin a JOIN admin_access x ON x.admin_id=a.id ORDER BY a.id')->fetchAll();
sm_admin_header('Staff & Access Roles','staff');
?>
<div class="admin-v3-note"><strong>Permissions:</strong> Super Admin: staff, settings and all catalogue functions. Editor: catalogue updates, documents and imports. Viewer: read-only. Never share a single admin login among staff.</div>
<?php if($error):?><div class="admin-v3-danger" role="alert"><?=adm_h($error)?></div><?php endif;?><?php if($success):?><div class="admin-v3-success"><?=adm_h($success)?></div><?php endif;?>
<section class="v2-card"><h2>Create staff account</h2><form method="post" class="admin-v3-form-row"><input type="hidden" name="csrf_token" value="<?=adm_h(csrf_token())?>"><input type="hidden" name="action" value="create"><label>Username<input required name="username" maxlength="50" pattern="[A-Za-z0-9_.-]{3,50}" autocomplete="off"></label><label>Initial password (12+ chars)<input required type="password" minlength="12" maxlength="200" name="password" autocomplete="new-password"></label><label>Role<select name="role"><option value="viewer">Viewer</option><option value="editor">Editor</option><option value="superadmin">Super Admin</option></select></label><div class="wide"><button class="v2-btn">Create account</button></div></form></section>
<section class="v2-card"><h2>Existing staff</h2><div class="admin-v3-table-wrap"><table class="admin-v3-table"><thead><tr><th>Username</th><th>Last login</th><th>Role & status</th><th>Action</th></tr></thead><tbody>
<?php foreach($staff as $s):?><tr><td><strong><?=adm_h($s['username'])?></strong><?=($s['id']==$me)?' (You)':''?></td><td><?=adm_h($s['last_login_at']??'Never')?></td><td><span class="admin-v3-badge"><?=adm_h($s['role'])?> · <?=((int)$s['is_active']?'Active':'Disabled')?></span></td><td><?php if((int)$s['id']!==$me):?><form method="post" class="admin-v3-inline"><input type="hidden" name="csrf_token" value="<?=adm_h(csrf_token())?>"><input type="hidden" name="action" value="change"><input type="hidden" name="target_id" value="<?=(int)$s['id']?>"><select name="role"><?php foreach(['viewer','editor','superadmin'] as $v):?><option value="<?=$v?>" <?=$s['role']===$v?'selected':''?>><?=ucfirst($v)?></option><?php endforeach;?></select><select name="active"><option value="1" <?=((int)$s['is_active']===1?'selected':'')?>>Active</option><option value="0" <?=((int)$s['is_active']===0?'selected':'')?>>Disabled</option></select><button class="v2-btn secondary">Save</button></form><?php else:?>Change your own password in My Account.<?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section>
<?php sm_admin_footer();
