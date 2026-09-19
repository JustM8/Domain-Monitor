(() => {
 'use strict';
 const init = () => {
  const sidebar = document.querySelector('#portal-navigation');
  const opener = document.querySelector('.portal-nav-open');
  const backdrop = document.querySelector('.portal-nav-backdrop');
  const main = document.querySelector('.portal-main');
  const narrow = window.matchMedia('(max-width: 991.98px)');
  const close = () => { document.body.classList.remove('portal-nav-visible'); backdrop.hidden = true; main.inert = false; sidebar.inert = narrow.matches; opener.setAttribute('aria-expanded','false'); };
  if (sidebar && opener && backdrop && main) {
   opener.addEventListener('click', () => { sidebar.inert = false; document.body.classList.add('portal-nav-visible'); backdrop.hidden = false; main.inert = true; opener.setAttribute('aria-expanded','true'); (sidebar.querySelector('.portal-nav-close') || sidebar).focus(); });
   const dismiss = () => { close(); opener.focus(); };
   backdrop.addEventListener('click', dismiss);
   sidebar.querySelector('.portal-nav-close')?.addEventListener('click', dismiss);
   document.addEventListener('keydown', event => {
    if (!document.body.classList.contains('portal-nav-visible')) return;
    if (event.key === 'Escape') { event.preventDefault(); dismiss(); }
    if (event.key === 'Tab') {
     const items = [...sidebar.querySelectorAll('a[href],button:not([disabled])')].filter(el => el.getClientRects().length);
     const first = items[0], last = items[items.length-1];
     if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
     else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
   });
   narrow.addEventListener('change', close); close();
  }
  document.querySelectorAll('.portal-content table').forEach(table => { if (!table.closest('.table-responsive')) { const wrap=document.createElement('div'); wrap.className='table-responsive'; table.before(wrap); wrap.append(table); } });
  document.querySelectorAll('form.row[method=GET]').forEach(form => {
   if(form.querySelectorAll('select').length <= 2) return;
   form.classList.add('portal-filter-grid');
   const panel=document.createElement('details'), summary=document.createElement('summary');
   panel.className='portal-filter-panel'; summary.textContent='Фільтри та пошук';
   panel.open=!narrow.matches; form.before(panel); panel.append(summary,form);
   narrow.addEventListener('change',()=>{panel.open=!narrow.matches;});
  });
  document.querySelectorAll('.portal-content .table-responsive').forEach(wrap=>{
   wrap.tabIndex=0; wrap.setAttribute('role','region'); wrap.setAttribute('aria-label','Таблиця з горизонтальним прокручуванням');
   const hint=document.createElement('p'); hint.className='portal-table-hint'; hint.textContent='Прокрутіть таблицю вбік, щоб побачити всі дані та дії.'; wrap.before(hint);
  });
  const randomIndex = max => { const bytes=new Uint32Array(1), limit=Math.floor(4294967296/max)*max; do { crypto.getRandomValues(bytes); } while (bytes[0]>=limit); return bytes[0]%max; };
  const generate = () => { const sets=['ABCDEFGHJKLMNPQRSTUVWXYZ','abcdefghijkmnopqrstuvwxyz','23456789','!@#$%^&*']; const chars=sets.join(''); const result=sets.map(set=>set[randomIndex(set.length)]); while(result.length<20) result.push(chars[randomIndex(chars.length)]); for(let i=result.length-1;i>0;i--) { const j=randomIndex(i+1); [result[i],result[j]]=[result[j],result[i]]; } return result.join(''); };
  const button = (icon,label) => { const el=document.createElement('button'); el.type='button'; el.className='btn btn-outline-secondary'; el.title=label; el.setAttribute('aria-label',label); el.innerHTML='<i class="bi '+icon+'" aria-hidden="true"></i>'; return el; };
  document.querySelectorAll('input[name="password"],input[name="password_confirmation"],input[name="admin_password"],input[name="ssh_password"]').forEach(input => {
   if(input.dataset.passwordEnhanced || input.disabled) return;
   input.dataset.passwordEnhanced='true'; input.type='password'; input.autocomplete='new-password'; input.spellcheck=false;
   let group=input.parentElement;
   if(!group.classList.contains('input-group')) { group=document.createElement('div'); group.className='input-group'; input.before(group); group.append(input); }
   group.classList.add('portal-password-group');
   const show=button('bi-eye','Показати пароль'); show.setAttribute('aria-pressed','false');
   const updateShow=()=>{const visible=input.type==='text'; show.setAttribute('aria-pressed',String(visible)); show.title=visible?'Сховати пароль':'Показати пароль'; show.setAttribute('aria-label',show.title); show.firstElementChild.className='bi '+(visible?'bi-eye-slash':'bi-eye');};
   show.addEventListener('click',()=>{input.type=input.type==='password'?'text':'password'; updateShow();}); group.append(show);
   if(input.readOnly || input.name==='password_confirmation') return;
   const make=button('bi-shuffle','Згенерувати пароль'); group.append(make);
   const status=document.createElement('div'); status.className='portal-password-status'; status.setAttribute('role','status'); group.after(status);
   make.addEventListener('click',()=>{
    if(!globalThis.crypto?.getRandomValues) {status.textContent='Генератор недоступний у цьому браузері.'; return;}
    const value=generate(); input.value=value; input.dispatchEvent(new Event('input',{bubbles:true}));
    const confirmation=input.name==='password'?input.form?.querySelector('input[name="password_confirmation"]'):null;
    if(confirmation && !confirmation.readOnly && !confirmation.disabled){confirmation.value=value;confirmation.dispatchEvent(new Event('input',{bubbles:true}));}
    status.textContent='Пароль згенеровано. Зміни набудуть чинності після збереження.';
   });
  });
  document.querySelectorAll('[data-secret-toggle]').forEach(el=>{el.title='Показати / сховати пароль';el.setAttribute('aria-label',el.title);});
 };
 if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
