<?php
// Shared public components. Not an independently accessible endpoint.
if (!function_exists('e')) { http_response_code(404); exit; }
function brand_class(array $brand): string {
 $uid=$brand['uid']??'';
 if(!is_string($uid)||!preg_match('/^[a-f0-9]{16}$/D',$uid))$uid=substr(hash('sha256',($brand['name']??'').'|'.($brand['logo']??'')),0,16);
 return 'brand-'.$uid;
}
function public_custom_css(array $site): void {
 $css=(string)($site['custom_css']??'');
 if($css!=='')echo '<style id="public-custom-css">'.str_replace('<','\\3c ',$css).'</style>';
}
function public_responsive_guards(): void {
 echo '<style id="responsive-guards">';
 readfile(__DIR__.'/assets/responsive-guards.css');
 echo '</style>';
}
function brand_logo(array $site): void {
 $path=media_path($site['logo']??'assets/brand-logo.webp');
 if(!$path)$path='/assets/brand-logo.webp';
 echo '<img class="site-logo" src="'.e($path).'" alt="'.e($site['brand']).'" width="110" height="49">';
}
function project_key(array $project): string { return $project['uid'] ?? substr(hash('sha256', $project['title'].'|'.$project['description']),0,16); }
function project_url(array $project): string { return '/project.php?id='.rawurlencode(project_key($project)); }
function media_path(string $path): string { return preg_match('~^(?:uploads|assets)/[a-zA-Z0-9._-]+\.(?:jpg|jpeg|png|webp)$~D',$path) ? '/'.$path : ''; }
function project_art(array $project): void { ?>
<div class="project-art <?=e($project['visual'])?>"><?php if(!empty($project['image']) && media_path($project['image'])):?><img src="<?=e(media_path($project['image']))?>" alt="<?=e($project['title'])?>" loading="lazy"><?php elseif($project['visual']==='avid'):?>
<div class="avid-board"><span class="avid-small">استودیوی معماری آوید</span><span class="avid-logo">آویــد</span><span class="avid-bottom">فضایی برای متفاوت بودن</span></div><div class="avid-paper"><span class="persian-monogram">آ</span><small>معماری آوید</small></div>
<?php elseif($project['visual']==='film'):?><div class="film-orb"></div><span class="film-word">روایت</span><span class="film-small">هر قاب، یک داستان</span><span class="play-circle" aria-hidden="true">▷</span>
<?php else:?><div class="mock-browser"><div class="browser-top"><span>فرم.</span><i aria-hidden="true">● ● ●</i></div><div class="browser-body"><small>کمتر، اما بهتر</small><strong>زیبایی در<br>سادگی‌ست.</strong><span class="mock-button">کشف یک تجربه تازه ↗</span><div class="digital-sculpture"></div></div></div><?php endif;?></div>
<?php }
function project_card(array $project): void { ?>
<a class="project-card" href="<?=e(project_url($project))?>" data-category="<?=e($project['category'])?>"><?php project_art($project);?><div class="project-caption"><div><span><?=e($project['label'])?></span><h3><?=e($project['title'])?></h3></div><span class="round-arrow"><?=icon('arrow')?></span></div></a>
<?php }
function public_header(string $title, string $description, array $site): void { ?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="<?=e($description)?>"><meta name="theme-color" content="#f7f9fd"><title><?=e($title)?> — <?=e($site['brand'])?></title><link rel="icon" href="<?=e(media_path($site['logo']??'assets/brand-logo.webp'))?>"><style id="site-styles"><?php readfile(__DIR__.'/assets/style.css');readfile(__DIR__.'/assets/home.css'); ?><?php if(!empty($site['font']) && preg_match('~^uploads/[a-f0-9]+\.(woff2?|ttf)$~',$site['font'])):?>@font-face{font-family:Custom;src:url('/<?=e($site['font'])?>')}body,input,textarea,select,button{font-family:Custom,Vazirmatn,sans-serif!important}<?php endif;?></style><?php public_custom_css($site); public_responsive_guards(); ?></head><body><a class="skip-link" href="#main-content">رفتن به محتوای اصلی</a><header class="header"><div class="container nav"><a class="logo header-wordmark" href="/" aria-label="نگاه مدیا، خانه"><b><?=e($site['brand'])?></b></a><nav id="navigation" aria-label="منوی اصلی"><a href="/works.php" class="active" aria-current="page">همه کارها</a><a href="/#services">خدمات ما</a><a href="/#about">درباره ما</a></nav><a class="nav-contact" href="/#contact">شروع همکاری <?=icon('arrow')?></a><button class="menu-toggle" aria-label="باز کردن منو" aria-expanded="false" aria-controls="navigation"><?=icon('menu')?></button></div></header>
<?php }
function public_footer(array $site): void { ?>
<footer class="container footer"><a class="logo" href="/"><?php brand_logo($site);?></a><p>© <?=e($site['legal'])?>؛ تمام حقوق محفوظ است.</p><a href="/#contact">گفت‌وگو درباره پروژه ↗</a></footer><script src="/assets/app.js" defer></script></body></html>
<?php }
