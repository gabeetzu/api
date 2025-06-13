import sys
import json
from ultralytics import YOLO
import cv2  # Add OpenCV for image loading

def main():
    try:
        if len(sys.argv) != 2:
            raise ValueError("Usage: python3 cnn_yolo_infer.py <image_path>")
        
        # Load image with OpenCV
        image_path = sys.argv[1]
        img = cv2.imread(image_path)
        if img is None:
            raise ValueError("Could not read image file")
        
        # Load model
        model = YOLO('best.pt')
        
        # Run inference
        results = model.predict(img)
        
        # Extract results
        top = results[0].names[results[0].probs.top1]
        conf = float(results[0].probs.top1conf)
        
        print(json.dumps({"label": top, "confidence": round(conf, 2)}))
        
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    main()
