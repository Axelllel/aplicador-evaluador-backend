import { FilesetResolver, FaceDetector } from '@mediapipe/tasks-vision';
let detector;
self.onmessage = async ({data}) => {
  try {
    if (data.action === 'init') {
      const files = await FilesetResolver.forVisionTasks(data.assets);
      detector = await FaceDetector.createFromOptions(files, {baseOptions:{modelAssetPath:`${data.assets}/blaze_face_short_range.tflite`,delegate:'CPU'},runningMode:'VIDEO',minDetectionConfidence:.7});
      self.postMessage({ready:true});
    } else if (data.frame && detector) {
      try {
        const result=detector.detectForVideo(data.frame,data.timestamp);
        self.postMessage({timestamp:data.timestamp,faces:result.detections.map(face=>({x:(face.boundingBox.originX+face.boundingBox.width/2)/data.frame.width,y:(face.boundingBox.originY+face.boundingBox.height/2)/data.frame.height,confidence:face.categories[0]?.score || 0}))});
      } finally { data.frame.close(); }
    }
  } catch { self.postMessage({error:true}); }
};
