<?php
$uri=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if($uri==='/health'){
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>true,'service'=>'InventoryPro']);
  exit;
}
if(str_starts_with($uri,'/api/')){require dirname(__DIR__).'/api/index.php';exit;}
if($uri==='/'||$uri==='/index.html'){readfile(__DIR__.'/index.html');exit;}
$file=realpath(__DIR__.$uri);$root=realpath(__DIR__);if($file&&str_starts_with($file,$root.DIRECTORY_SEPARATOR)&&is_file($file)){ $ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));$types=['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp'];header('Content-Type:'.($types[$ext]??'text/plain').'; charset=utf-8');readfile($file);exit;}http_response_code(404);echo 'Not Found';
