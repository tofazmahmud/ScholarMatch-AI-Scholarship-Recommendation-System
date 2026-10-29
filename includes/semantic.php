<?php
require_once __DIR__.'/v2.php';
function sm_semantic_document(array $s): string {
 return implode("\n",array_map(fn($k)=>(string)($s[$k]??''),['name','provider','country','degree_level','field','funding_type','description_text']));
}
function sm_semantic_embedding(string $text,string $task='RETRIEVAL_QUERY'): array {
 $path=dirname(__DIR__,3).'/scholarmatch_private/gemini_config.php';
 if(!is_file($path)) throw new RuntimeException('Gemini is not configured.');
 $conf=require $path;
 $key=(string)($conf['api_key']??'');
 if(!$key || str_contains($key,'PASTE_') || str_contains($key,'YOUR_')) throw new RuntimeException('Gemini API key missing.');
 $model='gemini-embedding-001';
 $url='https://generativelanguage.googleapis.com/v1beta/models/'.$model.':embedContent';
 $payload=['model'=>'models/'.$model,'content'=>['parts'=>[['text'=>substr($text,0,6000)]]], 'taskType'=>$task,'outputDimensionality'=>768];
 $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-goog-api-key: '.$key],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>40,CURLOPT_CONNECTTIMEOUT=>10]);
 $reply=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if(!is_string($reply) || $code!==200) throw new RuntimeException('Embedding API unavailable (HTTP '.$code.').');
 $body=json_decode($reply,true);$values=$body['embedding']['values']??null;
 if(!is_array($values) || count($values)!==768) throw new RuntimeException('Unexpected embedding dimension.');
 return array_map('floatval',$values);
}
function sm_semantic_cosine(array $a,array $b): float {
 if(count($a)!==count($b) || !$a)return 0.;$dot=0.;$aa=0.;$bb=0.;
 foreach($a as $i=>$x){$y=(float)$b[$i];$x=(float)$x;$dot+=$x*$y;$aa+=$x*$x;$bb+=$y*$y;}
 return $aa>0 && $bb>0?$dot/sqrt($aa*$bb):0.;
}
function sm_semantic_lexical(string $query,string $doc): float {
 $q=preg_split('/[^a-z0-9]+/i',strtolower($query),-1,PREG_SPLIT_NO_EMPTY);
 $tokens=array_values(array_unique(array_filter($q,fn($w)=>strlen($w)>2 || in_array($w,['ai','ml','cs','it'],true))));
 if(!$tokens)return 0.;$doc=strtolower($doc);$found=0;
 foreach($tokens as $word) if(str_contains($doc,$word))$found++;
 return $found/count($tokens);
}
