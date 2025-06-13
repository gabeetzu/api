import sys
import json
import traceback
import os
import cv2
from ultralytics import YOLO

def main():
    try:
        if len(sys.argv) != 2:
            raise ValueError("Usage: python3 cnn_yolo_infer.py <image_path>")
      
        image_path = sys.argv[1]
        # Debug image path
        print(json.dumps({"debug": f"Processing image: {image_path}"}), file=sys.stderr)

        if not os.path.exists(image_path):
            raise FileNotFoundError(f"Image file not found: {image_path}")

        img = cv2.imread(image_path)
        if img is None:
            raise ValueError("OpenCV failed to read the image")

        print(json.dumps({"debug": f"Image dimensions: {img.shape}"}), file=sys.stderr)
            
        model = YOLO("best.pt")
        results = model.predict(img)
        
        if not results:
            raise ValueError("No inference results")
            
        top = results[0].names[results[0].probs.top1]
        conf = float(results[0].probs.top1conf)
        print(json.dumps({"label": top, "confidence": conf}))
        
    except Exception as e:
        error_msg = {
            "error": str(e),
            "traceback": traceback.format_exc()
        }
        print(json.dumps(error_msg), file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main()
