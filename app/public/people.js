'use strict';
const peoplePages={customers:{page:1,page_size:25,q:'',active:'1'},users:{page:1,page_size:25,q:'',active:'all'}};
const peopleRequests={customers:0,users:0};
function peopleToolbar(kind){const p=peoplePages[kind];return `<div class="toolbar people-toolbar"><label class="search"><input id="people-search" aria-label="Szukaj" maxlength="120" placeholder="${kind==='customers'?'Początek nazwy, NIP-u, e-maila lub telefonu':'Początek nazwiska lub loginu'}" value="${esc(p.q)}"></label>${kind==='customers'||can('users.manage')?`<label>Status<select id="people-active">${[['1','Aktywni'],['0','Nieaktywni'],['all','Wszyscy']].map(([v,l])=>`<option value="${v}" ${p.active===v?'selected':''}>${l}</option>`).join('')}</select></label>`:''}<label>Na stronie<select id="people-size">${[10,25,50,100].map(n=>`<option ${p.page_size===n?'selected':''}>${n}</option>`).join('')}</select></label></div>`;}
async function loadPeople(kind){
 const root=$('#people-results'),request=++peopleRequests[kind];if(!root)return;
 root.setAttribute('aria-busy','true');$$('[data-page]',root).forEach(b=>b.disabled=true);
 try{
  const p=await api(kind+'&'+new URLSearchParams(peoplePages[kind]));
  if(!root.isConnected||request!==peopleRequests[kind])return;
  peoplePages[kind].page=p.page;
  const list=kind==='customers'?customerRows(p.entries):teamCards(p.entries);
  root.innerHTML=list+`<nav class="pagination" aria-label="Strony ${kind==='customers'?'klientów':'pracowników'}"><span>${p.total} ${kind==='customers'?'klientów':'kont'} · Strona ${p.page} z ${p.pages}</span><div><button type="button" data-page="${p.page-1}" ${p.page===1?'disabled':''}>← Poprzednia</button><button type="button" data-page="${p.page+1}" ${p.page===p.pages?'disabled':''}>Następna →</button></div></nav>`;
  $$('[data-page]',root).forEach(b=>b.onclick=()=>{peoplePages[kind].page=+b.dataset.page;loadPeople(kind);});
  $$('[data-edit-customer]',root).forEach(b=>b.onclick=()=>editCustomer(+b.dataset.editCustomer).catch(e=>notify(e.message,true)));
  $$('[data-edit-user]',root).forEach(b=>b.onclick=()=>editUser(+b.dataset.editUser).catch(e=>notify(e.message,true)));
  $$('[data-reset-password]',root).forEach(b=>b.onclick=()=>openPasswordForm(+b.dataset.resetPassword).catch(e=>notify(e.message,true)));
 }catch(error){if(root.isConnected&&request===peopleRequests[kind])root.innerHTML=`<p class="error" role="alert">${esc(error.message)}</p><button type="button" id="people-retry">Spróbuj ponownie</button>`;$('#people-retry',root)?.addEventListener('click',()=>loadPeople(kind));}
 finally{if(root.isConnected&&request===peopleRequests[kind])root.setAttribute('aria-busy','false');}
}
function bindPeopleToolbar(kind){
 let timer;const search=$('#people-search');search.oninput=()=>{clearTimeout(timer);peopleRequests[kind]++;peoplePages[kind].q=search.value;peoplePages[kind].page=1;timer=setTimeout(()=>{if(search.isConnected)loadPeople(kind);},250);};
 $('#people-active')?.addEventListener('change',e=>{peoplePages[kind].active=e.target.value;peoplePages[kind].page=1;loadPeople(kind);});
 $('#people-size').onchange=e=>{peoplePages[kind].page_size=+e.target.value;peoplePages[kind].page=1;loadPeople(kind);};
}
function customerRows(entries){return entries.length?`<div class="table-scroll"><table><thead><tr><th>Klient</th><th>NIP</th><th>E-mail</th><th>Telefon</th><th>Adres</th><th>Status</th>${can('orders.edit')?'<th></th>':''}</tr></thead><tbody>${entries.map(c=>`<tr><td><strong>${esc(c.name)}</strong></td><td>${esc(c.tax_id||'—')}</td><td>${esc(c.email||'—')}</td><td>${esc(c.phone||'—')}</td><td class="prewrap">${esc(c.address_text||'—')}</td><td>${+c.is_active?'Aktywny':'Nieaktywny'}</td>${can('orders.edit')?`<td><button type="button" class="text-button" data-edit-customer="${c.id}">Edytuj</button></td>`:''}</tr>`).join('')}</tbody></table></div>`:'<div class="empty">Brak klientów spełniających wybrane kryteria.</div>';}
async function renderCustomers(){
 $('#content').innerHTML=pageHeader('Klienci','Dane kontaktowe i historia współpracy.',can('orders.edit')?'<button class="primary" id="new-customer">＋ Dodaj klienta</button>':'')+`<section class="panel">${peopleToolbar('customers')}<div id="people-results" aria-live="polite"></div></section>`;
 $('#new-customer')?.addEventListener('click',()=>newCustomer());bindPeopleToolbar('customers');await loadPeople('customers');
}
async function editCustomer(id){const c=await api('customer&id='+id);if(state.view==='customers')customerForm(c);}
function newCustomer(done){customerForm(null,done);}
function customerForm(customer=null,done=null){
 openModal(customer?'Edytuj klienta':'Nowy klient',`<form id="customer-form"><p class="notice">Zmiana danych klienta nie zmienia migawek danych w przyjętych zamówieniach. Nieaktywny klient pozostaje w historii i nie jest dostępny dla nowych zamówień.</p><div class="form-grid">${field('name','Nazwa firmy / imię i nazwisko','text','','required maxlength="255"')}${field('tax_id','NIP','text','','maxlength="32"')}${field('email','E-mail','email','','maxlength="254"')}${field('phone','Telefon','tel','','maxlength="50"')}<label class="full">Adres<textarea name="address_text" maxlength="5000"></textarea></label><label class="full">Uwagi<textarea name="notes" maxlength="10000"></textarea></label><label class="check-label"><input type="checkbox" name="is_active" checked> Klient aktywny</label></div>${actions(customer?'Zapisz zmiany':'Dodaj klienta')}</form>`,()=>{
  const form=$('#customer-form');if(customer){for(const key of ['name','tax_id','email','phone','address_text','notes'])form.elements[key].value=customer[key]??'';form.elements.is_active.checked=!!+customer.is_active;}
  submitForm(form,async fd=>{const data={...Object.fromEntries(fd),is_active:form.elements.is_active.checked?1:0};if(customer){data.id=customer.id;data.version=customer.version;}
   const result=await api(customer?'update_customer':'customer',data);$('#modal').close();notify(customer?'Zapisano dane klienta.':'Dodano klienta.');if(done)done(result.id);else if(state.view==='customers')await loadPeople('customers');
  });
 });
}
function teamCards(entries){return entries.length?`<div class="team-grid">${entries.map(u=>`<article class="team-card"><div class="avatar">${esc(u.display_name.slice(0,1))}</div><h3>${esc(u.display_name)}</h3><p>${esc(u.roles||'Bez roli')}</p><span class="subtext">${esc(u.login)} · ${+u.is_active?'Aktywny':'Nieaktywny'}</span><p class="subtext">${state.data.memberships.filter(m=>+m.user_id===+u.id).map(m=>esc(state.data.dictionaries.work_centers.find(c=>+c.id===+m.work_center_id)?.name)).join(' · ')||'Nie przypisano stanowisk'}</p>${can('users.manage')?`<div class="inline-actions"><button class="text-button" type="button" data-edit-user="${u.id}">Edytuj konto</button><button class="text-button" type="button" data-reset-password="${u.id}">${+u.id===+state.data.user.id?'Zmień moje hasło':'Ustaw nowe hasło'}</button></div>`:''}</article>`).join('')}</div>`:'<div class="empty">Brak kont spełniających wybrane kryteria.</div>';}
async function renderTeam(){
 $('#content').innerHTML=pageHeader('Zespół','Role, stanowiska i konta pracowników.',can('users.manage')?'<button class="primary" id="new-user">＋ Dodaj pracownika</button>':'')+`<section class="panel">${peopleToolbar('users')}<div id="people-results" aria-live="polite"></div></section>`;
 $('#new-user')?.addEventListener('click',()=>userForm());bindPeopleToolbar('users');await loadPeople('users');
}
async function editUser(id){const user=await api('user&id='+id);if(state.view==='team')userForm(user);}
function retainedChecks(list,name,selected){return checks(list.filter(x=>x.is_active===undefined||+x.is_active||selected.includes(+x.id)).map(x=>({...x,name:x.name+(x.is_active!==undefined&&!+x.is_active?' (nieaktywne)':''),is_active:1})),name,selected);}
function userForm(user=null){
 openModal(user?'Edytuj konto pracownika':'Nowy pracownik',`<form id="user-form"><p class="notice">Zmiana ról i stanowisk zachowuje wcześniejsze zadania, przydziały i raporty. Wyłączenie konta blokuje logowanie i kończy jego sesje.</p><div class="form-grid">${field('display_name','Imię i nazwisko','text',user?.display_name??'','required maxlength="200"')}${field('login','Login','text',user?.login??'','required maxlength="100" autocomplete="off"')}${user?'':field('password','Hasło (minimum 12 znaków)','password','','required minlength="12" maxlength="72" autocomplete="new-password"')}<div class="full"><label>Role</label>${checks(state.data.dictionaries.roles,'role_ids',user?.role_ids??[])}</div><div class="full"><label>Stanowiska pracy</label>${retainedChecks(state.data.dictionaries.work_centers,'work_center_ids',user?.work_center_ids??[])}</div><label class="check-label"><input type="checkbox" name="is_active" ${!user||+user.is_active?'checked':''}> Konto aktywne</label></div>${actions(user?'Zapisz konto':'Utwórz konto')}</form>`,()=>submitForm($('#user-form'),async fd=>{
  const data=Object.fromEntries(fd);data.is_active=$('#user-form [name=is_active]').checked?1:0;data.role_ids=fd.getAll('role_ids').map(Number);data.work_center_ids=fd.getAll('work_center_ids').map(Number);if(user){data.id=user.id;data.version=user.version;}
  await api(user?'update_user':'create_user',data);$('#user-form').reset();$('#modal').close();notify(user?'Zapisano konto pracownika.':'Utworzono konto pracownika.');await refresh();
 }));
}
async function openPasswordForm(id=null){
 const own=id===null||+id===+state.data.user.id,user=own?state.data.user:await api('user&id='+id);
 openModal(own?'Zmień moje hasło':'Nowe hasło · '+user.display_name,`<form id="password-form"><p class="notice">${own?'Po zmianie hasła pozostaniesz zalogowany tutaj. Pozostałe sesje Twojego konta wygasną.':'Ustawienie nowego hasła unieważni wszystkie dotychczasowe sesje tego pracownika.'}</p><div class="form-grid">${own?field('current_password','Obecne hasło','password','','required autocomplete="current-password"'):''}${field('password','Nowe hasło (minimum 12 znaków)','password','','required minlength="12" maxlength="72" autocomplete="new-password"')}${field('password_confirmation','Powtórz nowe hasło','password','','required minlength="12" maxlength="72" autocomplete="new-password"')}</div>${actions(own?'Zmień hasło':'Ustaw nowe hasło')}</form>`,()=>submitForm($('#password-form'),async fd=>{
  const data=Object.fromEntries(fd);if(data.password!==data.password_confirmation)throw Error('Nowe hasła muszą być takie same.');if(!own){data.id=user.id;data.version=user.version;}
  const result=await api(own?'change_password':'reset_password',data);state.data.csrf=result.csrf;$('#password-form').reset();$('#modal').close();notify('Hasło zostało zmienione.');
 }));
}
function bindCustomerLookup(form,order=null){
 const search=$('#customer-search',form),select=form.elements.customer_id,info=$('#customer-page-info',form);let page=1,seq=0,timer,selected=order?{id:order.customer_id,name:order.customer_name}:null,entries=[];
 const fillContacts=()=>{const c=entries.find(c=>+c.id===+select.value)||selected;form.elements.contact_email.value=c?.email??'';form.elements.contact_phone.value=c?.phone??'';};
 select.onchange=()=>{if(!select.value){selected=null;fillContacts();return;}selected=entries.find(c=>+c.id===+select.value)||selected;fillContacts();};
 const load=async(nextPage)=>{
  const request=++seq;$('#customer-prev',form).disabled=true;$('#customer-next',form).disabled=true;info.textContent='Wyszukiwanie…';
  try{
   const result=await api('customers&'+new URLSearchParams({page:nextPage,page_size:10,active:1,q:search.value}));
   if(!form.isConnected||request!==seq)return;page=result.page;entries=result.entries;
   if(!selected&&!order&&!search.value&&entries.length)selected=entries[0];
   const list=selected&&!entries.some(c=>+c.id===+selected.id)?[selected,...entries]:entries;
   select.innerHTML='<option value="">Wybierz klienta</option>'+list.map(c=>`<option value="${c.id}">${esc(c.name)}${c.tax_id?' · '+esc(c.tax_id):''}${c.is_active!==undefined&&!+c.is_active?' (nieaktywny)':''}</option>`).join('');select.value=selected?String(selected.id):'';
   if(selected&&!order&&!form.elements.contact_email.value&&!form.elements.contact_phone.value)fillContacts();
   info.textContent=`${result.total} wyników · strona ${result.page} z ${result.pages}${selected&&!entries.some(c=>+c.id===+selected.id)?' · wybrany klient zachowany poza wynikami':''}`;
   $('#customer-prev',form).disabled=result.page<=1;$('#customer-next',form).disabled=result.page>=result.pages;
  }catch(error){if(form.isConnected&&request===seq)info.textContent=error.message+' Zmień wyszukiwanie lub użyj przycisku Szukaj.';}
 };
 search.oninput=()=>{clearTimeout(timer);seq++;timer=setTimeout(()=>{if(form.isConnected)load(1);},250);};$('#customer-find',form).onclick=()=>{clearTimeout(timer);load(1);};
 $('#customer-prev',form).onclick=()=>load(page-1);$('#customer-next',form).onclick=()=>load(page+1);
 if(order)api('customer&id='+order.customer_id).then(c=>{if(form.isConnected&&+select.value===+c.id)selected=c;}).catch(()=>{});
 load(1);
}
