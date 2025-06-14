import torch
from ultralytics import YOLO
import base64
import io
from PIL import Image

model = YOLO("api-PWAPP/best.pt")  # or absolute path
model.fuse()

def classify_image(base64_img):
    img = Image.open(io.BytesIO(base64.b64decode(base64_img.split(',')[-1])))
    results = model(img)[0]
    label = results.names[results.probs.top1]
    confidence = results.probs.top1conf.item()
    print({"label": label, "confidence": confidence})
    return {"label": label, "confidence": confidence}
