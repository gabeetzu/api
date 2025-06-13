import torch, sys, json, traceback, cv2, os
from ultralytics import YOLO

torch.set_default_device('cpu')          # enforce CPU

def jprint(obj):
    print(json.dumps(obj))

    try:
    if len(sys.argv) != 2:
        raise ValueError("arg <image_path> required")

        img_path = sys.argv[1]
    if not os.path.exists(img_path):
        raise FileNotFoundError(img_path)

img = cv2.imread(img_path)
    if img is None:
        raise ValueError("OpenCV failed")

    model = YOLO('best.pt')
    res   = model.predict(img, device='cpu', verbose=False)
    top   = res[0].names[res[0].probs.top1] if res and res[0].probs else "unknown"
    conf  = float(res[0].probs.top1conf)    if res and res[0].probs else 0.0
    jprint({"label": top, "confidence": conf})

except Exception as e:
    jprint({"error": str(e), "trace": traceback.format_exc()})
    sys.exit(1)
