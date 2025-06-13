import sys
import json
import cv2
from ultralytics import YOLO

def main():
    try:
        if len(sys.argv) != 2:
            raise ValueError("Usage: python3 cnn_yolo_infer.py <image_path>")
      
        img_path = sys.argv[1]
        img = cv2.imread(img_path)
        if img is None:
            raise ValueError("Could not read image file")
            
        model = YOLO("best.pt")
        results = model.predict(img)
        
        if not results:
            raise ValueError("No inference results")
            
        top = results[0].names[results[0].probs.top1]
        conf = float(results[0].probs.top1conf)
        print(json.dumps({"label": top, "confidence": round(conf, 2)}))
        
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    main()
