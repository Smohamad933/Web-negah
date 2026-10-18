<?php
require __DIR__.'/core.php'; require __DIR__.'/views.php';
header('Cache-Control: no-store');
$categories=['all'=>'همه کارها','branding'=>'هویت بصری','film'=>'محتوا و تصویر','digital'=>'وب و اپلیکیشن','campaign'=>'کمپین و رویداد'];
$category=is_string($_GET['category']??null)?$_GET['category']:'all';
if(!isset($categories[$category])) $category='all';
$brandKey=is_string($_GET['brand']??null)?$_GET['brand']:'';$selectedBrand=null;
foreach(($site['brands']??[]) as $candidate)if(brand_class($candidate)===$brandKey)$selectedBrand=$candidate;
if($brandKey&&!$selectedBrand)http_response_code(404);
$projects=array_values(array_filter($site['projects'],fn($p)=>($category==='all'||$p['category']===$category)&&(!$brandKey||($selectedBrand&&(($p['brand_id']??'')===$brandKey||(empty($p['brand_id'])&&trim($p['client']??'')===$selectedBrand['name']))))));
public_header('همه کارها','منتخب پروژه‌ها و زبان طراحی نگاه مدیا در برندینگ، تصویر، طراحی وب و کمپین.',$site);
?><main id="main-content" class="container portfolio-page"><nav class="breadcrumbs" aria-label="مسیر صفحه"><a href="/">خانه</a><span>/</span><span>همه کارها</span></nav><div class="portfolio-heading"><div><span class="small-label">دنیای نگاه مدیا</span><h1><?=$brandKey?($selectedBrand?'آثار '.e($selectedBrand['name']):'برند پیدا نشد.'):'از نگاه، به اثر.'?></h1></div><p>هر پروژه، یک داستان مستقل.<br>برای دیدن جزئیات و گالری، وارد هر اثر شوید.</p></div><nav class="portfolio-filters" aria-label="دسته‌بندی آثار"><?php foreach($categories as $key=>$label):?><a href="/works.php<?=e('?'.http_build_query(array_filter(['category'=>$key==='all'?'':$key,'brand'=>$brandKey])))?>" class="<?=$category===$key?'selected':''?>" <?=$category===$key?'aria-current="page"':''?>><?=e($label)?></a><?php endforeach;?></nav><?php if($projects):?><div class="projects-grid portfolio-grid"><?php foreach($projects as $project)project_card($project);?></div><?php else:?><div class="portfolio-empty"><h2>هنوز اثری در این بخش منتشر نشده.</h2><a class="button portfolio-outline" href="/works.php">دیدن همه کارها</a></div><?php endif;?><div class="portfolio-bottom-cta"><h2>پروژه بعدی، شاید برای شما باشد.</h2><a class="button primary" href="/#contact">شروع گفت‌وگو <?=icon('arrow')?></a></div></main><?php public_footer($site); ?>
