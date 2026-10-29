<?php
require_once __DIR__ . '/matching_engine.php';
function sm_manual_breakdown(array $evidence): array {
    $profile=$evidence['student']??[];
    $result=[];
    foreach(($evidence['requirements']??[]) as $rule) {
        $record=[
            'id'=>(int)($rule['id']??0),
            'requirement_type'=>(string)($rule['type']??''),
            'comparison_operator'=>(string)($rule['operator']??''),
            'value'=>(string)($rule['value']??''),
            'certainty'=>(string)($rule['certainty']??'')
        ];
        $outcome=sm_check_requirement($record,$profile);
        $kind=sm_normalize($record['requirement_type']);
        $actual=$profile[$kind]??null;
        if ($kind === 'skills') $actual=$profile['skills']??null;
        $result[]=['id'=>$record['id'],'type'=>$kind,'operator'=>$record['comparison_operator'],
          'required'=>$record['value'],'actual'=>$actual,'result'=>$outcome,
          'certainty'=>$record['certainty']];
    }
    return $result;
}
function sm_manual_status(array $details): string {
    $rules=[];
    foreach($details as $d) $rules[]=['requirement_type'=>$d['type'],'comparison_operator'=>$d['operator'],
        'value'=>$d['required'],'certainty'=>$d['certainty']];
    // Used only for display; actual decisions remain under matching_engine.php
    return $details ? (in_array('fail',array_column($details,'result'),true) ? 'Not Eligible' :
       (in_array('unknown',array_column($details,'result'),true) ? 'Unknown/Manual Check' :
       (in_array('manual',array_column($details,'result'),true) ?
           (in_array('pass',array_column($details,'result'),true)?'Potentially Eligible':'Unknown/Manual Check') : 'Eligible'))) : 'Unknown/Manual Check';
}
function sm_manual_explanation(array $evidence, array $details): string {
    $name=(string)($evidence['scholarship']['name']??'this scholarship');
    $status=sm_manual_status($details);
    $pieces=[];
    foreach($details as $d){
       $label=ucwords(str_replace('_',' ',$d['type']));
       $criteria=trim($d['operator'].' '.$d['required']);
       $current=trim((string)($d['actual']??''));
       if ($d['result']==='pass') $pieces[]="$label: recorded value '".$current."' meets rule #".$d['id']." ($criteria).";
       elseif ($d['result']==='fail') $pieces[]="$label: recorded value '".$current."' does not meet rule #".$d['id']." ($criteria).";
       elseif ($d['result']==='unknown') $pieces[]="$label: there is insufficient or unsupported data to verify rule #".$d['id']." ($criteria).";
       else $pieces[]="$label: rule #".$d['id']." requires manual confirmation ($criteria).";
    }
    $out='Rule-based assessment for '.$name.'. Current computed eligibility: '.$status.'. ';
    $out.= $pieces ? implode(' ',$pieces) : 'No structured eligibility rules are recorded; eligibility cannot be confirmed.';
    return $out.' This is based on stored records, not an official admission decision. Verify the provider requirements.';
}
