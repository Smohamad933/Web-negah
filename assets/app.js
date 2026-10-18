const menu = document.querySelector('.menu-toggle');
const nav = document.querySelector('#navigation');
function closeMenu(returnFocus = false) {
 nav?.classList.remove('open'); menu?.setAttribute('aria-expanded', 'false');
 menu?.setAttribute('aria-label', 'باز کردن منو');
 if (returnFocus) menu?.focus();
}
menu?.addEventListener('click', () => {
 const open = nav.classList.toggle('open');
 menu.setAttribute('aria-expanded', String(open));
 menu.setAttribute('aria-label', open ? 'بستن منو' : 'باز کردن منو');
});
nav?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => closeMenu()));
document.addEventListener('click', event => { if (!event.target.closest('.header')) closeMenu(); });
document.addEventListener('keydown', event => { if (event.key === 'Escape' && nav?.classList.contains('open')) closeMenu(true); });
window.matchMedia('(min-width:801px)').addEventListener('change', event => { if (event.matches) closeMenu(); });
const sections = document.querySelectorAll('main section[id]');
if ('IntersectionObserver' in window) {
 const observer = new IntersectionObserver(entries => {
  entries.forEach(entry => {
   if (entry.isIntersecting) nav?.querySelectorAll('a').forEach(a => {
    const active = a.hash === '#' + entry.target.id;
    a.classList.toggle('active', active);
    if (active) a.setAttribute('aria-current', 'location'); else a.removeAttribute('aria-current');
   });
  });
 }, {rootMargin:'-15% 0px -55% 0px'});
 sections.forEach(s => observer.observe(s));
}
document.querySelectorAll('[data-service]').forEach(link => link.addEventListener('click', () => {
 const select = document.querySelector('select[name="service"]');
 if (select) { select.value = link.dataset.service; select.dispatchEvent(new Event('change')); }
}));
const serviceDetails = [...document.querySelectorAll('.service-detail')];
serviceDetails.forEach(detail => detail.addEventListener('toggle', () => {
 if (detail.open) serviceDetails.forEach(other => { if (other !== detail) other.open = false; });
}));
const floatingHeader = document.querySelector('.header');
if (floatingHeader) {
 const updateHeader = () => floatingHeader.classList.toggle('is-scrolled', window.scrollY > 24);
 window.addEventListener('scroll', updateHeader, {passive:true}); updateHeader();
}
const form = document.querySelector('.contact-form');
if (form) {
 const phone = form.querySelector('[name="phone"]');
 const normalizePhone = () => { phone.value = phone.value.replace(/[۰-۹٠-٩]/g,c=>String(c.charCodeAt(0)-(c>='۰'?1776:1632))).replace(/[^0-9]/g,''); };
 phone.addEventListener('input',normalizePhone);
 const message = form.querySelector('[name="message"]');
 const counter = document.querySelector('#message-counter');
 const updateCounter = () => { counter.textContent = message.value.length + ' / 3000'; };
 message.addEventListener('input',updateCounter); updateCounter();
 const submit = form.querySelector('[type="submit"]'); const initial = submit.innerHTML;
 form.addEventListener('submit', () => { normalizePhone(); if (!form.checkValidity()) return; submit.disabled = true; submit.textContent = 'در حال ارسال…'; form.setAttribute('aria-busy','true'); });
 window.addEventListener('pageshow', () => { submit.disabled = false; submit.innerHTML = initial; form.removeAttribute('aria-busy'); });
}

const partnerSection = document.querySelector('.partner-section');
const motionToggle = document.querySelector('.partner-motion-toggle');
motionToggle?.addEventListener('click', () => {
 const paused = partnerSection.classList.toggle('is-paused');
 motionToggle.setAttribute('aria-pressed', String(paused));
 motionToggle.setAttribute('aria-label', paused ? 'ادامه حرکت برندها' : 'توقف حرکت برندها');
});

// One non-modal desktop card, outside the masked ribbon so it never gets clipped.
(() => {
 const section = document.querySelector('.partner-section');
 if (!section) return;
 const media = matchMedia('(min-width:1024px) and (hover:hover) and (pointer:fine)');
 const logos = [...section.querySelectorAll('[data-brand-url]')];
 const card = document.createElement('div');
 card.className = 'partner-popover'; card.id = 'partner-client-details'; card.hidden = true;
 card.setAttribute('role','region'); card.setAttribute('aria-label','اطلاعات کارفرما');
 const kicker = document.createElement('small'); kicker.textContent = 'همراهان نگاه';
 const title = document.createElement('h3'), text = document.createElement('p'), link = document.createElement('a');
 link.textContent = 'دیدن کارهای این برند ↗'; card.append(kicker,title,text,link); document.body.append(card);
 let active = null, dismissed = null, closeTimer, openTimer, hideTimer;
 const close = () => {
  clearTimeout(openTimer); clearTimeout(closeTimer);
  if (!active && !card.classList.contains('is-visible')) return;
  active?.setAttribute('aria-expanded','false'); active = null;
  card.classList.remove('is-visible'); section.classList.remove('is-detail-open');
  clearTimeout(hideTimer); hideTimer = setTimeout(() => { card.hidden = true; },250);
 };
 const position = () => {
  const r = active.getBoundingClientRect(), w = card.offsetWidth, h = card.offsetHeight;
  card.style.left = `${Math.max(16,Math.min(innerWidth-w-16,r.left+r.width/2-w/2))}px`;
  const top = r.bottom+12+h <= innerHeight-16 ? r.bottom+12 : r.top-h-12;
  card.style.top = `${Math.max(16,Math.min(innerHeight-h-16,top))}px`;
 };
 const open = logo => {
  if (!media.matches || dismissed === logo) return;
  clearTimeout(closeTimer); clearTimeout(hideTimer); clearTimeout(openTimer);
  active?.setAttribute('aria-expanded','false'); active = logo; logo.setAttribute('aria-expanded','true');
  title.textContent = logo.dataset.brandName;
  text.textContent = logo.dataset.brandDescription || ''; text.hidden = !text.textContent;
  link.href = logo.dataset.brandUrl; card.hidden = false;
  section.classList.add('is-detail-open'); position();
  requestAnimationFrame(() => { if(active) card.classList.add('is-visible'); });
 };
 const scheduleClose = () => { clearTimeout(openTimer); clearTimeout(closeTimer); closeTimer = setTimeout(() => {
  if (!card.matches(':hover') && !card.contains(document.activeElement) && !active?.matches(':hover') && document.activeElement !== active) close();
 },220); };
 logos.forEach(logo => {
  logo.addEventListener('mouseenter', () => { if(media.matches){ clearTimeout(closeTimer); clearTimeout(openTimer); openTimer=setTimeout(()=>open(logo),100); } });
  logo.addEventListener('mouseleave', scheduleClose);
  logo.addEventListener('focus', () => open(logo)); logo.addEventListener('blur', () => { if(dismissed===logo) dismissed=null; scheduleClose(); });
  logo.addEventListener('keydown', e => {
   if (!media.matches) return;
   if(e.key==='Enter'||e.key===' '){e.preventDefault();dismissed=null;open(logo);link.focus();}
   if(e.key==='ArrowDown'&&active===logo){e.preventDefault();link.focus();}
  });
 });
 card.addEventListener('mouseenter',()=>clearTimeout(closeTimer)); card.addEventListener('mouseleave',scheduleClose);
 card.addEventListener('focusin',()=>clearTimeout(closeTimer)); card.addEventListener('focusout',scheduleClose);
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&active){const previous=active;if(card.contains(document.activeElement))previous.focus();dismissed=previous;close();}});
 document.addEventListener('pointermove',e=>{if(dismissed&&!dismissed.contains(e.target)&&!card.contains(e.target))dismissed=null;});
 document.addEventListener('pointerdown',e=>{if(active&&!card.contains(e.target)&&!active.contains(e.target))close();});
 const configure = () => { close(); logos.forEach(logo=>{
  if(media.matches&&logo.dataset.brandCopy==='0') {logo.tabIndex=0;logo.setAttribute('role','button');logo.setAttribute('aria-controls',card.id);logo.setAttribute('aria-expanded','false');}
  else {logo.removeAttribute('tabindex');logo.removeAttribute('role');logo.removeAttribute('aria-controls');logo.removeAttribute('aria-expanded');}
 }); };
 media.addEventListener('change',configure); configure();
 window.addEventListener('resize',close); window.addEventListener('scroll',close,{passive:true});
})();
