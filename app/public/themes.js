'use strict';
const themes={green:{name:'Zielony',description:'Obecny wygląd pracowni',bar:'#172c28',color:'#265b48',background:'#f7f8f4'},beige:{name:'Beżowy',description:'Ciepły papier i piaskowe akcenty',bar:'#3b3028',color:'#77563b',background:'#f7f1e7'},beige_light:{name:'Jasny beż',description:'Kremowe tła i jasny panel boczny',bar:'#f7efdf',color:'#806041',background:'#fffcf7'},gray:{name:'Szary',description:'Neutralne szarości i spokojne kontrasty',bar:'#292d32',color:'#505b67',background:'#f3f4f5'},black:{name:'Czarny',description:'Ciemne tła i jasna typografia',bar:'#101113',color:'#b8c2cc',background:'#141618'}};
function applyTheme(theme){
 if(!Object.hasOwn(themes,theme))theme='green';
 document.documentElement.dataset.theme=theme;
 document.documentElement.style.colorScheme=theme==='black'?'dark':'light';
 document.querySelector('meta[name="theme-color"]').content=themes[theme].bar;
}
function appearance(){
 const saved=state.data.user.theme||'green';
 openModal('Wygląd aplikacji',`<form id="appearance-form"><p class="hint">Wybierz motyw dla swojego konta. Ustawienie będzie dostępne po ponownym zalogowaniu, również na innym urządzeniu.</p><div class="theme-options">${Object.entries(themes).map(([key,t])=>`<label class="theme-option"><input type="radio" name="theme" value="${key}" ${key===saved?'checked':''}><span class="theme-preview" style="--preview-bg:${t.background};--preview-bar:${t.bar};--preview-accent:${t.color}"><i></i><b></b><em></em></span><strong>${t.name}</strong><small>${t.description}</small></label>`).join('')}</div><p class="hint">Zmiana wyboru pokazuje podgląd. „Zapisz motyw” zapisuje ustawienie na Twoim koncie.</p>${actions('Zapisz motyw')}</form>`,()=>{
  const dialog=$('#modal');
  const restore=()=>applyTheme($('#app').hidden?'green':state.data?.user.theme||'green');dialog.addEventListener('close',restore,{once:true});
  $$('[name=theme]',$('#appearance-form')).forEach(input=>input.onchange=()=>applyTheme(input.value));
  submitForm($('#appearance-form'),async fd=>{
   const result=await api('save_theme',{theme:fd.get('theme')});state.data.user.theme=result.theme;applyTheme(result.theme);dialog.close();notify('Zapisano motyw Twojego konta.');
  });
 });
}
document.querySelector('#appearance').addEventListener('click',appearance);
