from ultralytics import YOLO
import sys
import json

model = YOLO("best.pt")  # You’ll train this later, for now just get setup

image_path = sys.argv[1]
results = model(image_path)

# Get top prediction
label = results[0].names[results[0].probs.top1]
confidence = float(results[0].probs[results[0].probs.top1])

print(json.dumps({
    "label": label,
    "confidence": confidence
}))
