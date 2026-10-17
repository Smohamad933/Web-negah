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
window.matchMedia('(min-width:601px)').addEventListener('change', event => { if (event.matches) closeMenu(); });
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
const dialog = document.querySelector('#project-dialog');
document.querySelectorAll('.project-card').forEach(card => card.addEventListener('click', () => {
 dialog.querySelector('h2').textContent = card.dataset.title;
 dialog.querySelector('#project-description').textContent = card.dataset.description;
 dialog.querySelector('.section-kicker').textContent = card.dataset.label;
 const preview = card.querySelector('.project-art').cloneNode(true);
 preview.removeAttribute('id');
 dialog.querySelector('.dialog-art').replaceChildren(preview);
 dialog.showModal(); document.body.classList.add('has-modal');
}));
dialog?.querySelector('.dialog-close').addEventListener('click', () => dialog.close());
dialog?.addEventListener('close', () => document.body.classList.remove('has-modal'));
dialog?.addEventListener('click', event => {
 if (event.target !== dialog) return;
 const r = dialog.getBoundingClientRect();
 if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close();
});
document.querySelector('#dialog-contact')?.addEventListener('click', () => dialog.close());
const serviceDetails = [...document.querySelectorAll('.service-detail')];
serviceDetails.forEach(detail => detail.addEventListener('toggle', () => {
 if (detail.open) serviceDetails.forEach(other => { if (other !== detail) other.open = false; });
}));
const floatingHeader = document.querySelector('.header');
if (floatingHeader) {
 const updateHeader = () => floatingHeader.classList.toggle('is-scrolled', window.scrollY > 24);
 window.addEventListener('scroll', updateHeader, {passive:true}); updateHeader();
}
const gallery = document.querySelector('.projects-grid');
const galleryButtons = document.querySelectorAll('[data-gallery]');
if (gallery && galleryButtons.length) {
 const cards = [...gallery.querySelectorAll('.project-card')];
 function nearestCard() {
  const edge = gallery.getBoundingClientRect().right;
  let index = 0, distance = Infinity;
  cards.forEach((card,i) => { const d = Math.abs(card.getBoundingClientRect().right - edge); if (d < distance) {distance = d; index = i;} });
  return index;
 }
 function updateGalleryControls() {
  const current = nearestCard();
  galleryButtons.forEach(button => button.disabled = button.dataset.gallery === 'previous' ? current === 0 : current === cards.length - 1);
 }
 galleryButtons.forEach(button => button.addEventListener('click', () => {
  const direction = button.dataset.gallery === 'next' ? 1 : -1;
  const target = cards[Math.max(0,Math.min(cards.length - 1,nearestCard() + direction))];
  if (target) gallery.scrollBy({left:target.getBoundingClientRect().right - gallery.getBoundingClientRect().right,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
 }));
 gallery.addEventListener('scroll', updateGalleryControls, {passive:true});
 window.addEventListener('resize',updateGalleryControls); updateGalleryControls();
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
