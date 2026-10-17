<?php
declare(strict_types=1);
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function read_json(string $name): array {
    $file = __DIR__.'/data/'.$name.'.json';
    if (!is_file($file)) return [];
    $fp = fopen($file, 'r'); if (!$fp) throw new RuntimeException('خطا در خواندن اطلاعات');
    flock($fp, LOCK_SH); $data = stream_get_contents($fp); flock($fp, LOCK_UN); fclose($fp);
    return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
}
function update_json(string $name, callable $update): void {
    $fp = fopen(__DIR__.'/data/'.$name.'.json','c+');
    if (!$fp || !flock($fp,LOCK_EX)) throw new RuntimeException('امکان ذخیره‌سازی وجود ندارد. دسترسی پوشه data را بررسی کنید.');
    try { $raw=stream_get_contents($fp); $data=$raw ? json_decode($raw,true,512,JSON_THROW_ON_ERROR):[]; $new=$update($data); $encoded=json_encode($new,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR); rewind($fp); if (!ftruncate($fp,0) || fwrite($fp,$encoded)===false) throw new RuntimeException('خطای ذخیره‌سازی'); fflush($fp); } finally { flock($fp,LOCK_UN); fclose($fp); }
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function check_csrf(): void { if (!hash_equals(csrf(),(string)($_POST['csrf']??''))) { http_response_code(403); exit('درخواست معتبر نیست. صفحه را دوباره باز کنید.'); } }
function admin(): bool { return !empty($_SESSION['admin']); }
function icon(string $name): string {
 $paths=['arrow'=>'<path d="M18 6 6 18M18 18H6V6"/>','brand'=>'<path d="m12 3 9 9-9 9-9-9Z"/><path d="m12 7 5 5-5 5-5-5Z"/>','film'=>'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9 5 3-5 3Z"/>','code'=>'<rect x="2" y="4" width="20" height="16" rx="3"/><path d="m9 9-3 3 3 3m6-6 3 3-3 3"/>','campaign'=>'<path d="m4 10 14-5v14L4 14Z"/><path d="m6 15 2 6h4l-2-5m11-7 2-1m-2 6 2 1"/>','plus'=>'<path d="M12 5v14M5 12h14"/>','close'=>'<path d="m6 6 12 12M6 18 18 6"/>','phone'=>'<path d="M7 3 4 4c-4 6 10 20 16 16l1-3-5-3-2 2-6-6 2-2Z"/>','menu'=>'<path d="M4 7h16M4 12h16M4 17h16"/>'];
 return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['arrow']).'</svg>';
}
$site=read_json('site');
