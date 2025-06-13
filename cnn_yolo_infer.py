import sys
import json
import traceback
import os
import cv2
import torch
from ultralytics import YOLO

def main():
    try:
        # Force CPU usage (no GPU on Render.com)
        torch.set_default_device('cpu')
        
        if len(sys.argv) != 2:
            raise ValueError("Usage: python3 cnn_yolo_infer.py <image_path>")
        
        image_path = sys.argv[1]
        print(json.dumps({"debug": f"Processing image: {image_path}"}), file=sys.stderr)

        if not os.path.exists(image_path):
            raise FileNotFoundError(f"Image file not found: {image_path}")

        img = cv2.imread(image_path)
        if img is None:
            raise ValueError("OpenCV failed to read the image")

        print(json.dumps({"debug": f"Image dimensions: {img.shape}"}), file=sys.stderr)
        
        # Load model with explicit CPU device
        model = YOLO("best.pt")
        model.to('cpu')  # Ensure CPU usage
        
        # Run inference on CPU
        results = model.predict(img, device='cpu', verbose=False)
        
        if not results or len(results) == 0:
            raise ValueError("No inference results")
        
        # Extract results
        result = results[0]
        if hasattr(result, 'probs') and result.probs is not None:
            top = result.names[result.probs.top1]
            conf = float(result.probs.top1conf)
        else:
            # Fallback for detection models
            if len(result.boxes) > 0:
                top_idx = result.boxes.conf.argmax()
                top = result.names[int(result.boxes.cls[top_idx])]
                conf = float(result.boxes.conf[top_idx])
            else:
                top = "unknown"
                conf = 0.0
        
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
