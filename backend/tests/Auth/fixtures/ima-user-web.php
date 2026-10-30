<?php
// Fixture local: somente marcadores sinteticos, nunca persistir request/credencial.
$mode=$_GET['mode']??'valid';
header('Content-Type: application/json');
if ($mode==='redirect') {http_response_code(302);header('Location: /landing');echo '{}';return;}
if ($mode==='huge') {echo str_repeat('x',18000);return;}
if ($mode==='secret-error') {http_response_code(503);echo '{"error":"SYNTHETIC_MARKER_MUST_NOT_LEAK"}';return;}
if ($mode==='malformed') {echo '{"success":true,"data":[{"login":"OTHER","password":"SYNTHETIC_MARKER_MUST_NOT_LEAK"}]}';return;}
if ($mode==='denied') {http_response_code(401);echo '{"success":false,"error":{"code":"INVALID_CREDENTIALS","status":401}}';return;}
if ($mode==='timeout') {usleep(1500000);}
$r=json_decode(file_get_contents('php://input'),true);
if (($r['username']??'')!=='ciw_test' || ($r['password']??'')!=="A'ç "
    || ($_SERVER['HTTP_X_CIW_AUTH_TOKEN']??'')!==str_repeat('a',64)) {http_response_code(401);echo '{}';return;}
echo '{"success":true,"data":[{"login":"CIW_TEST"}]}';
