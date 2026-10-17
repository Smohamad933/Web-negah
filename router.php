<?php
// Development server only. IIS uses web.config.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'/');
if(preg_match('~(?:^|/)(?:data|node_modules|\.git|core\.php|views\.php|admin-projects\.php|tools|router\.php)(?:/|$)|\.json$|(?:^|/)\.~i',$path)||str_contains($path,'..')){http_response_code(404);exit('Not found');}
if($path==='/' || $path==='/index.php'){require __DIR__.'/index.php';return true;}
if($path==='/works.php'){require __DIR__.'/works.php';return true;}
if($path==='/project.php'){require __DIR__.'/project.php';return true;}
if($path==='/admin.php'){require __DIR__.'/admin.php';return true;}
if(preg_match('~^/(?:assets|uploads)/[a-zA-Z0-9._-]+\.(?:css|js|svg|jpg|jpeg|png|webp|woff2?|ttf|txt)$~',$path)&&is_file(__DIR__.$path)) return false;
http_response_code(404);echo 'Not found';
