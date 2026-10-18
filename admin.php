<?php
require __DIR__.'/core.php'; require __DIR__.'/views.php';
header('Cache-Control: no-store');
$error=''; $ok=$_SESSION['flash']??''; unset($_SESSION['flash']);
$tab=(string)($_GET['tab']??'overview');
$tabs=['overview'=>'پیشخوان','content'=>'محتوای سایت','services'=>'خدمات','projects'=>'آثار و پروژه‌ها','brands'=>'برندهای همکار','messages'=>'درخواست‌های همکاری','settings'=>'فونت و تنظیمات','custom-css'=>'CSS سفارشی','updates'=>'به‌روزرسانی'];
if(!isset($tabs[$tab])) $tab='overview';
function upload(string $field, string $type): ?string {
 if(empty($_FILES[$field]) || $_FILES[$field]['error']===UPLOAD_ERR_NO_FILE)return null;
 $f=$_FILES[$field]; if($f['error']!==UPLOAD_ERR_OK || $f['size']>5*1024*1024)throw new RuntimeException('فایل باید کمتر از ۵ مگابایت باشد.');
 $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
 if($type==='font') { if(!in_array($ext,['woff','woff2','ttf'],true))throw new RuntimeException('فقط فونت WOFF، WOFF2 و TTF مجاز است.'); $sig=file_get_contents($f['tmp_name'],false,null,0,4); if(!in_array($sig,['wOFF','wOF2',"\x00\x01\x00\x00",'true'],true))throw new RuntimeException('فایل فونت معتبر نیست.'); }
 else { $info=@getimagesize($f['tmp_name']); $allowed=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp']; if(!$info||!isset($allowed[$info[2]]))throw new RuntimeException('فقط تصویر JPG، PNG و WebP مجاز است.'); $ext=$allowed[$info[2]]; }
 $path='uploads/'.bin2hex(random_bytes(16)).'.'.$ext; if(!move_uploaded_file($f['tmp_name'],__DIR__.'/'.$path))throw new RuntimeException('ذخیره فایل ممکن نشد. دسترسی uploads را بررسی کنید.');return $path;
}
if($_SERVER['REQUEST_METHOD']==='POST') {
 check_csrf(); $action=(string)($_POST['action']??'');
 try {
 if($action==='login') {
  $auth=read_json('auth'); $key=hash('sha256',($_SERVER['REMOTE_ADDR']??'unknown')); $blocked=false;
  update_json('attempts',function($attempts)use($key,&$blocked){ foreach($attempts as $k=>$v)if(time()-$v['time']>900)unset($attempts[$k]); $a=$attempts[$key]??['time'=>time(),'count'=>0]; $blocked=$a['count']>=8; $a['count']++; $attempts[$key]=$a; return $attempts; });
  if($blocked) throw new RuntimeException('تلاش‌های ناموفق زیاد است. ۱۵ دقیقه دیگر امتحان کنید.');
  if(!hash_equals($auth['username'],(string)($_POST['username']??'')) || !password_verify((string)($_POST['password']??''),$auth['hash']))throw new RuntimeException('نام کاربری یا رمز عبور درست نیست.');
  session_regenerate_id(true); $_SESSION['admin']=true; $_SESSION['csrf']=bin2hex(random_bytes(32)); update_json('attempts',function($a)use($key){unset($a[$key]);return $a;}); header('Location: /admin.php');exit;
 }
 elseif(!admin()) { http_response_code(403); throw new RuntimeException('ابتدا وارد شوید.'); }
 elseif($action==='logout') { $_SESSION=[];session_regenerate_id(true);header('Location: /admin.php');exit; }
 elseif(in_array($action,['prepare_update','install_update','discard_update','download_update_backup'],true)) {
  require_once __DIR__.'/updater.php';$updater=new SourceUpdater(__DIR__);
  if($action==='prepare_update') {
   $file=$_FILES['source_zip']??null;
   if(!$file||!is_array($file)||is_array($file['name']??null)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('فایل ZIP دریافت نشد؛ حجم فایل و تنظیمات آپلود PHP را بررسی کنید.');
   if(strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='zip'||!is_uploaded_file($file['tmp_name']))throw new RuntimeException('یک فایل ZIP سورس انتخاب کنید.');
   $prepared=$updater->prepare($file['tmp_name'],$file['name']);if(!empty($_SESSION['pending_update']))$updater->discard($_SESSION['pending_update']);$_SESSION['pending_update']=$prepared['id'];$ok='بسته بررسی شد؛ نتیجه را پیش از نصب مرور کنید.';
  } elseif($action==='install_update') {
   if(empty($_SESSION['pending_update'])||!hash_equals($_SESSION['pending_update'],(string)($_POST['update_id']??'')))throw new RuntimeException('بسته انتخاب‌شده متعلق به این نشست نیست.');
   if(($_POST['trust_package']??'')!=='1')throw new RuntimeException('تأیید منبع بسته برای نصب لازم است.');
   verify_update_password((string)($_POST['update_password']??''));$result=$updater->install($_SESSION['pending_update']);unset($_SESSION['pending_update']);$ok='به‌روزرسانی نصب شد و اطلاعات سایت حفظ شد. پشتیبان کد قبلی در همین بخش قابل دریافت است.';
  } elseif($action==='discard_update') {
   if(!empty($_SESSION['pending_update']))$updater->discard($_SESSION['pending_update']);unset($_SESSION['pending_update']);$ok='بسته موقت پاک شد؛ هیچ فایل کدی تغییر نکرد.';
  } else {
   $backup=$updater->backupPath((string)($_POST['backup_id']??''));header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="negah-code-backup-'.basename($backup).'"');header('Content-Length: '.filesize($backup));readfile($backup);exit;
  }
 }
 elseif($action==='content') {
  $fields=['brand','legal','phone','eyebrow','hero_title','hero_text','about_title','about_text','contact_title','contact_text'];
  $new=[];foreach($fields as $field){ $new[$field]=trim((string)($_POST[$field]??''));if(!$new[$field]||strlen($new[$field])>6000)throw new RuntimeException('همه فیلدها را تکمیل کنید (حداکثر ۳۰۰۰ حرف).'); }
  if(!preg_match('/^09[0-9]{9}$/',$new['phone']))throw new RuntimeException('شماره تماس باید ۱۱ رقم و با 09 شروع شود.');
  update_json('site',fn($s)=>array_replace($s,$new)); $ok='محتوای سایت ذخیره شد.';
 }
 elseif($action==='services') {
  $services=[];foreach(($_POST['services']??[]) as $i=>$s){if(!isset($site['services'][$i]))continue;$services[]=['title'=>trim(substr((string)$s['title'],0,250)),'text'=>trim(substr((string)$s['text'],0,2000)),'tags'=>trim(substr((string)$s['tags'],0,500)),'icon'=>$site['services'][$i]['icon']];}
  if(count($services)!==4||in_array('',array_column($services,'title'),true))throw new RuntimeException('عنوان چهار خدمت را تکمیل کنید.');
  update_json('site',fn($s)=>array_replace($s,['services'=>$services]));$ok='خدمات به‌روزرسانی شد.';
 }
 elseif($action==='brand') {
  $name=trim((string)($_POST['name']??'')); if(!$name||strlen($name)>240)throw new RuntimeException('نام برند را وارد کنید (حداکثر ۱۲۰ حرف).');
  $id=filter_var($_POST['id']??'',FILTER_VALIDATE_INT);$existing=$id!==false&&isset($site['brands'][$id])?$site['brands'][$id]:null;
  $description=trim((string)($_POST['brand_description']??''));if(strlen($description)>3000)throw new RuntimeException('توضیحات برند باید حداکثر ۳۰۰۰ بایت باشد.');
  $logo=upload('logo','image')??($existing['logo']??'');if(!empty($_POST['remove_logo']))$logo='';
  update_json('site',function($s)use($name,$logo,$id,$existing,$description){$s['brands']??=[];$brand=['name'=>$name,'logo'=>$logo,'description'=>$description,'uid'=>$existing?substr(brand_class($existing),6):bin2hex(random_bytes(8))];if($existing)$s['brands'][$id]=$brand;else $s['brands'][]=$brand;return $s;});$ok='برند همکار ذخیره شد.';
 }
 elseif($action==='custom_css') {
  if(!isset($_POST['custom_css'])||!is_string($_POST['custom_css']))throw new RuntimeException('کد CSS معتبر نیست.');
  $css=trim($_POST['custom_css']);
  if(strlen($css)>50000||str_contains($css,'<'))throw new RuntimeException('CSS باید حداکثر ۵۰ هزار بایت و بدون تگ HTML باشد.');
  update_json('site',fn($s)=>array_replace($s,['custom_css'=>$css]));$ok='CSS سفارشی سایت ذخیره شد.';
 }
 elseif($action==='brand_css') {
  $css=trim((string)($_POST['brand_css']??''));
  if(strlen($css)>20000||str_contains($css,'<'))throw new RuntimeException('CSS باید حداکثر ۲۰ هزار بایت و بدون تگ HTML باشد.');
  update_json('site',fn($s)=>array_replace($s,['brand_css'=>$css]));$ok='CSS سفارشی برندها ذخیره شد.';
 }
 elseif($action==='delete_brand') {
  $id=filter_var($_POST['id']??'',FILTER_VALIDATE_INT);if($id===false)throw new RuntimeException('برند معتبر نیست.');update_json('site',function($s)use($id){if(isset($s['brands'][$id]))array_splice($s['brands'],$id,1);return $s;});$ok='برند حذف شد.';
 }
 elseif($action==='logo') { $logo=upload('logo','image');if(!$logo)throw new RuntimeException('فایل لوگو را انتخاب کنید.');update_json('site',fn($s)=>array_replace($s,['logo'=>$logo]));$ok='لوگوی سایت به‌روزرسانی شد.'; }
 elseif($action==='reset_logo') { update_json('site',function($s){unset($s['logo']);return $s;});$ok='لوگوی اصلی نگاه فعال شد.'; }
 elseif($action==='project') {
  $title=trim((string)($_POST['title']??''));$description=trim((string)($_POST['description']??''));$category=(string)($_POST['category']??'');$label=trim((string)($_POST['label']??''));
  if(!$title||!$description||!$label||strlen($title)>250||strlen($label)>250||strlen($description)>30000||!in_array($category,['branding','film','digital','campaign'],true))throw new RuntimeException('عنوان، دسته، برچسب و توضیحات پروژه را کامل و در محدوده مجاز وارد کنید.');
  $id=filter_var($_POST['id']??'',FILTER_VALIDATE_INT);$existing=$id!==false&&isset($site['projects'][$id])?$site['projects'][$id]:null;
  if((string)($_POST['id']??'')!==''&&!$existing)throw new RuntimeException('پروژه دیگر وجود ندارد؛ صفحه را تازه‌سازی کنید.');
  $client=trim((string)($_POST['client']??''));$year=trim((string)($_POST['year']??''));$role=trim((string)($_POST['role']??''));
  if(strlen($client)>250||strlen($role)>500||strlen($year)>32)throw new RuntimeException('مشخصات پروژه بیش از حد طولانی است.');
  $video_url=trim((string)($_POST['video_url']??''));
  if($video_url&&(!filter_var($video_url,FILTER_VALIDATE_URL)||parse_url($video_url,PHP_URL_SCHEME)!=='https'||!preg_match('~\.(mp4|webm)$~i',parse_url($video_url,PHP_URL_PATH)??'')||strlen($video_url)>2000))throw new RuntimeException('آدرس ویدیو باید یک لینک مستقیم HTTPS با فرمت mp4 یا webm باشد.');
  $gallery=[];
  foreach(($existing['gallery']??[]) as $i=>$media){$edit=$_POST['gallery_form'][$i]??[];if(!empty($edit['remove']))continue;$caption=trim((string)($edit['caption']??$media['caption']??''));if(strlen($caption)>500)throw new RuntimeException('توضیح تصویر بیش از حد طولانی است.');$media['caption']=$caption;$media['_order']=max(0,min(999,(int)($edit['order']??$i)));$gallery[]=$media;}
  usort($gallery,fn($a,$b)=>$a['_order']<=>$b['_order']);foreach($gallery as &$media)unset($media['_order']);unset($media);
  $files=$_FILES['gallery']??[];$errors=$files['error']??[];
  if(!is_array($errors))throw new RuntimeException('ساختار فایل‌های گالری معتبر نیست.');
  $newCount=count(array_filter($errors,fn($error)=>$error!==UPLOAD_ERR_NO_FILE));
  if($newCount>8||count($gallery)+$newCount>24)throw new RuntimeException('در هر بار حداکثر ۸ تصویر و برای هر پروژه حداکثر ۲۴ تصویر مجاز است.');
  if(!empty($_SERVER['CONTENT_LENGTH'])&&(int)$_SERVER['CONTENT_LENGTH']>50*1024*1024)throw new RuntimeException('حجم کل درخواست بیش از ۵۰ مگابایت است.');
  foreach($errors as $i=>$error){if($error===UPLOAD_ERR_NO_FILE)continue;$_FILES['gallery_item']=['name'=>$files['name'][$i],'type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i],'error'=>$error,'size'=>$files['size'][$i]];$path=upload('gallery_item','image');$gallery[]=['image'=>$path,'caption'=>''];}unset($_FILES['gallery_item']);
  $image=upload('image','image')??($existing['image']??'');if(!empty($_POST['remove_cover']))$image='';
  $visual=['branding'=>'avid','film'=>'film','digital'=>'digital','campaign'=>'avid'][$category];
  $uid=$existing?project_key($existing):bin2hex(random_bytes(8));
  $brand_id=(string)($_POST['brand_id']??'');if($brand_id&&!in_array($brand_id,array_map('brand_class',$site['brands']??[]),true))throw new RuntimeException('برند مرتبط معتبر نیست.');
  $project=compact('uid','title','description','category','label','image','visual','client','year','role','video_url','gallery','brand_id');
  update_json('site',function($s)use($project,$id,$existing){if($existing)$s['projects'][$id]=$project;else $s['projects'][]=$project;return $s;});$ok='پروژه و گالری آن ذخیره شد.';
 }
 elseif($action==='delete_project') { $id=filter_var($_POST['id']??'',FILTER_VALIDATE_INT);if($id===false)throw new RuntimeException('پروژه معتبر نیست.');update_json('site',function($s)use($id){if(isset($s['projects'][$id]))array_splice($s['projects'],$id,1);return $s;});$ok='پروژه حذف شد.'; }
 elseif($action==='message_read'||$action==='message_delete') { $id=(string)($_POST['id']??'');update_json('messages',function($messages)use($action,$id){if($action==='message_delete')return array_values(array_filter($messages,fn($m)=>$m['id']!==$id));foreach($messages as &$m)if($m['id']===$id)$m['read']=true;unset($m);return $messages;});$ok='درخواست به‌روزرسانی شد.'; }
 elseif($action==='banners') { $images=[]; foreach(['hero_background','hero_image','cinema_image'] as $key) { $image=upload($key,'image'); if($image)$images[$key]=$image; } if(!$images)throw new RuntimeException('حداقل یک تصویر انتخاب کنید.'); update_json('site',fn($s)=>array_replace($s,$images));$ok='تصاویر بنر ذخیره شد.'; }
 elseif($action==='reset_banners') { update_json('site',function($s){unset($s['hero_background'],$s['hero_image'],$s['cinema_image']);return $s;});$ok='تصاویر پیش‌فرض بنر فعال شد.'; }
 elseif($action==='font') { $font=upload('font','font');if(!$font)throw new RuntimeException('یک فایل فونت انتخاب کنید.');update_json('site',fn($s)=>array_replace($s,['font'=>$font]));$ok='فونت جدید روی کل سایت فعال شد.'; }
 elseif($action==='reset_font') { update_json('site',fn($s)=>array_replace($s,['font'=>'']));$ok='فونت پیش‌فرض فعال شد.'; }
 elseif($action==='password') { $auth=read_json('auth');if(!password_verify((string)($_POST['current_password']??''),$auth['hash']))throw new RuntimeException('رمز فعلی صحیح نیست.');$password=(string)($_POST['new_password']??'');if(strlen($password)<10||strlen($password)>72)throw new RuntimeException('رمز جدید باید ۱۰ تا ۷۲ بایت باشد.');update_json('auth',fn($a)=>array_replace($a,['hash'=>password_hash($password,PASSWORD_DEFAULT)]));session_regenerate_id(true);$ok='رمز عبور تغییر کرد.'; }
 else throw new RuntimeException('عملیات معتبر نیست.');
 if($ok){$_SESSION['flash']=$ok;header('Location: /admin.php?tab='.urlencode($tab));exit;}
 } catch(Throwable $ex) { $error=$ex instanceof RuntimeException?$ex->getMessage():'ذخیره اطلاعات انجام نشد. دسترسی پوشه data را بررسی کنید.'; }
}
$site=read_json('site');$messages=admin()?read_json('messages'):[];$unread=count(array_filter($messages,fn($m)=>!$m['read']));
function hidden(string $action): void {echo '<input type="hidden" name="csrf" value="'.e(csrf()).'"><input type="hidden" name="action" value="'.e($action).'">';}
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>مدیریت نگاه مدیا</title><style id="admin-styles"><?php readfile(__DIR__.'/assets/style.css'); readfile(__DIR__.'/assets/admin.css'); ?></style><link rel="icon" href="/assets/favicon.svg"></head><body class="admin-body">
<?php if(!admin()):?><main class="login-wrap"><a href="/" class="admin-brand"><?php brand_logo($site);?></a><div class="admin-panel"><span class="section-kicker">فضای مدیریت</span><h2>خوش برگشتید.</h2><p>برای مدیریت نگاه مدیا وارد شوید.</p><?php if($error):?><div class="notice error" role="alert"><?=e($error)?></div><?php endif;?><form method="post"><?php hidden('login');?><label>نام کاربری<input name="username" dir="ltr" autocomplete="username" required></label><label>رمز عبور<input type="password" name="password" dir="ltr" autocomplete="current-password" required></label><button class="button primary">ورود به پنل <?=icon('arrow')?></button></form></div><a class="back-site" href="/">بازگشت به سایت ↗</a></main>
<?php else:?><aside class="admin-sidebar"><a class="admin-brand" href="/admin.php"><?php brand_logo($site);?><small>پنل مدیریت محتوا</small></a><nav><?php foreach($tabs as $key=>$title):?><a class="<?=$tab===$key?'selected':''?>" href="?tab=<?=$key?>"><?=e($title)?><?php if($key==='messages'&&$unread):?><b><?=$unread?></b><?php endif;?></a><?php endforeach;?></nav><div class="sidebar-bottom"><a href="/" target="_blank" rel="noopener">مشاهده سایت ↗</a><form method="post"><?php hidden('logout');?><button>خروج از حساب</button></form></div></aside><main class="admin-main"><header class="admin-header"><div><span class="section-kicker">فضای کاری شما</span><h1><?=e($tabs[$tab])?></h1></div><span>سلام، Mohusyn</span></header><?php if($error):?><div class="notice error" role="alert"><?=e($error)?></div><?php endif;?><?php if($ok):?><div class="notice success" role="status"><?=e($ok)?></div><?php endif;?>
<?php if($tab==='overview'):?><div class="stats-grid"><div class="admin-panel"><span>درخواست‌های همکاری</span><strong><?=count($messages)?></strong></div><div class="admin-panel"><span>درخواست خوانده‌نشده</span><strong><?=$unread?></strong></div><div class="admin-panel"><span>آثار منتشرشده</span><strong><?=count($site['projects'])?></strong></div></div><div class="admin-panel welcome"><span class="section-kicker">همه‌چیز در کنترل شماست</span><h2>نگاه تازه‌ای به سایتتان بدهید.</h2><p>متن‌ها را ویرایش کنید، آثار تازه اضافه کنید و درخواست‌های همکاری را اینجا ببینید.</p><div class="admin-actions"><a class="button primary" href="?tab=content">ویرایش محتوا <?=icon('arrow')?></a><a class="button secondary" href="?tab=messages">دیدن درخواست‌ها</a></div></div><div class="admin-panel"><h3>راهنمای کوتاه</h3><ul class="help-list"><li>تغییرات پس از ذخیره، بلافاصله در سایت نمایش داده می‌شوند.</li><li>در بخش آثار، نمونه‌های مفهومی را با پروژه‌های واقعی خودتان جایگزین کنید.</li><li>فونت را از بخش تنظیمات بارگذاری کنید؛ فونت پیش‌فرض وزیرمتن است.</li><li>از پوشه data و uploads به‌طور منظم نسخه پشتیبان بگیرید.</li></ul></div>
<?php elseif($tab==='content'):?><form method="post" class="admin-panel editor-form"><?php hidden('content');?><h3>مشخصات برند</h3><div class="form-row"><?php foreach(['brand'=>'نام عمومی','legal'=>'نام حقوقی','phone'=>'شماره تماس','eyebrow'=>'عنوان کوتاه استودیو'] as $key=>$label):?><label><?=e($label)?><input name="<?=$key?>" value="<?=e($site[$key])?>" required></label><?php endforeach;?></div><?php foreach(['hero'=>'صفحه اصلی','about'=>'درباره نگاه','contact'=>'دعوت به همکاری'] as $key=>$label):?><h3><?=e($label)?></h3><label>عنوان (هر خط را با Enter جدا کنید)<textarea name="<?=$key?>_title" rows="2" required><?=e($site[$key.'_title'])?></textarea></label><label>توضیحات<textarea name="<?=$key?>_text" rows="3" required><?=e($site[$key.'_text'])?></textarea></label><?php endforeach;?><button class="button primary">ذخیره تغییرات</button></form>
<?php elseif($tab==='services'):?><form method="post" class="editor-form"><?php hidden('services');foreach($site['services'] as $i=>$s):?><div class="admin-panel"><h3>خدمت <?=$i+1?></h3><label>عنوان<input name="services[<?=$i?>][title]" value="<?=e($s['title'])?>" required></label><label>توضیحات<textarea name="services[<?=$i?>][text]" rows="2"><?=e($s['text'])?></textarea></label><label>تخصص‌ها<input name="services[<?=$i?>][tags]" value="<?=e($s['tags'])?>"></label></div><?php endforeach;?><button class="button primary">ذخیره خدمات</button></form>
<?php elseif($tab==='projects'):?><?php require __DIR__.'/admin-projects.php'; ?>
<?php elseif($tab==='brands'):?><?php $edit=filter_var($_GET['edit']??'',FILTER_VALIDATE_INT);$brand=$edit!==false&&isset($site['brands'][$edit])?$site['brands'][$edit]:null;?><div class="admin-panel editor-form"><h3><?=$brand?'ویرایش برند همکار':'افزودن برند همکار'?></h3><p class="admin-hint">فقط برندهایی را وارد کنید که واقعاً با آن‌ها همکاری کرده‌اید. لوگوها در یک ردیف خلوت زیر معرفی اصلی نمایش داده می‌شوند. بدون لوگو، نام برند نمایش داده می‌شود. ترتیب نمایش مطابق ترتیب افزودن است.</p><form method="post" enctype="multipart/form-data"><?php hidden('brand');?><input type="hidden" name="id" value="<?=$brand?$edit:''?>"><label>نام برند<input name="name" value="<?=e($brand['name']??'')?>" maxlength="120" required></label><label>اطلاعات کارفرما (کارت دسکتاپ)<textarea name="brand_description" rows="4" maxlength="1000"><?=e($brand['description']??'')?></textarea></label><p class="admin-hint">این توضیح هنگام هاور یا فوکوس لوگو فقط روی دسکتاپ نمایش داده می‌شود. برای دکمه دیدن کارهای این برند، پروژه‌ها را از فرم ویرایش پروژه به این برند مرتبط کنید.</p><label>لوگوی برند (PNG، WebP یا JPG؛ حداکثر ۵ مگابایت)<input type="file" name="logo" accept="image/png,image/webp,image/jpeg"></label><?php if(!empty($brand['logo'])):?><img class="admin-brand-preview" src="/<?=e($brand['logo'])?>" alt="<?=e($brand['name'])?>"><label class="brand-check"><input type="checkbox" name="remove_logo" value="1"> حذف لوگوی فعلی و نمایش نام برند</label><?php endif;?><button class="button primary">ذخیره برند</button><?php if($brand):?><a class="button secondary" href="?tab=brands">انصراف</a><?php endif;?></form></div><div class="admin-panel"><h3>برندهای همکار</h3><?php if(empty($site['brands'])):?><p class="admin-hint">هنوز برندی ثبت نشده است؛ این بخش تا افزودن اولین برند در سایت نمایش داده نمی‌شود.</p><?php endif;?><?php foreach(($site['brands']??[]) as $i=>$b):?><div class="admin-project-row"><div class="brand-row-title"><?php if($b['logo']):?><img src="/<?=e($b['logo'])?>" alt=""><?php endif;?><b><?=e($b['name'])?></b><code dir="ltr">.<?=e(brand_class($b))?></code></div><div class="row-actions"><a href="?tab=brands&edit=<?=$i?>">ویرایش</a><form method="post" data-confirm="این برند از سایت حذف شود؟"><?php hidden('delete_brand');?><input type="hidden" name="id" value="<?=$i?>"><button class="danger-text">حذف</button></form></div></div><?php endforeach;?></div>
<form method="post" class="admin-panel editor-form"><?php hidden('brand_css');?><h3>CSS سفارشی لوگوهای همکار</h3><p class="admin-hint">کلاس ثابت هر برند در فهرست بالا نوشته شده است؛ ویرایش نام یا حذف برندهای دیگر آن را تغییر نمی‌دهد. این کلاس روی هر دو نسخه لوگو در نوار متحرک اعمال می‌شود. CSS فقط در صفحه اصلی بارگذاری می‌شود. برای بزرگ‌کردن یک لوگو، کلاس نمونه را با کلاس همان برند جایگزین کنید.</p><pre dir="ltr" style="white-space:pre-wrap;overflow-wrap:anywhere">.partner-group .brand-example img {
  width: 160px;
  max-width: 160px;
  max-height: 65px;
  height: auto;
}</pre><label>کد CSS (بدون تگ style)<textarea name="brand_css" dir="ltr" rows="10" maxlength="20000" spellcheck="false" style="font-family:monospace;text-align:left"><?=e($site['brand_css']??'')?></textarea></label><p class="admin-hint">حداقل عرض ۱۰۰ پیکسل برای هر لوگو حفظ شده است. تغییرات CSS بلافاصله پس از ذخیره روی سایت اعمال می‌شوند؛ برای بازنشانی، این کادر را خالی و ذخیره کنید. فقط CSS مورد اعتماد وارد کنید؛ این کد می‌تواند ظاهر صفحه را تغییر دهد یا منابع خارجی بارگیری کند.</p><button class="button primary">ذخیره CSS لوگوها</button></form>
<?php elseif($tab==='messages'):?><?php if(!$messages):?><div class="admin-panel empty"><span>↙</span><h2>هنوز درخواستی نرسیده.</h2><p>درخواست‌های فرم همکاری سایت، اینجا نمایش داده می‌شوند.</p></div><?php endif;?><?php foreach(array_reverse($messages) as $m):?><article class="admin-panel message-card <?=!$m['read']?'unread':''?>"><div class="message-top"><div><h3><?=e($m['name'])?> <?php if(!$m['read']):?><span class="badge">جدید</span><?php endif;?></h3><span><?=e($m['service'])?></span></div><time datetime="<?=e($m['date'])?>"><?=e(gmdate('Y/m/d · H:i',strtotime($m['date'])))?> UTC</time></div><a class="message-phone" href="tel:<?=e($m['phone'])?>" dir="ltr"><?=e($m['phone'])?></a><p><?=nl2br(e($m['message']))?></p><div class="row-actions"><?php if(!$m['read']):?><form method="post"><?php hidden('message_read');?><input type="hidden" name="id" value="<?=e($m['id'])?>"><button class="button secondary">علامت‌گذاری خوانده‌شده</button></form><?php endif;?><form method="post" data-confirm="این درخواست برای همیشه حذف شود؟"><?php hidden('message_delete');?><input type="hidden" name="id" value="<?=e($m['id'])?>"><button class="danger-text">حذف درخواست</button></form></div></article><?php endforeach;?>
<?php elseif($tab==='updates'):?><?php require __DIR__.'/admin-updates.php';?>
<?php elseif($tab==='custom-css'):?><form method="post" class="admin-panel editor-form"><?php hidden('custom_css');?><h3>CSS سفارشی کل سایت</h3><p class="admin-hint">این کد بعد از استایل‌های پیش‌فرض، فونت و CSS لوگوها در تمام صفحات عمومی (خانه، همه کارها و صفحه هر پروژه) اعمال می‌شود؛ پنل مدیریت و صفحه ورود را تغییر نمی‌دهد. تغییرات با آپدیت سورس حفظ می‌شوند.</p><label>کد CSS (بدون تگ style)<textarea name="custom_css" dir="ltr" rows="20" maxlength="50000" spellcheck="false" style="font-family:monospace;text-align:left" placeholder="/* CSS دلخواه شما */"><?=e($site['custom_css']??'')?></textarea></label><p class="admin-hint">حداکثر ۵۰ هزار بایت. فقط کد مورد اعتماد وارد کنید؛ CSS می‌تواند ظاهر صفحات را تغییر دهد یا منابع خارجی بارگیری کند. برای بازنشانی، کادر را خالی و ذخیره کنید. قبل از تغییر، از کد فعلی یک کپی بگیرید.</p><button class="button primary">ذخیره CSS سایت</button></form><div class="admin-panel editor-form"><h3>نمونه تغییرات ساده</h3><p class="admin-hint">فقط بخش موردنیاز را کپی کنید. برای تیتر اصلی از کلاس <code dir="ltr">.studio-copy h1</code> استفاده کنید؛ تغییرات دسکتاپ را داخل media query بگذارید تا موبایل به هم نریزد. برای جلوگیری از بریده‌شدن تیتر، محدودیت اندازه و شکست خط تیتر اصلی زیر عرض ۱۲۰۰ پیکسل پس از CSS سفارشی اعمال می‌شود.</p><pre dir="ltr" style="white-space:pre-wrap;overflow-wrap:anywhere;text-align:left">/* رنگ اصلی و پس‌زمینه */
:root {
  --green: #152e64;
  --paper: #f7f9fd;
}

/* اندازه و فاصله حروف تیتر، فقط دسکتاپ */
@media (min-width: 1101px) {
  .studio-copy h1 {
    font-size: 64px;
    letter-spacing: -0.8px;
  }
  .studio-hero {
    column-gap: 60px;
  }
}

/* گردی دکمه‌ها در صفحات عمومی */
.button {
  border-radius: 14px;
}</pre><p class="admin-hint">کلاس‌های کاربردی: <code dir="ltr">.header</code> هدر، <code dir="ltr">.container</code> عرض محتوا، <code dir="ltr">.studio-copy h1</code> تیتر اصلی، <code dir="ltr">.project-card</code> کارت اثر، <code dir="ltr">.partner-logo img</code> لوگوی همکار و <code dir="ltr">.footer</code> فوتر.</p></div>
<?php elseif($tab==='settings'):?><div class="admin-panel editor-form"><h3>لوگوی سایت</h3><div class="logo-setting-preview"><?php brand_logo($site);?></div><p class="admin-hint">لوگوی شفاف PNG یا WebP پیشنهاد می‌شود. فرمت JPG نیز پذیرفته می‌شود. حداکثر حجم ۵ مگابایت؛ لوگو در هدر، پایین صفحه و صفحات آثار به‌روز می‌شود.</p><form method="post" enctype="multipart/form-data"><?php hidden('logo');?><label>فایل لوگوی جدید<input type="file" name="logo" accept="image/png,image/webp,image/jpeg" required></label><button class="button primary">ذخیره لوگو</button></form><form method="post" class="reset-font"><?php hidden('reset_logo');?><button class="button secondary">بازگشت به لوگوی اصلی نگاه</button></form></div><div class="admin-panel editor-form"><h3>تصویر پس‌زمینه صفحه اصلی</h3><p class="admin-hint" id="hero-background-help"><strong>اندازه پیشنهادی: <bdi dir="ltr">۳۸۴۰ × ۲۱۶۰</bdi> پیکسل (4K، عرض × ارتفاع).</strong><br>تصویر افقی با نسبت <bdi dir="ltr">16:9</bdi>؛ اندازه سبک‌تر قابل استفاده: <bdi dir="ltr">۱۹۲۰ × ۱۰۸۰</bdi> پیکسل. ابعاد پیشنهادی اجباری نیستند.<br>فرمت JPG، PNG یا WebP؛ حداکثر حجم ۵ مگابایت. برای سرعت بهتر، WebP با حجم حدود ۵۰۰ کیلوبایت تا ۱ مگابایت پیشنهاد می‌شود.<br>این عکس، کل پس‌زمینه بخش اول سایت (فضای استودیو، دوربین و میز طراحی) است. نیمه بالایی و مرکز تصویر را روشن و خلوت بگذارید تا تیتر خوانا بماند؛ سوژه‌های اصلی را پایین تصویر نگه دارید. در موبایل بخشی از کناره‌ها برش می‌خورد.</p><?php $heroPreview=media_path($site['hero_background']??'')?:'/assets/creative-studio-hd.webp';$heroInfo=is_file(__DIR__.$heroPreview)?@getimagesize(__DIR__.$heroPreview):false;?><img class="admin-cover-preview" src="<?=e($heroPreview)?>" alt="پیش‌نمایش تصویر پس‌زمینه فعلی صفحه اصلی" loading="lazy"><?php if($heroInfo):?><p class="admin-hint">ابعاد فایل فعلی: <bdi dir="ltr"><?=$heroInfo[0]?> × <?=$heroInfo[1]?></bdi> پیکسل (عرض × ارتفاع).<?php if(empty($site['hero_background'])):?> در حالت پیش‌فرض نسخه ۴K نیز متناسب با نمایشگر بارگذاری می‌شود.<?php endif;?></p><?php endif;?><form method="post" enctype="multipart/form-data"><?php hidden('banners');?><label>انتخاب عکس جایگزین پس‌زمینه<input type="file" name="hero_background" aria-describedby="hero-background-help" accept="image/jpeg,image/png,image/webp"></label><button class="button primary">ذخیره تصویر اصلی</button></form><form method="post" class="reset-font"><?php hidden('reset_banners');?><button class="button secondary">بازگشت به تصویر پیش‌فرض</button></form></div><div class="admin-panel editor-form"><h3>فونت اختصاصی سایت</h3><p class="admin-hint">فونت فارسی با فرمت WOFF2، WOFF یا TTF بارگذاری کنید. حداکثر ۵ مگابایت. فقط فونتی که مجوز استفاده وب آن را دارید.</p><p class="font-status">فونت فعلی: <?=$site['font']?'فونت اختصاصی شما':'وزیرمتن (پیش‌فرض)'?></p><form method="post" enctype="multipart/form-data"><?php hidden('font');?><label>انتخاب فایل فونت<input type="file" name="font" accept=".woff2,.woff,.ttf" required></label><button class="button primary">بارگذاری و فعال‌سازی</button></form><?php if($site['font']):?><form method="post" class="reset-font"><?php hidden('reset_font');?><button class="button secondary">بازگشت به وزیرمتن</button></form><?php endif;?></div><div class="admin-panel editor-form"><h3>تغییر رمز عبور</h3><p class="admin-hint">نام کاربری: Mohusyn — بهتر است رمز اولیه را پس از ورود تغییر دهید.</p><form method="post"><?php hidden('password');?><label>رمز فعلی<input name="current_password" type="password" autocomplete="current-password" required></label><label>رمز جدید (حداقل ۱۰ کاراکتر)<input name="new_password" type="password" autocomplete="new-password" minlength="10" maxlength="72" required></label><button class="button primary">تغییر رمز</button></form></div><?php endif;?></main><?php endif;?><script src="/assets/admin.js" defer></script></body></html>
