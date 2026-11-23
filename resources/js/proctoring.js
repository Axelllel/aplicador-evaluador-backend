import { ObservationWindow } from './proctoring-state';
/** Cámara bajo permiso explícito. El video se analiza en un worker y no se transmite. */
export function startProctoring({send, active, button, status}) {
  let stream,worker,timer,busy=false,starting=false,video;
  const queue=[],last=new Map();
  const show = text => { status.textContent=text; };
  const flush=async()=>{
    if(busy || !active() || !navigator.onLine)return;
    busy=true;
    try { while(queue.length && active()){await send(queue[0]);queue.shift();} }
    catch { show('Hay incidencias pendientes de enviar. Se reintentará al recuperar la conexión.'); }
    finally {busy=false;}
  };
  const record = event => {
    if(!active())return;
    const now=performance.now(),previous=last.get(event.tipo_alerta);
    if(previous!==undefined && now-previous<30000)return;
    last.set(event.tipo_alerta,now);
    if(queue.length<50)queue.push(event);
    void flush();
  };
  const stop=()=>{
    clearInterval(timer); worker?.terminate(); worker=null;
    stream?.getTracks().forEach(track=>track.stop()); stream=null;
    if(video){video.srcObject=null;video.remove();video=null;}
    button.textContent='Activar cámara';button.disabled=!active();
  };
  const observations=new ObservationWindow();
  const begin=async()=>{
    if(starting || stream)return;
    starting=true;button.disabled=true;show('Solicitando permiso de cámara…');
    try {
      if(!navigator.mediaDevices?.getUserMedia || !globalThis.createImageBitmap)throw new Error('unsupported');
      stream=await navigator.mediaDevices.getUserMedia({video:{width:{ideal:320},height:{ideal:240},facingMode:'user'},audio:false});
      if(!active()){stop();return;}
      video=document.createElement('video');video.muted=true;video.playsInline=true;video.srcObject=stream;await video.play();
      show('Preparando la supervisión de cámara…');
      worker=new Worker(new URL('./proctoring-worker.js',import.meta.url));
      const ready=new Promise((resolve,reject)=>{
        const timeout=setTimeout(()=>reject(new Error('initialization')),30000);
        worker.onmessage=({data})=>{
          if(data.ready){clearTimeout(timeout);resolve();}
          else if(data.error){clearTimeout(timeout);reject(new Error('detector'));stop();show('La supervisión de cámara no está disponible. Puedes continuar el examen.');}
          else if(data.faces){
            const event=observations.observe(data.faces,data.timestamp);if(event)record(event);
          }
        };
        worker.onerror=()=>{clearTimeout(timeout);reject(new Error('worker'));stop();show('La supervisión de cámara no está disponible. Puedes continuar el examen.');};
      });
      worker.postMessage({action:'init',assets:new URL('/build/proctoring',location.origin).href});await ready;
      if(!active()){stop();return;}
      button.textContent='Cámara activa';show('Cámara activa. El video permanece en este dispositivo.');
      let framePending=false;
      const original=worker.onmessage;worker.onmessage=event=>{framePending=false;original(event);};
      timer=setInterval(async()=>{
        if(!active()){stop();return;}
        if(framePending || document.hidden || video.readyState<2)return;
        framePending=true;
        try { const frame=await createImageBitmap(video);if(!worker){frame.close();return;}worker.postMessage({frame,timestamp:performance.now()},[frame]); }
        catch{framePending=false;}
      },2000);
      stream.getVideoTracks().forEach(track=>track.addEventListener('ended',()=>{record({tipo_alerta:'camara_desactivada',nivel_severidad:'baja'});stop();show('La cámara se desactivó. Puedes intentar activarla otra vez.');}));
    } catch(error) {
      stop();show(error.name==='NotAllowedError'?'No se autorizó la cámara. Puedes continuar; la incidencia se registra.':'No fue posible activar la cámara. Puedes continuar el examen.');
      record({tipo_alerta:'camara_desactivada',nivel_severidad:'baja'});
    } finally {starting=false;button.disabled=!active() || Boolean(stream);}
  };
  button.addEventListener('click',begin);
  const retry=setInterval(flush,15000);
  addEventListener('online',flush);
  addEventListener('offline',()=>record({tipo_alerta:'conexion',nivel_severidad:'baja'}));
  addEventListener('pagehide',stop,{once:true});
  return {record,stop:()=>{stop();clearInterval(retry);}};
}
