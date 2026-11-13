'use strict';
let dictionaryCatalog=null;
async function renderDictionaries(category='paper_variants') {
 if(!can('users.manage')){ $('#content').innerHTML='<div class="empty">Nie masz uprawnień do edycji słowników.</div>';return; }
 const catalog=await api('dictionary_catalog');dictionaryCatalog=catalog;
 const categories=catalog.categories;
 if(!categories[category])category='paper_variants';
 const current=categories[category];
 $('#breadcrumb').textContent='Słowniki / '+current.name;
 const columns=current.fields.filter(f=>!['textarea','permissions'].includes(f.type));
 $('#content').innerHTML=pageHeader('Słowniki','Papier, technologia i zespół — ustawienia Twojej drukarni.')+`<div class="dictionary-layout"><nav class="dictionary-nav" aria-label="Kategorie słowników">${Object.entries(categories).map(([key,c])=>`<a href="#dictionaries/${key}" class="${key===category?'active':''}"><span>${esc(c.name)}</span><b>${c.entries.length}</b></a>`).join('')}</nav><section class="panel dictionary-panel"><div class="panel-heading"><div><h2>${esc(current.name)} <span class="count">${current.entries.length}</span></h2><p class="hint" style="margin:9px 0 0">${esc(current.description)}</p></div><button id="add-dictionary" class="primary">＋ Dodaj wpis</button></div><div class="toolbar"><label class="search"><span>⌕</span><input id="dictionary-search" placeholder="Szukaj wpisu…" aria-label="Szukaj wpisu słownika"></label><span class="hint">${current.fields.some(f=>f.key==='is_active')?'Nieaktywne wpisy pozostają w historii.':'Kody istniejących wpisów są stałe.'}</span></div><div class="table-scroll"><table><thead><tr>${columns.map(f=>`<th>${esc(f.label)}</th>`).join('')}${category==='roles'?'<th>Uprawnienia</th>':''}<th></th></tr></thead><tbody id="dictionary-rows"></tbody></table></div><div id="dictionary-empty" class="empty" hidden>Brak pasujących wpisów.</div></section></div>`;
 function value(entry,f){
  const v=entry[f.key];
  if(f.type==='boolean')return f.key==='is_active'?badge(+v?'done':'cancelled',+v?'Aktywny':'Nieaktywny'):esc(+v?'Tak':'Nie');
  if(f.type==='reference')return esc(categories[f.source].entries.find(e=>+e.id===+v)?.name||'—');
  if(f.type==='select')return esc(f.options[v]||v);
  return esc(v||v===0?v:'—');
 }
 function update(){const query=$('#dictionary-search').value.trim().toLocaleLowerCase('pl');const entries=current.entries.filter(e=>current.fields.some(f=>String(e[f.key]??'').toLocaleLowerCase('pl').includes(query)));$('#dictionary-rows').innerHTML=entries.map(entry=>`<tr>${columns.map(f=>`<td>${value(entry,f)}</td>`).join('')}${category==='roles'?`<td><span class="subtext">${catalog.role_permissions.filter(r=>+r.role_id===+entry.id).map(r=>esc(catalog.permissions.find(p=>+p.id===+r.permission_id)?.name)).join('<br>')||'Brak'}</span></td>`:''}<td><button class="text-button" data-dictionary-edit="${entry.id}">Edytuj →</button></td></tr>`).join('');$('#dictionary-empty').hidden=entries.length>0;$$('[data-dictionary-edit]').forEach(b=>b.onclick=()=>editDictionary(category,current.entries.find(e=>+e.id===+b.dataset.dictionaryEdit)));}
 $('#dictionary-search').oninput=update;$('#add-dictionary').onclick=()=>editDictionary(category);update();
}
function editDictionary(category,entry=null){
 const definition=dictionaryCatalog.categories[category];
 const systemStatus=entry&&category==='order_statuses'&&['new','confirmed','production','ready','delivered','cancelled'].includes(entry.code);
 const fields=definition.fields.map(f=>{
  const v=entry?.[f.key]??f.default??'';
  if(f.type==='permissions'){
   const ids=dictionaryCatalog.role_permissions.filter(r=>+r.role_id===+entry?.id).map(r=>r.permission_id);
   const selected=entry?ids:dictionaryCatalog.permissions.filter(p=>p.code==='orders.view').map(p=>p.id);
   return `<div class="full"><label>${esc(f.label)}</label>${checks(dictionaryCatalog.permissions,'permission_ids',selected)}${entry?.code==='admin'?'<p class="hint">Administrator zachowuje pełny zestaw uprawnień.</p>':''}</div>`;
  }
  if(f.type==='boolean'){const locked=systemStatus&&['is_active','is_terminal'].includes(f.key);return `<label class="dictionary-check"><input type="checkbox" name="${f.key}" ${+v?'checked':''} ${locked?'disabled':''}>${esc(f.label)}</label>`;}
  if(f.type==='reference')return `<label>${esc(f.label)}<select name="${f.key}" required>${opts(dictionaryCatalog.categories[f.source].entries,v,'Wybierz…')}</select></label>`;
  if(f.type==='select')return `<label>${esc(f.label)}<select name="${f.key}">${Object.entries(f.options).map(([id,label])=>`<option value="${id}" ${id===v?'selected':''}>${esc(label)}</option>`).join('')}</select></label>`;
  if(f.type==='textarea')return `<label class="full">${esc(f.label)}<textarea name="${f.key}" maxlength="${f.max}">${esc(v)}</textarea></label>`;
  return field(f.key,esc(f.label),f.type==='integer'?'number':'text',v,`${f.required?'required':''} ${f.type==='integer'?`min="${f.min}" max="${f.max}" step="1"`:`maxlength="${f.max}"`} ${f.type==='code'?`pattern="[a-z][a-z0-9_]*" ${entry?'readonly':''}`:''}`);
 }).join('');
 openModal((entry?'Edytuj wpis: ':'Nowy wpis: ')+definition.name,`<form id="dictionary-form"><div class="form-grid">${fields}</div><p class="hint" style="margin-top:18px">${entry?'Zmiana nazwy lub opisu dotyczy tego wpisu słownika. Papier i rodzaj produktu zapisane w przyjętym zamówieniu zachowują swoją kopię parametrów.':'Kod identyfikuje wpis i po zapisaniu pozostaje stały.'}</p>${actions(entry?'Zapisz zmiany':'Dodaj wpis')}</form>`,()=>submitForm($('#dictionary-form'),async fd=>{
  const data={...Object.fromEntries(fd),category};if(entry)data.id=entry.id;
  for(const f of definition.fields)if(f.type==='boolean'){const input=$(`[name="${f.key}"]`,$('#dictionary-form'));data[f.key]=input.disabled?Number(entry[f.key]):Number(input.checked);}
  if(category==='roles')data.permission_ids=fd.getAll('permission_ids').map(Number);
  await api('dictionary_save',data);$('#modal').close();notify(entry?'Zapisano zmiany słownika.':'Dodano wpis do słownika.');state.data=await api('bootstrap');await renderDictionaries(category);
 }));
}
