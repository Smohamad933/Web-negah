<?php
// Included only after the application bootstrap. Uploaded PHP is never executed during validation.
if (!function_exists('admin') || !admin()) { http_response_code(404); exit; }
final class SourceUpdater {
    private string $root;
    private string $storage;
    public function __construct(string $root) {
        $resolved=realpath($root);
        if(!$resolved) throw new RuntimeException('مسیر سایت معتبر نیست.');
        $this->root=$resolved;
        $this->storage=$resolved.'/data/updates';
    }
    private function mkdir(string $dir): void {
        if(is_link($dir) || (!is_dir($dir)&&!@mkdir($dir,0700,true))) throw new RuntimeException('ساخت پوشه خصوصی به‌روزرسانی ممکن نیست. مجوز data را بررسی کنید.');
    }
    private function initialize(): void {
        if(!class_exists('ZipArchive')) throw new RuntimeException('افزونه ZIP در PHP فعال نیست. آن را روی سرور فعال کنید.');
        if(is_link($this->root.'/data')) throw new RuntimeException('پوشه data نباید پیوند باشد.');
        $this->mkdir($this->storage);$this->mkdir($this->storage.'/staging');$this->mkdir($this->storage.'/backups');
    }
    private function path(string $name): string {
        if(!$name || strlen($name)>220 || str_contains($name,'\\') || !preg_match('~^[A-Za-z0-9][A-Za-z0-9._/-]*$~D',$name)) throw new RuntimeException('بسته شامل مسیر غیرمجاز است.');
        foreach(explode('/',$name) as $part) {
            if(!$part || $part==='.' || $part==='..' || str_ends_with($part,'.') || preg_match('/^(con|prn|aux|nul|com[1-9]|lpt[1-9])(?:\.|$)/i',$part)) throw new RuntimeException('مسیر فایل در ZIP امن نیست.');
        }
        return $name;
    }
    private function installable(string $path): bool {
        if(preg_match('~^(data|uploads|tools)/~',$path)) return false;
        if(preg_match('~^[a-z][a-z0-9-]*\.php$~D',$path)) return true;
        if(in_array($path,['web.config','README.md'],true)) return true;
        if(preg_match('~^assets/[A-Za-z0-9_-][A-Za-z0-9._-]*\.(css|js|svg|png|jpe?g|webp|woff2?|ttf|txt)$~D',$path)) return true;
        throw new RuntimeException('بسته شامل نوع فایل غیرمجاز برای نصب است: '.$path);
    }
    private function target(string $path): string {
        $this->path($path);$target=$this->root;
        foreach(explode('/',$path) as $part) {
            $target.='/'.$part;
            if(is_link($target)) throw new RuntimeException('نصب روی پیوند فایل یا پوشه مجاز نیست.');
        }
        return $target;
    }
    private function atomicWrite(string $target, string $contents): void {
        $dir=dirname($target);
        if(!is_dir($dir) || !is_writable($dir) || (file_exists($target)&&!is_file($target)) || (is_file($target)&&!is_writable($target))) throw new RuntimeException('مجوز نوشتن فایل‌های کد کافی نیست.');
        $tmp=@tempnam($dir,'.negah-update-');
        if(!$tmp) throw new RuntimeException('ساخت فایل موقت نصب ممکن نیست.');
        try {
            if(@file_put_contents($tmp,$contents,LOCK_EX)!==strlen($contents)) throw new RuntimeException('نوشتن کامل فایل نصب ممکن نشد.');
            @chmod($tmp,is_file($target)?(fileperms($target)&0777):0644);
            if(!@rename($tmp,$target)) throw new RuntimeException('جایگزینی فایل کد ممکن نشد.');
        } finally { if(is_file($tmp)) @unlink($tmp); }
    }
    private function writeJson(string $path, array $data): void {
        $this->atomicWrite($path,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }
    private function stageDir(string $id): string {
        if(!preg_match('/^[a-f0-9]{32}$/D',$id)) throw new RuntimeException('بسته آماده نصب معتبر نیست.');
        return $this->storage.'/staging/'.$id;
    }
    private function removeTree(string $path): void {
        if(is_link($path)||is_file($path)){@unlink($path);return;}
        if(!is_dir($path))return;
        foreach(new DirectoryIterator($path) as $entry) if(!$entry->isDot())$this->removeTree($entry->getPathname());
        @rmdir($path);
    }
    public function discard(string $id): void { $this->removeTree($this->stageDir($id)); }
    public function pending(string $id): array {
        $dir=$this->stageDir($id);$file=$dir.'/state.json';
        if(!is_file($file))throw new RuntimeException('بسته آماده نصب پیدا نشد؛ ZIP را دوباره بررسی کنید.');
        $state=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
        if(time()-($state['created']??0)>1800){$this->discard($id);throw new RuntimeException('مهلت نصب بسته تمام شده؛ دوباره بارگذاری کنید.');}
        return $state;
    }
    public function prepare(string $zipPath, string $displayName): array {
        $this->initialize();
        foreach(new DirectoryIterator($this->storage.'/staging') as $entry) {
            if(!$entry->isDot() && !$entry->isLink() && preg_match('/^[a-f0-9]{32}$/D',$entry->getFilename()) && time()-$entry->getMTime()>86400)$this->removeTree($entry->getPathname());
        }
        if(!is_file($zipPath)||filesize($zipPath)>5*1024*1024)throw new RuntimeException('ZIP سورس باید حداکثر ۵ مگابایت باشد.');
        $zip=new ZipArchive();
        if($zip->open($zipPath,ZipArchive::CHECKCONS)!==true)throw new RuntimeException('فایل ZIP سالم یا قابل خواندن نیست.');
        $id=bin2hex(random_bytes(16));$dir=$this->stageDir($id);
        try {
            if($zip->numFiles<2||$zip->numFiles>500)throw new RuntimeException('تعداد فایل‌های بسته مجاز نیست.');
            $entries=[];$seen=[];$manifestNames=[];$total=0;
            for($i=0;$i<$zip->numFiles;$i++) {
                $stat=$zip->statIndex($i);if(!$stat)throw new RuntimeException('فهرست ZIP قابل خواندن نیست.');
                $name=$stat['name'];$isDir=str_ends_with($name,'/');$this->path($isDir?substr($name,0,-1):$name);
                $fold=strtolower(rtrim($name,'/'));if(isset($seen[$fold]))throw new RuntimeException('بسته مسیر تکراری یا هم‌نام دارد.');$seen[$fold]=true;
                $opsys=0;$attr=0;
                if($zip->getExternalAttributesIndex($i,$opsys,$attr)){$kind=($attr>>16)&0170000;if($kind!==0&&$kind!==0100000&&$kind!==0040000)throw new RuntimeException('پیوند و فایل ویژه در ZIP مجاز نیست.');}
                if(!empty($stat['encryption_method']))throw new RuntimeException('ZIP رمزگذاری‌شده پشتیبانی نمی‌شود.');
                $total+=$stat['size'];if($total>40*1024*1024||$stat['size']>8*1024*1024)throw new RuntimeException('حجم بازشده بسته بیش از حد مجاز است.');
                if(!$isDir){$entries[$name]=['index'=>$i,'size'=>$stat['size']];if(basename($name)==='negah-update.json')$manifestNames[]=$name;}
            }
            if(count($manifestNames)!==1)throw new RuntimeException('از ZIP سورس جدید نگاه مدیا استفاده کنید؛ فایل شناسنامه به‌روزرسانی پیدا نشد.');
            $manifestName=$manifestNames[0];$prefix=substr($manifestName,0,-strlen('negah-update.json'));
            if($entries[$manifestName]['size']>256*1024)throw new RuntimeException('شناسنامه بسته بیش از حد بزرگ است.');
            $manifest=json_decode($zip->getFromIndex($entries[$manifestName]['index']),true,512,JSON_THROW_ON_ERROR);
            if(($manifest['format']??'')!=='negah-media-source'||($manifest['schema']??0)!==1||!is_array($manifest['files']??null))throw new RuntimeException('شناسنامه این بسته متعلق به نگاه مدیا نیست.');
            $listed=$manifest['files'];if(count($listed)>499)throw new RuntimeException('فهرست بسته معتبر نیست.');
            foreach(['index.php','admin.php','core.php','views.php','works.php','project.php','updater.php','admin-updates.php','web.config','assets/style.css','assets/home.css'] as $required)if(!isset($listed[$required]))throw new RuntimeException('بسته سورس کامل نیست: '.$required);
            foreach($entries as $name=>$entry)if($name!==$manifestName&&(!str_starts_with($name,$prefix)||!array_key_exists(substr($name,strlen($prefix)),$listed)))throw new RuntimeException('فایل ناشناخته‌ای خارج از شناسنامه بسته وجود دارد.');
            $this->mkdir($dir);$this->mkdir($dir.'/files');$changes=[];$ignored=0;$unchanged=0;
            foreach($listed as $relative=>$expected) {
                $this->path((string)$relative);
                if(!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected)||!isset($entries[$prefix.$relative]))throw new RuntimeException('فایل یا هش مورد انتظار در بسته پیدا نشد.');
                $entry=$entries[$prefix.$relative];$contents=$zip->getFromIndex($entry['index']);
                if($contents===false||strlen($contents)!==$entry['size']||!hash_equals($expected,hash('sha256',$contents)))throw new RuntimeException('بررسی یکپارچگی فایل بسته ناموفق بود.');
                if(!$this->installable($relative)){$ignored++;continue;}
                if(str_ends_with($relative,'.php')) { try{token_get_all($contents,TOKEN_PARSE);}catch(ParseError $error){throw new RuntimeException('خطای نگارشی PHP یا ناسازگاری نسخه در فایل: '.$relative);} }
                $target=$this->target($relative);if(file_exists($target)&&!is_file($target))throw new RuntimeException('مسیر مقصد فایل معمولی نیست.');
                $old=is_file($target)?hash_file('sha256',$target):null;
                if($old===$expected){$unchanged++;continue;}
                $staged=$dir.'/files/'.$relative;$this->mkdir(dirname($staged));$this->atomicWrite($staged,$contents);
                $changes[$relative]=['before'=>$old,'after'=>$expected];
            }
            $state=['id'=>$id,'created'=>time(),'name'=>substr(basename($displayName),0,160),'sha256'=>hash_file('sha256',$zipPath),'files'=>$changes,'ignored'=>$ignored,'unchanged'=>$unchanged];
            $this->writeJson($dir.'/state.json',$state);return $state;
        } catch(Throwable $error) { $this->removeTree($dir);if($error instanceof JsonException)throw new RuntimeException('شناسنامه ZIP معتبر نیست.');throw $error; }
        finally { $zip->close(); }
    }
    private function log(array $entry): void {
        $path=$this->storage.'/history.json';$history=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):[];
        array_unshift($history,$entry);$this->writeJson($path,array_slice($history,0,30));
    }
    public function history(): array {
        $path=$this->storage.'/history.json';return is_file($path)?(json_decode(file_get_contents($path),true)?:[]):[];
    }
    public function backupPath(string $id): string {
        if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new RuntimeException('نسخه پشتیبان معتبر نیست.');
        $path=$this->storage.'/backups/'.$id.'.zip';if(!is_file($path)||is_link($path))throw new RuntimeException('نسخه پشتیبان پیدا نشد.');return $path;
    }
    public function install(string $id): array {
        $this->initialize();$state=$this->pending($id);
        if(empty($state['files']))throw new RuntimeException('این بسته تغییری نسبت به نسخه فعلی ندارد.');
        $lock=fopen($this->storage.'/install.lock','c+');if(!$lock)throw new RuntimeException('قفل نصب ایجاد نشد.');
        if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);throw new RuntimeException('یک نصب دیگر در جریان است.');}
        $backupPath=$this->storage.'/backups/'.$id.'.zip';$marker=$this->root.'/data/update-maintenance.json';
        $backup=new ZipArchive();$written=[];$backupReady=false;$maintenance=false;
        try {
            if(is_file($marker))throw new RuntimeException('حالت نگهداری قبلی هنوز فعال است؛ ابتدا بازیابی را کامل کنید.');
            foreach($state['files'] as $relative=>$hashes) {
                if(!$this->installable($relative))throw new RuntimeException('تغییر داده‌های سایت مجاز نیست.');
                $target=$this->target($relative);$staged=$this->stageDir($id).'/files/'.$relative;
                if(!is_file($staged)||!hash_equals($hashes['after'],hash_file('sha256',$staged)))throw new RuntimeException('بسته آماده نصب تغییر کرده است؛ دوباره بارگذاری کنید.');
                if((is_file($target)?hash_file('sha256',$target):null)!==$hashes['before'])throw new RuntimeException('کد سایت پس از بررسی بسته تغییر کرده است؛ ZIP را دوباره بررسی کنید.');
                if(!is_dir(dirname($target))||!is_writable(dirname($target))||(is_file($target)&&!is_writable($target)))throw new RuntimeException('مجوز Modify روی فایل‌های کد و assets برای نصب لازم است.');
            }
            if($backup->open($backupPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('ساخت ZIP پشتیبان ممکن نشد.');
            foreach($state['files'] as $relative=>$hashes)if($hashes['before']!==null){if(!$backup->addFile($this->target($relative),$relative))throw new RuntimeException('افزودن فایل به پشتیبان ممکن نشد.');}
            if(!$backup->addFromString('recovery.json',json_encode(['created'=>gmdate('c'),'files'=>$state['files']],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)))throw new RuntimeException('ساخت فهرست بازیابی ممکن نشد.');
            if(!$backup->close())throw new RuntimeException('تکمیل ZIP پشتیبان ناموفق بود.');
            // Verify every backed-up byte before touching any deployed file.
            if($backup->open($backupPath,ZipArchive::CHECKCONS)!==true)throw new RuntimeException('پشتیبان قابل خواندن نیست.');
            foreach($state['files'] as $relative=>$hashes)if($hashes['before']!==null){$bytes=$backup->getFromName($relative);if($bytes===false||!hash_equals($hashes['before'],hash('sha256',$bytes)))throw new RuntimeException('بررسی نسخه پشتیبان ناموفق بود.');}
            $backupReady=true;
            $this->writeJson($marker,['id'=>$id,'started'=>gmdate('c')]);$maintenance=true;
            foreach($state['files'] as $relative=>$hashes){$this->atomicWrite($this->target($relative),file_get_contents($this->stageDir($id).'/files/'.$relative));$written[]=$relative;if(function_exists('opcache_invalidate'))@opcache_invalidate($this->target($relative),true);}
            $entry=['id'=>$id,'date'=>gmdate('c'),'name'=>$state['name'],'sha256'=>$state['sha256'],'count'=>count($written),'status'=>'success'];
            $this->log($entry);$this->discard($id);return $entry;
        } catch(Throwable $error) {
            $rollbackOk=true;
            if($backupReady)foreach(array_reverse($written) as $relative){try{if($state['files'][$relative]['before']===null){if(is_file($this->target($relative))&&!unlink($this->target($relative)))throw new RuntimeException('حذف فایل جدید ممکن نشد.');}else{$bytes=$backup->getFromName($relative);if($bytes===false)throw new RuntimeException('خواندن پشتیبان ممکن نشد.');$this->atomicWrite($this->target($relative),$bytes);}if(function_exists('opcache_invalidate'))@opcache_invalidate($this->target($relative),true);}catch(Throwable $ignored){$rollbackOk=false;}}
            if($backupReady){try{$this->log(['id'=>$id,'date'=>gmdate('c'),'name'=>$state['name'],'sha256'=>$state['sha256'],'count'=>count($written),'status'=>$rollbackOk?'rolled_back':'manual_recovery']);}catch(Throwable $ignored){}}
            if(!$rollbackOk){$maintenance=false;throw new RuntimeException('بازگردانی بعضی فایل‌ها ناموفق بود؛ حالت نگهداری فعال مانده است. پشتیبان را دریافت و با مدیر سرور بازیابی کنید.');}
            if($written)throw new RuntimeException('نصب کامل نشد و فایل‌های تغییرکرده بازگردانده شدند. '.$error->getMessage());
            throw $error;
        } finally {
            if($backup->status===ZipArchive::ER_OK){try{$backup->close();}catch(Throwable $ignored){}}
            if($maintenance&&is_file($marker))@unlink($marker);
            flock($lock,LOCK_UN);fclose($lock);
        }
    }
}
function verify_update_password(string $password): void {
    $key='update_'.hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');$blocked=false;
    update_json('attempts',function($attempts)use($key,&$blocked){$a=$attempts[$key]??['time'=>time(),'count'=>0];if(time()-$a['time']>900)$a=['time'=>time(),'count'=>0];$blocked=$a['count']>=5;$a['count']++;$attempts[$key]=$a;return $attempts;});
    if($blocked)throw new RuntimeException('تلاش‌های رمز زیاد است؛ ۱۵ دقیقه دیگر امتحان کنید.');
    $auth=read_json('auth');if(!password_verify($password,$auth['hash']))throw new RuntimeException('رمز فعلی مدیر صحیح نیست.');
    update_json('attempts',function($attempts)use($key){unset($attempts[$key]);return $attempts;});
}
