<?php
// Conservative fallback: only explicit text patterns, never guess missing values.
function sm_cv_local_extract(string $text): array {
    $out=array_fill_keys(['education','degree','field','gpa','skills','interests','experience','publications','projects'],'');
    $text=substr(str_replace("\r",'',$text),0,25000);
    $patterns=[
      'education'=>'/(?:education|qualification)\s*[:\-]\s*([^\n]{4,180})/i',
      'field'=>'/(?:field(?: of study)?|major|specialization)\s*[:\-]\s*([^\n]{2,130})/i',
      'skills'=>'/(?:technical skills|skills)\s*[:\-]\s*([^\n]{2,600})/i',
      'interests'=>'/(?:research interests|interests)\s*[:\-]\s*([^\n]{2,500})/i',
      'projects'=>'/(?:projects|project)\s*[:\-]\s*([^\n]{2,800})/i',
      'experience'=>'/(?:experience|internship)\s*[:\-]\s*([^\n]{2,800})/i',
      'publications'=>'/(?:publications)\s*[:\-]\s*([^\n]{2,800})/i',
    ];
    foreach($patterns as $k=>$regex) if(preg_match($regex,$text,$m)) $out[$k]=trim($m[1]);
    if(preg_match('/\b(?:CGPA|Cumulative GPA|GPA)\s*[:=\-]?\s*([0-4](?:\.\d{1,2})?)\s*(?:out of|\/|on a scale of)\s*4(?:\.0{1,2})?\b/i',$text,$m)) $out['gpa']=$m[1];
    if(preg_match('/(?:completed degree|degree completed|highest completed degree)\s*[:\-]\s*(Bachelor|Master|PhD|Doctorate)/i',$text,$m)) {
        $out['degree']=strcasecmp($m[1],'Doctorate')===0?'PhD':ucfirst(strtolower($m[1]));
    } elseif(preg_match('/(?:Bachelor(?: of [^\n]{2,100})?|BSc|B\.Sc\.)\s*(?:\([^)]{0,80}\))?\s*(?:completed|graduated)/i',$text)) $out['degree']='Bachelor';
    foreach($out as $k=>$v) $out[$k]=trim(substr($v,0,in_array($k,['education','field','degree'],true)?150:1500));
    return $out;
}
