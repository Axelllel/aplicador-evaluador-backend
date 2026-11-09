export function folioFromQr(value) {
  const raw = value.trim();
  try { return new URL(raw).searchParams.get('folio') ?? raw; }
  catch { return raw; }
}

export function createStudentQrScanner(video) {
  const canvas = document.createElement('canvas');
  const context = canvas.getContext('2d', {willReadFrequently: true});
  let stream;
  let generation = 0;
  let frame;

  const stop = () => {
    generation++;
    cancelAnimationFrame(frame);
    stream?.getTracks().forEach(track => track.stop());
    stream = null;
    video.srcObject = null;
    video.hidden = true;
  };

  const start = async ({onCode, onStatus, onError = onStatus}) => {
    stop();
    if (!navigator.mediaDevices?.getUserMedia || !context) {
      onError('La cámara no está disponible aquí. Puedes escribir el folio.');
      return;
    }

    const scanId = generation;
    onStatus('Preparando cámara…');
    try {
      const {default: decode} = await import('jsqr');
      if (scanId !== generation) return;
      const camera = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});
      if (scanId !== generation) {
        camera.getTracks().forEach(track => track.stop());
        return;
      }

      stream = camera;
      video.srcObject = camera;
      video.hidden = false;
      await video.play();
      if (scanId !== generation) return;
      onStatus('Apunta al código QR de tu docente.');

      let lastScan = 0;
      const scan = now => {
        if (scanId !== generation) return;
        if (video.readyState >= 2 && video.videoWidth && now - lastScan >= 150) {
          lastScan = now;
          const scale = Math.min(1, 640 / video.videoWidth);
          const width = Math.round(video.videoWidth * scale);
          const height = Math.round(video.videoHeight * scale);
          if (canvas.width !== width || canvas.height !== height) {
            canvas.width = width;
            canvas.height = height;
          }
          let code;
          try {
            context.drawImage(video, 0, 0, width, height);
            const pixels = context.getImageData(0, 0, width, height);
            code = decode(pixels.data, width, height);
          } catch {
            stop();
            onError('No fue posible leer el QR. Puedes escribir el folio.');
            return;
          }
          if (code) {
            stop();
            onCode(folioFromQr(code.data));
            return;
          }
        }
        frame = requestAnimationFrame(scan);
      };
      frame = requestAnimationFrame(scan);
    } catch {
      if (scanId !== generation) return;
      stop();
      onError('No fue posible usar la cámara. Puedes escribir el folio.');
    }
  };

  return {start, stop};
}
