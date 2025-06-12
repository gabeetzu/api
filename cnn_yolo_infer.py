from ultralytics import YOLO
import sys
import json
from PIL import Image

model = YOLO('api-PWAPP/best.pt')
image_path = sys.argv[1]
results = model.predict(image_path)

# Extract top prediction
top = results[0].names[results[0].probs.top1]
conf = float(results[0].probs.top1conf)

print(json.dumps({"label": top, "confidence": round(conf, 2)}))
