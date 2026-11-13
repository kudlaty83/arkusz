'use strict';
function editorValue(control,value,label='') {
 if(control.tagName==='SELECT'&&value!==null&&value!==undefined&&!Array.from(control.options).some(o=>o.value===String(value))){
  const option=document.createElement('option');option.value=value;option.textContent=label||String(value);control.append(option);
 }
 control.value=value??'';
}
function restoreFeature(root,f,scope){
 root.insertAdjacentHTML('beforeend',featureEditor(scope));const el=root.lastElementChild;bindFeature(el);el.dataset.featureId=f.id;
 editorValue($('.feature-type',el),f.feature_type_id,f.feature_name);$('.feature-type',el).dispatchEvent(new Event('change'));
 $('.feature-notes',el).value=f.notes??'';
 for(const v of f.values){const input=$('[data-param="'+v.code+'"]',el);if(input)input.value=v.value_text??v.value_number??v.value_boolean??'';}
}
function restoreComponent(root,c,features){
 root.insertAdjacentHTML('beforeend',componentEditor());const el=root.lastElementChild;bindComponent(el);el.dataset.componentId=c.id;
 editorValue($('[data-key=print_technique]',el),c.print_technique);$('[data-key=print_technique]',el).dispatchEvent(new Event('change'));
 for(const input of $$('[data-key]',el))editorValue(input,c[input.dataset.key],input.dataset.key==='paper_variant_id'?c.paper_description:'');
 if(c.print_technique==='offset')for(const side of ['front','back']){
  $('[data-key=offset_'+side+'_colors]',el).dispatchEvent(new Event('change'));
  const inks=c.inks.filter(i=>i.side===side);$$('[data-ink='+side+']',el).forEach((input,i)=>input.value=inks[i]?.ink_name??'');
 }
 for(const f of features.filter(f=>f.scope==='component'&&+f.component_id===+c.id))restoreFeature($('[data-component-features]',el),f,'component');
}
function restoreItem(root,item){
 root.insertAdjacentHTML('beforeend',itemEditor());const el=root.lastElementChild;bindItem(el);el.dataset.itemId=item.id;
 for(const input of $$('[data-item-key]',el))editorValue(input,item[input.dataset.itemKey],item.product_type_name);
 $('[data-components]',el).innerHTML='';for(const c of item.components)restoreComponent($('[data-components]',el),c,item.features);
 for(const f of item.features.filter(f=>f.scope==='item'))restoreFeature($('[data-item-features]',el),f,'item');
}
function collectOrder(form){
 const data=Object.fromEntries(new FormData(form));
 if(data.due_at)data.due_at=new Date(data.due_at).toISOString().slice(0,16);
 data.items=$$('[data-item]',form).map(el=>{
  const item=Object.fromEntries($$('[data-item-key]',el).map(i=>[i.dataset.itemKey,i.value]));if(el.dataset.itemId)item.id=+el.dataset.itemId;
  item.features=collectFeatures($('[data-item-features]',el));
  item.components=$$('[data-component]',el).map(c=>{
   const component=Object.fromEntries($$('[data-key]',c).map(i=>[i.dataset.key,i.value]));if(c.dataset.componentId)component.id=+c.dataset.componentId;
   for(const side of ['front','back'])component[side+'_inks']=$$('[data-ink='+side+']',c).map(i=>i.value);
   component.features=collectFeatures($('[data-component-features]',c));return component;
  });return item;
 });return data;
}
function openOrderEditor(detail=null,focus=null){
 const editing=!!detail,o=detail?.order;
 openModal(editing?'Edytuj zamówienie · '+o.order_number:'Nowe zamówienie',`<form id="order-form"><div class="notice">${editing?'Uwaga: zmiana nakładu, terminu lub specyfikacji wpłynie na plan produkcji. Usuwanie elementów może anulować zadania i etapy zależne. Przed zapisem zobaczysz dokładne podsumowanie do zatwierdzenia. Raporty pozostaną w historii.':'Opisz produkty i ich części. Zadania oraz wykonawców zaplanujesz w karcie zamówienia.'}</div><fieldset data-order-fields style="border:0;padding:0;margin:0;min-width:0"><div class="form-grid"><div class="customer-lookup full"><label>Szukaj klienta<input id="customer-search" type="search" maxlength="120" placeholder="Początek nazwy, NIP-u, e-maila lub telefonu" aria-controls="customer-select"></label><label>Klient<select id="customer-select" name="customer_id" required><option value="">Wybierz klienta</option></select></label><div class="lookup-pagination"><button type="button" id="customer-find">Szukaj</button><button type="button" id="customer-prev" disabled>←</button><span id="customer-page-info" role="status"></span><button type="button" id="customer-next" disabled>→</button></div><small>Wyniki są pobierane po 10 wpisów. Nowego klienta dodasz w zakładce Klienci.</small></div>${field('title','Tytuł zamówienia','text','','required maxlength="255"')}${field('due_at','Termin realizacji (Twój czas lokalny)','datetime-local')}<label>Priorytet<select name="priority">${Object.entries(priorities).map(([v,l])=>`<option value="${v}" ${v==='normal'?'selected':''}>${l}</option>`).join('')}</select></label>${field('contact_name','Osoba kontaktowa','text','','maxlength="200"')}${field('contact_email','E-mail kontaktowy','email','','maxlength="254"')}${field('contact_phone','Telefon kontaktowy','tel','','maxlength="50"')}<label class="full">Dodatkowe informacje do zamówienia<textarea name="customer_notes" maxlength="10000" placeholder="Ustalenia z klientem, wymagania, sposób przygotowania…"></textarea></label><label class="full">Notatki wewnętrzne<textarea name="internal_notes" maxlength="10000" placeholder="Informacje organizacyjne dla zespołu…"></textarea></label></div><div id="items-editor"></div><button type="button" id="add-item">＋ Dodaj kolejny produkt</button></fieldset><section id="order-plan-preview" class="notice" hidden tabindex="-1" aria-labelledby="plan-preview-title"></section>${actions(editing?'Sprawdź zmiany w planie':'Przyjmij zamówienie')}</form>`,()=>{
  const form=$('#order-form'),root=$('#items-editor');
  const add=()=>{root.insertAdjacentHTML('beforeend',itemEditor());bindItem(root.lastElementChild);};$('#add-item').onclick=add;
  if(editing){
   for(const key of ['customer_id','title','priority','contact_name','contact_email','contact_phone','customer_notes','internal_notes'])editorValue(form.elements[key],o[key],o.customer_name);
   if(o.due_at){const time=new Date(o.due_at.replace(' ','T')+'Z');form.elements.due_at.value=new Date(time.getTime()-time.getTimezoneOffset()*60000).toISOString().slice(0,16);}
   for(const item of detail.items)restoreItem(root,item);
  }else{add();}
  bindCustomerLookup(form,o);
  const finish=async(data)=>{
   const r=await api(editing?'update_order':'create_order',data);$('#modal').close();notify(editing?'Zapisano zamówienie i zatwierdzone zmiany planu.':'Zamówienie przyjęte. Zaplanuj etapy produkcji.');location.hash='order/'+r.id;await refresh();
  };
  submitForm(form,async()=>{
   const data=collectOrder(form);if(!editing){await finish(data);return;}
   data.id=o.id;data.version=o.version;
   const preview=await api('preview_order_update',data),panel=$('#order-plan-preview',form),fields=$('[data-order-fields]',form),submit=$('button[type=submit]',form);
   fields.disabled=true;submit.hidden=true;
   panel.innerHTML=`<h3 id="plan-preview-title">Sprawdź i zatwierdź zmiany</h3><p>Zmiany obejmą ${preview.task_count} zadań. Terminy poniżej są podane w UTC. Sam podgląd nie zapisuje zmian.</p>${preview.changes.length?`<ul>${preview.changes.map(change=>`<li>${esc(change)}</li>`).join('')}</ul>`:'<p>Brak zmian w danych i planie produkcji.</p>'}<p>Nowa rewizja technologiczna oznacza ponowne wykonanie według zmienionej specyfikacji. Stare raporty pozostają w historii i nie zwiększają wykonania tej rewizji. Dodane produkty, części i cechy wymagają doboru operacji i wykonawców w karcie zamówienia.</p><div class="form-actions"><button type="button" id="back-to-order">Wróć do edycji</button><button type="button" class="primary" id="confirm-order-plan">Zatwierdź i zapisz zmiany</button></div>`;
   panel.hidden=false;panel.focus();panel.scrollIntoView({block:'start'});
   $('#back-to-order',panel).onclick=()=>{fields.disabled=false;submit.hidden=false;panel.hidden=true;$('.error',form).textContent='';};
   $('#confirm-order-plan',panel).onclick=async()=>{
    const buttons=$$('button',panel);buttons.forEach(b=>b.disabled=true);$('.error',form).textContent='';
    try{await finish({...data,confirmation_token:preview.confirmation_token});}catch(error){$('.error',form).textContent=error.message;}finally{buttons.forEach(b=>b.disabled=false);}
   };
  });
  if(focus){const target=$(`[data-${focus.type}-id="${focus.id}"]`,form);target?.scrollIntoView({block:'start'});}
 });
}
