'use strict';
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!window.confirm(form.dataset.confirm))event.preventDefault();}));
const kmStart=document.querySelector('[name="km_start"]');const kmEnd=document.querySelector('[name="km_end"]');const distance=document.querySelector('#km-distance');
function updateDistance(){if(!distance)return;const a=Number(kmStart.value),b=Number(kmEnd.value);distance.textContent=kmStart.value!==''&&kmEnd.value!==''?(b-a).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2})+' km':'—';distance.classList.toggle('invalid',kmStart.value!==''&&kmEnd.value!==''&&b<a);}
if(kmStart&&kmEnd){kmStart.addEventListener('input',updateDistance);kmEnd.addEventListener('input',updateDistance);updateDistance();}
document.querySelectorAll('[data-suggest]').forEach(input=>{let timer,controller;input.addEventListener('input',()=>{clearTimeout(timer);controller?.abort();timer=setTimeout(async()=>{const list=document.getElementById(input.getAttribute('list'));if(input.value.trim().length<2){list.replaceChildren();return;}controller=new AbortController();try{const response=await fetch('index.php?'+new URLSearchParams({page:'suggest',kind:input.dataset.suggest,q:input.value}),{signal:controller.signal});if(!response.ok)return;const values=await response.json();list.replaceChildren(...values.map(value=>{const option=document.createElement('option');option.value=value;return option;}));}catch(error){if(error.name!=='AbortError')list.replaceChildren();}},180);});});

// Verificaciones: cada casilla tiene su propia petición y su propio estado.
(() => {
 const notice=document.getElementById('verification-notice');
 if(!notice)return;
 const feedback=document.getElementById('verification-feedback');
 let refreshBusy=false,requestNumber=0;
 function message(text,error=false){if(!feedback)return;feedback.replaceChildren();if(!text)return;const box=document.createElement('div');box.className='notice '+(error?'error':'success');box.textContent=text;feedback.append(box);}
 function apply(data,vehicleId=null){
  if(typeof data.notice==='string')notice.innerHTML=data.notice;
  if(!data.cards)return;
  if(vehicleId!==null){const node=document.querySelector('[data-verification-vehicle="'+CSS.escape(String(vehicleId))+'"]');const html=data.cards[String(vehicleId)];if(node&&typeof html==='string'){node.querySelector('.vehicle-verifications').innerHTML=html;return;}}
  document.querySelectorAll('[data-verification-vehicle]').forEach(node=>{const html=data.cards[node.dataset.verificationVehicle];if(typeof html==='string')node.querySelector('.vehicle-verifications').innerHTML=html;});
 }
 async function refresh(){
  if(refreshBusy||document.visibilityState!=='visible')return;
  refreshBusy=true;const n=++requestNumber;
  try{const r=await fetch('index.php?page=verification_status',{cache:'no-store',headers:{'Accept':'application/json'}});const data=await r.json();if(r.ok&&n===requestNumber)apply(data);}catch(_){}finally{refreshBusy=false;}
 }
 async function save(form){
  if(form.dataset.busy==='1')return;
  const box=form.querySelector('[name=completed]');
  const previous=box.checked;
  const body=new FormData(form);
  form.dataset.busy='1';form.setAttribute('aria-busy','true');box.disabled=true;
  const vehicleId=form.querySelector('[name=vehicle_id]')?.value||null;
  message('Guardando cumplimiento…');
  try{
   const r=await fetch(form.getAttribute('action'),{method:'POST',body,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},cache:'no-store'});
   let data;try{data=await r.json();}catch(_){throw new Error('La respuesta del servidor no fue válida.');}
   if(!r.ok||data.ok===false)throw new Error(data.error||'No se pudo guardar la verificación.');
   apply(data,vehicleId);message('Cumplimiento de verificación guardado.');
  }catch(error){box.checked=previous;message(error.message,true);}
  finally{if(document.body.contains(form)){form.dataset.busy='0';form.removeAttribute('aria-busy');box.disabled=false;}}
 }
 document.addEventListener('change',event=>{const form=event.target.closest('.verification-form');if(form&&event.target.name==='completed')save(form);});
 document.addEventListener('submit',event=>{if(event.target.matches('.verification-form')){event.preventDefault();save(event.target);}});
 document.documentElement.classList.add('verification-js');
 setInterval(refresh,60000);document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')refresh();});
})();

// Refresh only the shared schedule; preserve any partially completed form.
(() => {
 const panel=document.getElementById('schedule-live');if(!panel)return;
 let pending=false;
 async function refreshSchedule(){
  if(pending||document.visibilityState!=='visible'||panel.contains(document.activeElement))return;
  pending=true;
  try{
   const isPublic=panel.dataset.scheduleSource==='public';
   const response=await fetch(isPublic?'public.php?page=schedule_status':location.href,{cache:'no-store'});
   if(!response.ok)return;
   let html;
   if(isPublic)html=(await response.json()).html;
   else html=new DOMParser().parseFromString(await response.text(),'text/html').getElementById('schedule-live')?.innerHTML;
   if(typeof html==='string'&&panel.innerHTML!==html){panel.innerHTML=html;panel.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{if(!window.confirm(form.dataset.confirm))event.preventDefault();}));}
  }catch(_){/* Keep the last successful list while disconnected. */}
  finally{pending=false;}
 }
 setInterval(refreshSchedule,30000);
 document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')refreshSchedule();});
})();

const payment=document.querySelector('#payment-method');const last5=document.querySelector('[name=card_last5]');function paymentMode(){if(!payment||!last5)return;const on=payment.value==='Tarjeta';last5.required=on;last5.disabled=!on;if(!on)last5.value='';}if(payment){payment.addEventListener('change',paymentMode);paymentMode();}
