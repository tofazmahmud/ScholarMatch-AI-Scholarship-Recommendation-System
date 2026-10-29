<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}
require_once __DIR__.'/../includes/semantic.php';
if(!in_array('--build', $argv,true)){echo "Creates/updates embeddings using Gemini API. API usage may have quotas/charges.\nUsage: php scripts/index_embeddings.php --build\n";exit;}
sm_v2_require_install($pdo);
$rows=$pdo->query('SELECT id,name,provider,country,degree_level,field,funding_type,description_text FROM scholarship WHERE is_active=1 ORDER BY id LIMIT 500')->fetchAll();
$check=$pdo->prepare('SELECT content_hash FROM scholarship_embedding WHERE scholarship_id=?');
$save=$pdo->prepare('INSERT INTO scholarship_embedding(scholarship_id,model_name,content_hash,dimensions,vector_json) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE model_name=VALUES(model_name),content_hash=VALUES(content_hash),dimensions=VALUES(dimensions),vector_json=VALUES(vector_json),updated_at=CURRENT_TIMESTAMP');
$done=0;$skipped=0;$failed=0;
foreach($rows as $r){$doc=sm_semantic_document($r);$hash=hash('sha256',$doc);$check->execute([(int)$r['id']]);if($check->fetchColumn()===$hash){$skipped++;continue;}
 try{$vec=sm_semantic_embedding($doc,'RETRIEVAL_DOCUMENT');$save->execute([(int)$r['id'],'gemini-embedding-001',$hash,count($vec),json_encode($vec,JSON_THROW_ON_ERROR)]);$done++;}
 catch(Throwable $e){$failed++;fwrite(STDERR,'Embedding failed for scholarship ID '.(int)$r['id'].'; '.$e->getMessage()."\n");if($failed>=3)break;}
}
echo "Index complete. New/updated: $done; unchanged: $skipped; failed: $failed\n";
