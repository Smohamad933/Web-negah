(() => {
 const dialog = document.querySelector('#gallery-dialog');
 const links = [...document.querySelectorAll('[data-lightbox]')];
 if (!dialog || !links.length || typeof dialog.showModal !== 'function') return;
 let current = 0;
 const image = dialog.querySelector('figure img');
 const caption = dialog.querySelector('figcaption');
 const counter = dialog.querySelector('.lightbox-counter');
 const buttons = [...dialog.querySelectorAll('[data-lightbox-step]')];
 function show(index) {
  current = Math.max(0,Math.min(links.length - 1,index));
  image.src = links[current].href;
  image.alt = links[current].querySelector('img').alt;
  caption.textContent = links[current].dataset.caption;
  counter.textContent = (current + 1).toLocaleString('fa-IR') + ' / ' + links.length.toLocaleString('fa-IR');
  buttons.forEach(button => button.disabled = Number(button.dataset.lightboxStep) < 0 ? current === 0 : current === links.length - 1);
 }
 links.forEach((link,index) => link.addEventListener('click',event => {
  event.preventDefault(); show(index); dialog.showModal(); document.body.classList.add('has-modal');
 }));
 buttons.forEach(button => button.addEventListener('click',() => show(current + Number(button.dataset.lightboxStep))));
 dialog.querySelector('.lightbox-close').addEventListener('click',() => dialog.close());
 dialog.addEventListener('close',() => document.body.classList.remove('has-modal'));
 dialog.addEventListener('keydown',event => {
  if(event.key === 'ArrowLeft'){event.preventDefault();show(current + 1);}
  if(event.key === 'ArrowRight'){event.preventDefault();show(current - 1);}
 });
 dialog.addEventListener('click',event => {
  if(event.target !== dialog)return;
  const r = dialog.getBoundingClientRect();
  if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)dialog.close();
 });
})();
