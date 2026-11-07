/** BigBitByte Portal estudiantil. Las operaciones reales pasan por Laravel + Supabase. */
const root = document.getElementById('bbb-student-app');
if (root) {
  const $ = id => document.getElementById(id);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const urls = root.dataset;
  const theme = () => {
    const dark = localStorage.getItem('bbb-theme') === 'dark';
    root.classList.toggle('sh-dark', dark);
    document.querySelectorAll('[data-theme-toggle]').forEach(b => b.textContent = dark ? '☀' : '☾');
  };
  theme();
  document.querySelectorAll('[data-theme-toggle]').forEach(b => b.addEventListener('click', () => {
    localStorage.setItem('bbb-theme', root.classList.contains('sh-dark') ? 'light' : 'dark'); theme();
  }));
  const request = async (url, data={}) => {
    const res = await fetch(url, {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify(data)});
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw Error(json.message || `Operación rechazada (${res.status})`);
    return json.data ?? json;
  };
  const dialog = id => { const d=$(id); if (d && !d.open) d.showModal(); };
  const close = id => { if ($(id)?.open) $(id).close(); };
  if (urls.screen === 'home') {
    const menu=$('bbb-menu-toggle'), side=$('student-navigation');
    if(menu && side) menu.addEventListener('click',()=>{side.hidden=!side.hidden; root.classList.toggle('sh-sidebar-open',!side.hidden);menu.setAttribute('aria-expanded', String(!side.hidden));});
    const grantBtn=$('bbb-feature-exam'), launch=$('bbb-instr-go');
    grantBtn?.addEventListener('click',()=>dialog('bbb-instructions'));
    $('bbb-close-grant')?.addEventListener('click',()=>close('bbb-instructions'));
    $('bbb-cancel-grant')?.addEventListener('click',()=>close('bbb-instructions'));
    launch?.addEventListener('click',async()=>{
      const pase=$('bbb-grant-input').value.trim(); const output=$('bbb-grant-error');
      output.hidden=true;
      if(!pase){output.textContent='Ingresa el pase de tu docente.';output.hidden=false;return;}
      launch.disabled=true;launch.textContent='Verificando pase…';
      try { const data=await request(urls.start,{pase});location.assign(data.url || urls.exam); }
      catch(e){output.textContent=e.message;output.hidden=false;launch.disabled=false;launch.textContent='Iniciar examen →';}
    });
  }
  if (urls.screen === 'exam') {
    const typeNames={opcion_multiple:'Opción múltiple',relacion_columnas:'Relación de columnas',abierta:'Respuesta abierta',programacion:'Programación'};
    let questions=[],current=0,answers={},saving=false,pendingSave=null,deadline=0,finished=false;
    const attemptId=new URLSearchParams(location.search).get('intento') || document.querySelector('.es-exam-name strong')?.textContent || 'current';
    const cacheKey=`bbb-real-answers-${attemptId}`;
    try { const saved=JSON.parse(sessionStorage.getItem(cacheKey)||'{}'); if(saved && typeof saved==='object') answers=saved; } catch {};
    const cache = () => { try{sessionStorage.setItem(cacheKey,JSON.stringify(answers))}catch{} };
    const answered = q => (typeof answers[q.examen_pregunta_id]==='string' && answers[q.examen_pregunta_id].trim().length>0);
    const progress = () => {
      const n=questions.filter(answered).length, total=questions.length;
      $('bbb-answered-count').textContent=`${n} de ${total}`;
      $('bbb-progress').style.width=`${total?100*n/total:0}%`;
      $('bbb-percent').textContent=`${total?Math.round(100*n/total):0}% completado`;
      $('bbb-q-nav').innerHTML=questions.map((q,i)=>`<button type="button" class="es-question-number ${i===current?'es-current':''} ${answered(q)?'es-answered':''}" data-q-go="${i}">${i+1}</button>`).join('');
    };
    const options = v => { if(Array.isArray(v))return v.map(x=>typeof x==='string'?x:JSON.stringify(x)); if(v && typeof v==='object')return Object.values(v).flatMap(x=>Array.isArray(x)?x.map(y=>typeof y==='string'?y:JSON.stringify(y)):[]); return []; };
    const formValue = q => {
      const type=q.tipo;
      if(type==='opcion_multiple') return $('bbb-q-input').querySelector('input[name="answer"]:checked')?.value ?? '';
      if(type==='relacion_columnas') { const vals=[...$('bbb-q-input').querySelectorAll('select')].map(x=>x.value);return vals.some(Boolean)?JSON.stringify(vals):''; }
      return $('bbb-free-answer')?.value ?? '';
    };
    const save = async () => {
      if (pendingSave) return pendingSave;
      const q=questions[current]; if(!q || finished)return true;
      const value=formValue(q), old=answers[q.examen_pregunta_id] || '';
      if(value===old)return true;
      if(!value.trim()){ $('bbb-save-state').textContent='Escribe una respuesta antes de cambiar de pregunta';return false; }
      saving=true;$('bbb-save-state').textContent='Guardando en Supabase…';
      pendingSave=(async()=>{
        try {
          await request(urls.answer,{examen_pregunta_id:q.examen_pregunta_id,respuesta_texto:value});
          answers[q.examen_pregunta_id]=value;cache();
          $('bbb-save-state').textContent='✓ Respuesta guardada en Supabase'; progress();return true;
        } catch(e){ $('bbb-save-state').textContent=`Error al guardar: ${e.message}`;return false; }
        finally {saving=false;}
      })();
      try {return await pendingSave;} finally {pendingSave=null;}
    };
    const render = () => {
      const q=questions[current]; if(!q)return;
      $('bbb-q-current').textContent=current+1;$('bbb-total-label').textContent=`de ${questions.length}`;
      $('bbb-q-position').textContent=`Pregunta ${current+1} de ${questions.length}`;
      $('bbb-question-type').textContent=typeNames[q.tipo]||q.tipo;
      $('bbb-q-label').textContent=`PREGUNTA ${String(current+1).padStart(2,'0')}`;
      $('bbb-question-heading').textContent=q.enunciado || 'Pregunta sin enunciado';
      $('bbb-q-hint').textContent='Tu respuesta se guardará en Supabase antes de avanzar.';
      $('bbb-q-status').textContent=answered(q)?'Respondida':'Pendiente de responder';
      const answer=answers[q.examen_pregunta_id]||'', opts=options(q.opciones);
      if(q.tipo==='opcion_multiple') {
        $('bbb-q-input').innerHTML=`<fieldset class="es-options"><legend class="sr-only">Selecciona una respuesta</legend>${opts.map((o,i)=>`<label class="es-option"><input type="radio" name="answer" value="${esc(o)}" ${answer===o?'checked':''}><span class="es-option-letter">${String.fromCharCode(65+i)}</span><span>${esc(o)}</span></label>`).join('')}</fieldset>`;
      } else if(q.tipo==='relacion_columnas') {
        const pairings=q.opciones && typeof q.opciones==='object' && !Array.isArray(q.opciones) ? q.opciones:{};
        const left=pairings.izquierda||pairings.items||pairings.columnas_a||[];
        const right=pairings.derecha||pairings.choices||pairings.columnas_b||opts;
        let old=[];try{old=JSON.parse(answer)}catch{};
        $('bbb-q-input').innerHTML=`<div class="bbb-match-grid">${(Array.isArray(left)&&left.length?left:['Relación 1']).map((s,i)=>`<label class="bbb-match-row"><strong>${esc(s)}</strong><select aria-label="Seleccionar relación ${i+1}"><option value="">Selecciona</option>${right.map(v=>`<option value="${esc(v)}" ${old[i]===v?'selected':''}>${esc(v)}</option>`).join('')}</select></label>`).join('')}</div>`;
      } else {
        $('bbb-q-input').innerHTML=`<div class="es-open-answer"><label for="bbb-free-answer">Tu respuesta</label><textarea class="bbb-question-edit ${q.tipo==='programacion'?'bbb-code':''}" id="bbb-free-answer" rows="8" placeholder="Escribe aquí tu respuesta"></textarea></div>`;
        $('bbb-free-answer').value=answer;
      }
      $('bbb-prev').disabled=current===0;$('bbb-next').disabled=current===questions.length-1;
      progress();
    };
    const goto=async pos=>{ if(pos<0||pos>=questions.length)return; if(!await save())return;current=pos;render();scrollTo({top:0,behavior:'smooth'});};
    $('bbb-prev').addEventListener('click',()=>goto(current-1));
    $('bbb-next').addEventListener('click',()=>goto(current+1));
    $('bbb-q-nav').addEventListener('click',e=>{const button=e.target.closest('[data-q-go]');if(button)goto(Number(button.dataset.qGo));});
    $('bbb-q-input').addEventListener('change',()=>save());
    $('bbb-q-input').addEventListener('focusout',e=>{if(e.target?.id==='bbb-free-answer')save();});
    const review=async()=>{
      if(!await save())return;
      const n=questions.filter(answered).length;
      $('bbb-review-done').textContent=n;$('bbb-review-pending').textContent=questions.length-n;
      $('bbb-review-list').innerHTML=questions.map((q,i)=>`<button class="bbb-review-question" type="button" data-review-q="${i}"><strong>${i+1}</strong><div>Pregunta ${i+1}<br><span>${answered(q)?'✓ Respondida':'• Sin responder'}</span></div></button>`).join('');
      $('bbb-review-warning').textContent=n<questions.length?`Quedan ${questions.length-n} preguntas sin responder. Puedes entregar después de confirmarlo.`:'Todas las preguntas registradas.';
      dialog('bbb-review');
    };
    $('bbb-review-open').addEventListener('click',review);
    $('bbb-review-list').addEventListener('click',e=>{const button=e.target.closest('[data-review-q]');if(button){close('bbb-review');goto(Number(button.dataset.reviewQ));}});
    document.querySelectorAll('[data-review-close]').forEach(b=>b.addEventListener('click',()=>close('bbb-review')));
    $('bbb-submit').addEventListener('click',()=>{close('bbb-review');dialog('bbb-confirm')});
    $('bbb-confirm-back').addEventListener('click',()=>{close('bbb-confirm');review();});
    $('bbb-confirm-submit').addEventListener('click',async()=>{
      const btn=$('bbb-confirm-submit');btn.disabled=true;btn.textContent='Registrando entrega…';$('bbb-finish-error').hidden=true;
      try{await request(urls.finish);finished=true;close('bbb-confirm');dialog('bbb-done-dialog');}
      catch(e){$('bbb-finish-error').textContent=e.message;$('bbb-finish-error').hidden=false;btn.disabled=false;btn.textContent='Sí, entregar';}
    });
    const tick=()=>{if(!deadline||finished)return;const remaining=Math.max(0,Math.ceil((deadline-Date.now())/1000));const m=Math.floor(remaining/60),s=remaining%60;$('bbb-time').textContent=`${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;if(!remaining){$('bbb-time').textContent='00:00';$('bbb-save-state').textContent='Tiempo estimado agotado: entrega el examen ahora.';}};
    request(urls.questions).then(data=>{
      questions=Array.isArray(data.preguntas)?data.preguntas:[];
      if(!questions.length){$('bbb-question-heading').textContent='Este examen no tiene preguntas disponibles.';return;}
      const totalSeconds=questions.reduce((sum,q)=>sum+Math.max(0,Number(q.tiempo_segundos)||0),0);
      // Temporizador informativo; las reglas y horarios reales los valida PostgreSQL.
      deadline=(Date.parse(urls.started)||Date.now())+totalSeconds*1000;
      current=0;$('bbb-save-state').textContent='Preguntas cargadas de Supabase';render();tick();setInterval(tick,1000);
    }).catch(e=>{$('bbb-question-heading').textContent='No fue posible recuperar las preguntas';$('bbb-save-state').textContent=e.message;});
  }
  if (urls.screen === 'grades' && $('bbb-result-title')) {
    request(urls.result).then(data=>{
      $('bbb-result-title').textContent=data.estado==='final'?'Calificación final':'Calificación provisional';
      $('bbb-result-score').hidden=false;
      $('bbb-result-score-val').textContent=data.calificacion;
      $('bbb-score-scale').textContent=`/ ${data.escala==='0-10'?'10':'100'}`;
      $('bbb-result-description').textContent=data.estado==='final'?'Evaluación terminada.':'Calificación aproximada: aún pueden existir preguntas pendientes de revisión.';
      $('bbb-graded-count').textContent=`${data.calificadas} de ${data.total_preguntas} preguntas calificadas`;
      const pending=Math.max(0,data.total_preguntas-data.calificadas);
      $('bbb-graded-pending').textContent=pending?`${pending} preguntas pendientes de revisión`:'Revisión completada';
      $('bbb-graded-progress').style.width=`${data.total_preguntas?100*data.calificadas/data.total_preguntas:0}%`;
    }).catch(()=>{
      $('bbb-result-title').textContent='Resultado pendiente de publicación';
      $('bbb-result-description').textContent='El examen está entregado, pero la calificación aún no está autorizada o completa. Consulta nuevamente cuando tu docente la publique.';
      $('bbb-result-score').hidden=true;
      $('bbb-graded-count').textContent='El docente controla la publicación de resultados';
    });
  }
}
