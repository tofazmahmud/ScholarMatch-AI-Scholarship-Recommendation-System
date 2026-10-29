<?php
function sm_document_chunks(string $text, int $chunkSize=1200, int $overlap=130): array {
  $text=trim(preg_replace('/[ \t]+/u',' ',$text)??$text);
  // Bound stored source text; nothing is added to the public file system.
  if(function_exists('mb_strcut'))$text=mb_strcut($text,0,160000,'UTF-8');
  else $text=substr($text,0,160000);
  $len=function_exists('mb_strlen')?mb_strlen($text,'UTF-8'):strlen($text);
  $parts=[];
  for($pos=0;$pos<$len && count($parts)<175;$pos+=($chunkSize-$overlap)){
    $chunk=function_exists('mb_substr')?mb_substr($text,$pos,$chunkSize,'UTF-8'):substr($text,$pos,$chunkSize);
    if(trim($chunk)!=='')$parts[]=trim($chunk);
  }
  return $parts;
}
function sm_document_evidence(PDO $pdo,int $scholarshipId,array $profile,int $limit=4): array {
  $s=$pdo->prepare('SELECT c.id,c.sequence_no,c.chunk_text,d.id AS document_id,d.title,d.source_url,d.is_verified FROM scholarship_chunk c JOIN scholarship_document d ON d.id=c.document_id WHERE d.scholarship_id=? ORDER BY d.id,c.sequence_no LIMIT 250');
  $s->execute([$scholarshipId]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
  $terms=preg_split('/[^\pL\pN]+/u',strtolower((string)($profile['field']??'').' '.(string)($profile['interests']??'').' '.(string)($profile['skills']??'')),-1,PREG_SPLIT_NO_EMPTY);
  $terms=array_values(array_unique(array_filter($terms,fn($v)=>strlen($v)>3)));
  foreach($rows as &$r){$score=0;$content=strtolower((string)$r['chunk_text']);foreach($terms as $w)if(str_contains($content,$w))$score++;$r['_score']=$score;}
  unset($r);
  usort($rows,fn($a,$b)=>($b['_score']<=>$a['_score'])?:($a['id']<=>$b['id']));
  return array_slice($rows,0,$limit);
}
