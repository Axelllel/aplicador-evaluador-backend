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
  const account = root.querySelector('.sh-account');
  if (account) {
    document.addEventListener('click', event => {
      if (!account.contains(event.target)) account.open = false;
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && account.open) {
        const focusWasInside = account.contains(document.activeElement);
        account.open = false;
        if (focusWasInside) account.querySelector('summary').focus();
      }
    });
  }
  const request = async (url, data={}) => {
    const res = await fetch(url, {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify(data)});
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw Object.assign(Error(json.message || `Operación rechazada (${res.status})`), {status: res.status});
    return json.data ?? json;
  };
  const dialog = id => { const d=$(id); if (d && !d.open) d.showModal(); };
  const close = id => { if ($(id)?.open) $(id).close(); };
  if (urls.screen === 'home' || urls.screen === 'grades') {
    const menu=$('bbb-menu-toggle'), side=$('student-navigation');
    if (menu && side) {
      const setMenu = open => {
        side.hidden = !open;
        root.classList.toggle('sh-sidebar-open', open);
        menu.setAttribute('aria-expanded', String(open));
      };
      menu.addEventListener('click', () => setMenu(side.hidden));
      $('bbb-menu-close')?.addEventListener('click', () => {
        setMenu(false);
        menu.focus();
      });
      side.querySelectorAll('a[href^="#"]').forEach(link => link.addEventListener('click', () => {
        if (window.matchMedia('(max-width: 1050px)').matches) {
          setMenu(false);
          $(link.hash.slice(1))?.focus();
        }
      }));
    }
    $('bbb-groups-open')?.addEventListener('click', () => dialog('bbb-groups-dialog'));
  }
  if (urls.screen === 'home') {
    const details = $('bbb-exam-details');
    if (details) {
      const localDate = new Intl.DateTimeFormat('es-MX', {dateStyle: 'medium', timeStyle: 'short'});
      const launch = $('bbb-start-exam');
      const output = $('bbb-start-error');
      const existing = $('bbb-exam-details-existing');
      root.querySelectorAll('[data-exam-details]').forEach(card => card.addEventListener('click', () => {
        const {examDetails, examName, examAvailable, examOpens, examCloses, examDuration, examQuestions} = card.dataset;
        const available = examAvailable === '1';
        const state = $('bbb-exam-details-state');
        $('bbb-exam-details-title').textContent = examName;
        state.textContent = available ? 'Disponible' : 'Próximamente';
        state.parentElement.classList.toggle('ei-state-available', available);
        $('bbb-exam-details-upcoming').hidden = available;
        if (existing) existing.hidden = !available;
        output.hidden = true;
        if (launch) {
          launch.dataset.examId = examDetails;
          launch.hidden = !available;
        }

        $('bbb-exam-details-facts').hidden = !examOpens;
        if (examOpens) {
          const seconds = Number(examDuration);
          const parts = [];
          if (seconds >= 60) parts.push(`${Math.floor(seconds / 60)} min`);
          if (seconds % 60) parts.push(`${seconds % 60} s`);
          $('bbb-exam-details-duration').textContent = parts.join(' ') || 'Sin duración estimada';
          $('bbb-exam-details-questions').textContent = examQuestions;
          $('bbb-exam-details-schedule').textContent = `${localDate.format(new Date(examOpens))} — ${localDate.format(new Date(examCloses))}`;
        }
        dialog('bbb-exam-details');
        details.scrollTop = 0;
      }));
      const keepOpenWhileStarting = event => {
        if (launch?.disabled) event.preventDefault();
      };
      details.addEventListener('cancel', keepOpenWhileStarting);
      details.addEventListener('submit', keepOpenWhileStarting);
      launch?.addEventListener('click', async () => {
        if (launch.disabled || !launch.dataset.examId) return;
        output.hidden = true;
        launch.disabled = true;
        launch.textContent = 'Iniciando examen…';
        try {
          const data = await request(urls.start, {examen_id: launch.dataset.examId});
          location.assign(data.url || urls.exam);
        } catch (error) {
          output.textContent = error.message;
          output.hidden = false;
          output.scrollIntoView({block:'nearest'});
          launch.disabled = false;
          launch.textContent = 'Iniciar examen →';
        }
      });
    }
  }
  const typeNames={opcion_multiple:'Opción múltiple',relacion_columnas:'Relación de columnas',abierta:'Respuesta abierta',programacion:'Programación'};
  if (urls.screen === 'exam') {
    let questions=[], current=0, answers={}, pendingSave=null, deadline=0, finished=false, navigating=false;
    const cacheKey=`bbb-attempt-answers-${urls.attempt}`;
    try { const saved=JSON.parse(sessionStorage.getItem(cacheKey)||'{}'); if(saved && typeof saved==='object') answers=saved; } catch {};
    const cache = () => { try{sessionStorage.setItem(cacheKey,JSON.stringify(answers))}catch{} };
    const matchingValues = answer => {
      try {
        const values = JSON.parse(answer);
        return Array.isArray(values) ? values : [];
      } catch { return []; }
    };
    const leftOptions = q => q.opciones?.izquierda ?? q.opciones?.items ?? q.opciones?.columnas_a ?? [];
    const answered = q => {
      const answer = answers[q.examen_pregunta_id];
      if (typeof answer !== 'string' || !answer.trim()) return false;
      if (q.tipo !== 'relacion_columnas') return true;
      const values = matchingValues(answer);
      return values.length >= Math.max(1, leftOptions(q).length) && values.every(value => typeof value === 'string' && value.trim());
    };
    const progress = () => {
      const n=questions.filter(answered).length, total=questions.length;
      $('bbb-answered-count').textContent=`${n} de ${total}`;
      $('bbb-progress').style.width=`${total?100*n/total:0}%`;
      $('bbb-percent').textContent=`${total?Math.round(100*n/total):0}% completado`;
      $('bbb-review-count').textContent = `${n} respondidas · ${total - n} pendientes`;
      const done = answered(questions[current]);
      $('bbb-q-status-label').textContent = done ? 'Respondida' : 'Pendiente de responder';
      $('bbb-q-status').classList.toggle('es-status-done', done);
      $('bbb-q-nav').innerHTML=questions.map((q,i)=>`<button type="button" class="es-question-number ${i===current?'es-current':''} ${answered(q)?'es-answered':''}" data-q-go="${i}" aria-current="${i===current?'step':'false'}" aria-label="Pregunta ${i+1}, ${answered(q)?'respondida':'pendiente'}">${i+1}</button>`).join('');
    };
    const options = v => { if(Array.isArray(v))return v.map(x=>typeof x==='string'?x:JSON.stringify(x)); if(v && typeof v==='object')return Object.values(v).flatMap(x=>Array.isArray(x)?x.map(y=>typeof y==='string'?y:JSON.stringify(y)):[]); return []; };
    const formValue = q => {
      const type=q.tipo;
      if(type==='opcion_multiple') return $('bbb-q-input').querySelector('input[name="answer"]:checked')?.value ?? '';
      if(type==='relacion_columnas') { const vals=[...$('bbb-q-input').querySelectorAll('select')].map(x=>x.value);return vals.some(Boolean)?JSON.stringify(vals):''; }
      return $('bbb-free-answer')?.value ?? '';
    };
    const save = async () => {
      if (pendingSave) {
        if (!await pendingSave) return false;
        return save();
      }
      const q=questions[current]; if(!q || finished)return true;
      const value=formValue(q), old=answers[q.examen_pregunta_id] || '';
      if(value===old)return true;
      if(!value.trim()){ $('bbb-save-state').textContent='Escribe un reemplazo para la respuesta guardada.';return false; }
      $('bbb-save-state').textContent='Guardando respuesta…';
      pendingSave=(async()=>{
        try {
          await request(urls.answer,{examen_pregunta_id:q.examen_pregunta_id,respuesta_texto:value});
          answers[q.examen_pregunta_id]=value;cache();
          $('bbb-save-state').textContent='✓ Respuesta guardada'; progress();return true;
        } catch(e){ $('bbb-save-state').textContent=`Error al guardar: ${e.message}`;return false; }
      })();
      let saved;
      try { saved = await pendingSave; } finally { pendingSave = null; }
      return saved && (questions[current] !== q || await save());
    };
    const render = () => {
      const q=questions[current]; if(!q)return;
      $('bbb-q-current').textContent=current+1;$('bbb-total-label').textContent=`de ${questions.length}`;
      $('bbb-q-position').textContent=`Pregunta ${current+1} de ${questions.length}`;
      $('bbb-question-type').textContent=typeNames[q.tipo]||q.tipo;
      $('bbb-q-label').textContent=`PREGUNTA ${String(current+1).padStart(2,'0')}`;
      $('bbb-question-heading').textContent=q.enunciado || 'Pregunta sin enunciado';
      const hints = {
        opcion_multiple: 'Selecciona una sola respuesta. Puedes cambiar tu elección antes de entregar.',
        relacion_columnas: 'Selecciona la opción que corresponde a cada elemento.',
        abierta: 'Escribe tu respuesta con tus propias palabras.',
        programacion: 'Escribe tu solución en el editor de código.',
      };
      $('bbb-q-hint').textContent = hints[q.tipo] ?? '';
      const answer=answers[q.examen_pregunta_id]||'', opts=options(q.opciones);
      if(q.tipo==='opcion_multiple') {
        $('bbb-q-input').innerHTML=`<fieldset class="es-options"><legend class="sr-only">Selecciona una respuesta</legend>${opts.map((o,i)=>`<label class="es-option has-checked:border-blue-400 has-checked:bg-[var(--sh-pale)]"><input type="radio" name="answer" value="${esc(o)}" ${answer===o?'checked':''}><span class="es-option-letter">${String.fromCharCode(65+i)}</span><span>${esc(o)}</span></label>`).join('')}</fieldset>`;
      } else if(q.tipo==='relacion_columnas') {
        const pairings=q.opciones && typeof q.opciones==='object' && !Array.isArray(q.opciones) ? q.opciones:{};
        const left=leftOptions(q);
        const right=pairings.derecha||pairings.choices||pairings.columnas_b||opts;
        const old=matchingValues(answer);
        $('bbb-q-input').innerHTML=`<div class="bbb-match-grid">${(Array.isArray(left)&&left.length?left:['Relación 1']).map((s,i)=>`<label class="bbb-match-row"><strong>${esc(s)}</strong><select aria-label="Seleccionar relación ${i+1}"><option value="">Selecciona</option>${right.map(v=>`<option value="${esc(v)}" ${old[i]===v?'selected':''}>${esc(v)}</option>`).join('')}</select></label>`).join('')}</div>`;
      } else {
        $('bbb-q-input').innerHTML=`<div class="es-open-answer"><label for="bbb-free-answer">Tu respuesta</label><textarea class="bbb-question-edit ${q.tipo==='programacion'?'bbb-code':''}" id="bbb-free-answer" rows="8" placeholder="Escribe aquí tu respuesta"></textarea></div>`;
        $('bbb-free-answer').value=answer;
      }
      $('bbb-prev').disabled=current===0;$('bbb-next').disabled=current===questions.length-1;
      progress();
    };
    const goto = async pos => {
      if (navigating || pos < 0 || pos >= questions.length) return;
      navigating = true;
      try {
        if (!await save()) return;
        current = pos;
        render();
        $('bbb-question-heading').focus({preventScroll:true});
        scrollTo({top:0,behavior:'smooth'});
      } finally { navigating = false; }
    };
    $('bbb-prev').addEventListener('click',()=>goto(current-1));
    $('bbb-next').addEventListener('click',()=>goto(current+1));
    $('bbb-q-nav').addEventListener('click',e=>{const button=e.target.closest('[data-q-go]');if(button)goto(Number(button.dataset.qGo));});
    $('bbb-q-input').addEventListener('change',()=>save());
    $('bbb-q-input').addEventListener('focusout',e=>{if(e.target?.id==='bbb-free-answer')save();});
    const review=async()=>{
      if(navigating || !questions.length || !await save())return;
      const n=questions.filter(answered).length;
      const pending = questions.length - n;
      $('bbb-review-exam-name').textContent = $('bbb-active-exam-title').textContent;
      $('bbb-review-total').textContent=questions.length;
      $('bbb-review-done').textContent=n;$('bbb-review-pending').textContent=pending;
      $('bbb-review-list').innerHTML=questions.map((q,i)=>`<li><button class="er-question ${answered(q)?'er-answered':''} ${i===current?'border-blue-300':''}" type="button" data-review-q="${i}"><span class="er-number">${i+1}</span><span class="er-question-label">Pregunta ${i+1}<span><i></i>${answered(q)?'Respondida':'Pendiente'}</span></span><span class="ml-auto" aria-hidden="true">→</span></button></li>`).join('');
      $('bbb-review-warning-box').hidden = pending === 0;
      $('bbb-review-warning').textContent = pending === 1 ? 'Queda 1 pregunta pendiente' : `Quedan ${pending} preguntas pendientes`;
      dialog('bbb-review');
      $('bbb-review').scrollTop = 0;
    };
    $('bbb-review-open').addEventListener('click',review);
    $('bbb-review-list').addEventListener('click',e=>{const button=e.target.closest('[data-review-q]');if(button){close('bbb-review');goto(Number(button.dataset.reviewQ));}});
    document.querySelectorAll('[data-review-close]').forEach(b=>b.addEventListener('click',()=>close('bbb-review')));
    $('bbb-submit').addEventListener('click',()=>{
      const pending = questions.length - questions.filter(answered).length;
      $('bbb-confirm-summary').textContent = pending ? `Entregarás con ${pending} pendiente(s). La entrega es definitiva.` : 'Tus respuestas quedarán registradas y ya no podrás modificarlas.';
      close('bbb-review');
      dialog('bbb-confirm');
    });
    $('bbb-confirm-back').addEventListener('click',()=>{close('bbb-confirm');review();});
    $('bbb-confirm-submit').addEventListener('click',async()=>{
      const btn=$('bbb-confirm-submit');btn.disabled=true;btn.textContent='Registrando entrega…';$('bbb-finish-error').hidden=true;
      try{
        await request(urls.finish);
        finished=true;
        try { sessionStorage.removeItem(cacheKey); } catch {}
        close('bbb-confirm');
        dialog('bbb-done-dialog');
      }
      catch(e){$('bbb-finish-error').textContent=e.message;$('bbb-finish-error').hidden=false;btn.disabled=false;btn.textContent='Sí, entregar';}
    });
    $('bbb-done-dialog').addEventListener('cancel', event => event.preventDefault());
    const tick=()=>{if(!deadline||finished)return;const remaining=Math.max(0,Math.ceil((deadline-Date.now())/1000));const m=Math.floor(remaining/60),s=remaining%60;$('bbb-time').textContent=`${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;};
    request(urls.questions).then(data=>{
      questions=Array.isArray(data.preguntas)?data.preguntas:[];
      if(!questions.length){$('bbb-question-heading').textContent='Este examen no tiene preguntas disponibles.';return;}
      if (data.nombre) $('bbb-active-exam-title').textContent = data.nombre;
      if (data.respuestas) { answers = data.respuestas; cache(); }
      const totalSeconds=questions.reduce((sum,q)=>sum+Math.max(0,Number(q.tiempo_segundos)||0),0);
      // Temporizador informativo; las reglas y horarios reales los valida PostgreSQL.
      if (totalSeconds) {
        deadline=(Date.parse(urls.started)||Date.now())+totalSeconds*1000;
        tick();
        setInterval(tick,1000);
      }
      $('bbb-review-open').disabled = false;
      $('bbb-save-state').textContent='Puedes revisar tus respuestas antes de entregar.';
      render();
    }).catch(e=>{$('bbb-question-heading').textContent='No fue posible recuperar las preguntas';$('bbb-save-state').textContent=e.message;});
  }
  if (urls.screen === 'grades' && $('bbb-result-title')) {
    const status = $('bbb-result-status');
    const title = $('bbb-result-title');
    const description = $('bbb-result-description');
    const pendingPublication = () => {
      status.textContent = 'En revisión';
      title.textContent = 'El resultado aún no está publicado';
      description.textContent = 'La calificación aparecerá cuando tu docente habilite el resultado y termine la revisión correspondiente.';
    };
    request(urls.result).then(data=>{
      const visible = data.calificacion != null;
      if (data.nombre) $('bbb-result-exam-name').textContent = data.nombre;
      if (data.fecha_entrega) {
        const date = $('bbb-result-date');
        date.textContent = `· Entregado el ${new Intl.DateTimeFormat('es-MX', {dateStyle:'medium'}).format(new Date(data.fecha_entrega))}`;
        date.hidden = false;
      }
      if (visible) {
        status.textContent = data.estado === 'final' ? 'Resultado final' : 'Resultado provisional';
        title.textContent = data.estado === 'final' ? 'Calificación final' : 'Calificación provisional';
        $('bbb-result-score').hidden = false;
        $('bbb-result-score-val').textContent = data.calificacion;
        $('bbb-score-scale').textContent = `/ ${data.escala === '0-10' ? '10' : '100'}`;
        description.textContent = data.estado === 'final' ? 'La revisión del examen está completa.' : 'La calificación puede cambiar cuando se complete la revisión.';
      } else {
        pendingPublication();
      }
      $('bbb-review-progress').hidden = false;
      $('bbb-graded-count').textContent=`${data.calificadas} de ${data.total_preguntas} preguntas procesadas`;
      const pending=Math.max(0,data.total_preguntas-data.calificadas);
      $('bbb-graded-pending').textContent=pending?`${pending} preguntas pendientes de revisión`:'Revisión completada';
      $('bbb-graded-progress').style.width=`${data.total_preguntas?100*data.calificadas/data.total_preguntas:0}%`;
      if (Array.isArray(data.preguntas)) {
        const groups = {objective: [], manual: []};
        data.preguntas.forEach((question, index) => {
          const group = question.tipo === 'opcion_multiple' ? 'objective' : 'manual';
          const label = !visible ? 'Pendiente de publicación' : question.respondida === false
            ? 'Sin responder · 0/100' : question.puntaje == null ? 'Pendiente de revisión' : `${question.puntaje}/100`;
          groups[group].push(`<article class="sg-question"><div class="sg-question-top"><span class="sg-question-number">${index + 1}</span><div><h4>Pregunta ${index + 1}</h4><span class="sg-question-type">${esc(typeNames[question.tipo] ?? question.tipo)}</span></div><span class="sg-question-points ${!visible || question.puntaje == null ? 'sg-ungraded' : ''}">${esc(label)}</span></div><p class="sg-question-prompt">${esc(question.enunciado)}</p></article>`);
        });
        for (const [group, cards] of Object.entries(groups)) {
          $(`bbb-${group}-count`).textContent = cards.length;
          $(`bbb-result-${group}`).innerHTML = cards.join('') || '<p class="sg-empty-category">No hay preguntas de este tipo.</p>';
        }
        $('bbb-result-questions').hidden = false;
      }
    }).catch(error=>{
      if (error.status === 403) {
        pendingPublication();
      } else {
        status.textContent = 'Sin conexión con el resultado';
        title.textContent = 'No pudimos consultar el resultado';
        description.textContent = 'Recarga la página para volver a intentarlo.';
      }
    });
  }
}
