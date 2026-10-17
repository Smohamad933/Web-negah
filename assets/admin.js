document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => { if(!window.confirm(form.dataset.confirm)) event.preventDefault(); }));

// Show progress only after confirmation and built-in validation have passed.
document.querySelectorAll('form[data-update-form]').forEach(form => form.addEventListener('submit',event => {
 if(event.defaultPrevented || !form.checkValidity())return;
 const button=form.querySelector('button[type=submit]');
 if(button){button.disabled=true;button.textContent='در حال پردازش…';form.setAttribute('aria-busy','true');}
}));
window.addEventListener('pageshow',event=>{if(event.persisted)location.reload();});
