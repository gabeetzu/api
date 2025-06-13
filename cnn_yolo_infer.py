import os
from ultralytics import YOLO
import sys
import json

# Get directory of this script
script_dir = os.path.dirname(os.path.abspath(__file__))

# Path to model weights
model_path = os.path.join(script_dir, 'best.pt')

# Load model
model = YOLO(model_path)

# Get image path from command line
image_path = sys.argv[1]

# Process image
results = model.predict(image_path)

# Extract top prediction
top = results[0].names[results[0].probs.top1]
conf = float(results[0].probs.top1conf)

print(json.dumps({"label": top, "confidence": round(conf, 2)}))
