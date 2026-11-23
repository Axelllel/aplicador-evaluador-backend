import { readFile, mkdir, copyFile, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
const destination = new URL('../public/build/proctoring/', import.meta.url);
await mkdir(destination, { recursive: true });
const model = Buffer.from(await readFile(new URL('../resources/models/blaze_face_short_range.tflite.base64', import.meta.url), 'utf8'), 'base64');
if (createHash('sha256').update(model).digest('hex') !== 'b4578f35940bf5a1a655214a1cce5cab13eba73c1297cd78e1a04c2380b0152f') throw new Error('Modelo de proctoring alterado.');
await writeFile(new URL('blaze_face_short_range.tflite', destination), model);
for (const name of ['vision_wasm_internal.js','vision_wasm_internal.wasm','vision_wasm_nosimd_internal.js','vision_wasm_nosimd_internal.wasm']) {
  await copyFile(new URL(`../node_modules/@mediapipe/tasks-vision/wasm/${name}`, import.meta.url), new URL(name, destination));
}
console.log('Proctoring: modelo verificado y recursos locales preparados.');
