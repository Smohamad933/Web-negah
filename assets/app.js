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
