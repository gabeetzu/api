from ultralytics import YOLO
import sys
import json
import os

# Get current directory
current_dir = os.path.dirname(os.path.abspath(__file__))

# Load model from current directory
model = YOLO(os.path.join(current_dir, 'best.pt'))  # No api-PWAPP/

# Process image
image_path = sys.argv[1]
results = model.predict(image_path)

# Extract prediction
top = results[0].names[results[0].probs.top1]
conf = float(results[0].probs.top1conf)

print(json.dumps({"label": top, "confidence": round(conf, 2)}))
