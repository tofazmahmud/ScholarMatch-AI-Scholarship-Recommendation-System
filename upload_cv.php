<?php
require_once __DIR__.'/includes/student_session.php';
require_once __DIR__.'/includes/cv_ai_extractor.php';
require_once __DIR__.'/includes/cv_document_parser.php';
require_once __DIR__.'/includes/cv_rule_extractor.php';
require_once __DIR__.'/includes/v2_layout.php';
$error='';$notice='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  sm_v2_post_guard();
  if(!empty($_SESSION['sm_cv_last_attempt']) && time()-(int)$_SESSION['sm_cv_last_attempt']<15)throw new RuntimeException('Please wait briefly before the next upload.');
  $mode=(string)($_POST['mode']??'auto');if(!in_array($mode,['manual','ai','auto'],true))throw new RuntimeException('Invalid extraction mode.');
  if($mode!=='manual' && ($_POST['consent']??'')!=='yes')throw new RuntimeException('Consent is required to send CV text to Gemini.');
  $file=$_FILES['cv']??null;
  if(!is_array($file) || is_array($file['error']??null) || ($file['error']??null)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']))throw new RuntimeException('Select a valid CV file.');
  if($file['size']<=0 || $file['size']>4*1024*1024)throw new RuntimeException('CV must be at most 4 MB.');
  $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));if(!in_array($ext,['pdf','docx'],true))throw new RuntimeException('Only PDF and DOCX supported.');
  $_SESSION['sm_cv_last_attempt']=time();
  $text=sm_cv_read_document($file['tmp_name'],(string)$file['name']);
  if(strlen($text)<50)throw new RuntimeException('Not enough extractable text. Scanned PDF requires OCR, which is not included.');
  $draft=null;$used='Manual PHP';
  if($mode!=='manual'){
   try{$draft=sm_gemini_extract_cv($text);$used='Gemini AI';}
   
catch (Throwable $e) {
    error_log('CV Gemini diagnostic: ' . $e->getMessage());

    if ($mode === 'ai') {
        throw new RuntimeException(
            'Gemini extraction failed: ' . $e->getMessage()
        );
    }

    $used = 'Manual PHP fallback';
}

  }
  if($draft===null)$draft=sm_cv_local_extract($text);
  if(count(array_filter($draft,fn($v)=>trim((string)$v)!==''))<1)throw new RuntimeException('No reliable academic fields found. Enter your profile manually.');
  $_SESSION['sm_cv_draft']=['created_at'=>time(),'data'=>$draft,'method'=>$used];
  header('Location: profile.php?cv_import=1');exit;
 }catch(Throwable $e){error_log('CV upload: '.$e->getMessage());$error=$e instanceof RuntimeException?$e->getMessage():'CV processing failed. Please try again.';}
}
sm_v2_head('CV import');sm_v2_begin('SMART PROFILE','Import your academic CV','PDF and DOCX support with Gemini AI or an offline rule-based extractor.');
?>
<div class="v2-two"><section class="v2-card"><h2>Choose a CV</h2><?php if($error): ?><div class="v2-alert" role="alert"><?= sm_h($error) ?></div><?php endif; ?><form method="POST" enctype="multipart/form-data" class="v2-stack"><input type="hidden" name="csrf_token" value="<?= sm_h(sm_student_csrf()) ?>"><label for="cv">CV file (PDF or DOCX, max 4 MB)</label><input type="file" name="cv" id="cv" accept=".pdf,.docx" required><label for="mode">Extraction mode</label><select name="mode" id="mode"><option value="auto">Auto: Gemini → manual fallback</option><option value="manual">Manual PHP only (no AI API)</option><option value="ai">Gemini only</option></select><label><input type="checkbox" name="consent" value="yes"> I agree to sending CV text to Gemini if I select Auto or Gemini mode.</label><button class="v2-btn">Extract CV information</button></form></section><section class="v2-card"><h2>How your privacy is handled</h2><p>Your uploaded file is parsed from PHP temporary storage and not permanently saved. An extracted draft lives in your session for 15 minutes, until you review and save it.</p><p>Manual mode never calls an external AI provider. Automatic mode may call Gemini and should only be used with consent.</p><p class="v2-muted">Rule-based extraction is conservative: it only recognizes clear labelled values, and may leave fields blank. Scanned PDFs need OCR and cannot be read here.</p><a href="profile.php">Enter profile manually →</a></section></div></main><?php sm_v2_footer(); ?>
