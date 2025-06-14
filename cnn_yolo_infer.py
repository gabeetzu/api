import sys
import os
import json
from ultralytics import YOLO

def load_model():
    try:
        model_path = os.path.join(os.path.dirname(__file__), 'best.pt')
        if not os.path.exists(model_path):
            raise FileNotFoundError(f'Model not found: {model_path}')
        return YOLO(model_path)
    except Exception as e:
        print(json.dumps({'error': f'Failed to load model: {str(e)}'}))
        sys.exit(1)

def predict(image_path, model):
    try:
        results = model.predict(source=image_path, save=False, conf=0.5, imgsz=640, verbose=False)
        if not results:
            raise ValueError("No prediction results returned")

        labels = results[0].names
        boxes = results[0].boxes
        if boxes and len(boxes.cls) > 0:
            class_id = int(boxes.cls[0].item())
            confidence = float(boxes.conf[0].item())
            label = labels[class_id]
            return {'label': label, 'confidence': round(confidence, 4)}
        else:
            return {'label': 'necunoscută', 'confidence': 0.0}

    except Exception as e:
        return {'error': f'Prediction failed: {str(e)}'}

def main():
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'No image path provided'}))
        sys.exit(1)

    image_path = sys.argv[1]
    if not os.path.isfile(image_path):
        print(json.dumps({'error': 'Invalid image path'}))
        sys.exit(1)

    model = load_model()
    output = predict(image_path, model)
    print(json.dumps(output))

if __name__ == "__main__":
    main()
