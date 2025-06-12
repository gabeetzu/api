<?php
header('Content-Type: application/json; charset=utf-8');
$allowed=['https://gospodapp.netlify.app','https://gospodapp.ro'];
$origin=$_SERVER['HTTP_ORIGIN']??'';
if(in_array($origin,$allowed)){
    header('Access-Control-Allow-Origin: '.$origin);
}else{
    header('Access-Control-Allow-Origin: *');
}
$input=json_decode(file_get_contents('php://input'),true) ?: [];
$ref=preg_replace('/[^A-Z0-9]/','',$input['referrer']??'');
$dev=preg_replace('/[^a-zA-Z0-9_-]/','',$input['device_hash']??'');
$joined=substr($input['joined']??'',0,30);
$line=date('Y-m-d H:i:s').",$ref,$dev,$joined\n";
file_put_contents('/var/data/logs/referrals.csv',$line,FILE_APPEND);
echo json_encode(['success'=>true]);
?>
