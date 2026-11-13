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
 root.insertAdjacentHTML('beforeend',componentEditor());const el=root.lastElementChild;bindComponent(el);el.dataset.componentId=c.id;$('[data-remove-component]',el).hidden=true;
 editorValue($('[data-key=print_technique]',el),c.print_technique);$('[data-key=print_technique]',el).dispatchEvent(new Event('change'));
 for(const input of $$('[data-key]',el))editorValue(input,c[input.dataset.key],input.dataset.key==='paper_variant_id'?c.paper_description:'');
 if(c.print_technique==='offset')for(const side of ['front','back']){
  $('[data-key=offset_'+side+'_colors]',el).dispatchEvent(new Event('change'));
  const inks=c.inks.filter(i=>i.side===side);$$('[data-ink='+side+']',el).forEach((input,i)=>input.value=inks[i]?.ink_name??'');
 }
 for(const f of features.filter(f=>f.scope==='component'&&+f.component_id===+c.id))restoreFeature($('[data-component-features]',el),f,'component');
}
function restoreItem(root,item){
 root.insertAdjacentHTML('beforeend',itemEditor());const el=root.lastElementChild;bindItem(el);el.dataset.itemId=item.id;$('[data-remove-item]',el).hidden=true;
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
 openModal(editing?'Edytuj zamówienie · '+o.order_number:'Nowe zamówienie',`<form id="order-form"><div class="notice">${editing?'Edytujesz dane zamówienia i specyfikację produktów. Istniejące części oraz historia pracy zostają zachowane. Zmiana nakładu lub terminu zamówienia nie zmienia automatycznie planu istniejących zadań.':'Opisz produkty i ich części. Zadania oraz wykonawców zaplanujesz w karcie zamówienia.'}</div><div class="form-grid"><label>Klient<select name="customer_id" required>${opts(state.data.customers)}</select><small>Nowego klienta dodasz w zakładce Klienci.</small></label>${field('title','Tytuł zamówienia','text','','required maxlength="255"')}${field('due_at','Termin realizacji (Twój czas lokalny)','datetime-local')}<label>Priorytet<select name="priority">${Object.entries(priorities).map(([v,l])=>`<option value="${v}" ${v==='normal'?'selected':''}>${l}</option>`).join('')}</select></label>${field('contact_name','Osoba kontaktowa','text','','maxlength="200"')}${field('contact_email','E-mail kontaktowy','email','','maxlength="254"')}${field('contact_phone','Telefon kontaktowy','tel','','maxlength="50"')}<label class="full">Dodatkowe informacje do zamówienia<textarea name="customer_notes" maxlength="10000" placeholder="Ustalenia z klientem, wymagania, sposób przygotowania…"></textarea></label><label class="full">Notatki wewnętrzne<textarea name="internal_notes" maxlength="10000" placeholder="Informacje organizacyjne dla zespołu…"></textarea></label></div><div id="items-editor"></div><button type="button" id="add-item">＋ Dodaj kolejny produkt</button>${actions(editing?'Zapisz zmiany':'Przyjmij zamówienie')}</form>`,()=>{
  const form=$('#order-form'),root=$('#items-editor');
  const add=()=>{root.insertAdjacentHTML('beforeend',itemEditor());bindItem(root.lastElementChild);};$('#add-item').onclick=add;
  const contactDefaults=()=>{const c=state.data.customers.find(c=>+c.id===+form.elements.customer_id.value);form.elements.contact_email.value=c?.email??'';form.elements.contact_phone.value=c?.phone??'';};
  form.elements.customer_id.onchange=contactDefaults;
  if(editing){
   for(const key of ['customer_id','title','priority','contact_name','contact_email','contact_phone','customer_notes','internal_notes'])editorValue(form.elements[key],o[key],o.customer_name);
   if(o.due_at){const time=new Date(o.due_at.replace(' ','T')+'Z');form.elements.due_at.value=new Date(time.getTime()-time.getTimezoneOffset()*60000).toISOString().slice(0,16);}
   for(const item of detail.items)restoreItem(root,item);
  }else{contactDefaults();add();}
  submitForm(form,async()=>{
   const data=collectOrder(form);if(editing){data.id=o.id;data.version=o.version;}
   const r=await api(editing?'update_order':'create_order',data);$('#modal').close();notify(editing?'Zapisano dane zamówienia i części.':'Zamówienie przyjęte. Zaplanuj etapy produkcji.');location.hash='order/'+r.id;await refresh();
  });
  if(focus){const target=$(`[data-${focus.type}-id="${focus.id}"]`,form);target?.scrollIntoView({block:'start'});}
 });
}
