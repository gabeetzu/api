import sys, json, traceback, os, cv2, torch
from ultralytics import YOLO

torch.set_default_device("cpu")

def fail(msg): 
    print(json.dumps({"error": msg}), file=sys.stderr); sys.exit(1)

try:
    if len(sys.argv) != 2:
        fail("arg <image_path> required")
    img_path = sys.argv[1]
    if not os.path.exists(img_path):
        fail("file not found")

    img = cv2.imread(img_path)
    if img is None:
        fail("opencv unable to read image")

    model = YOLO("best.pt").to("cpu")
    res    = model.predict(img, device="cpu", verbose=False)

    if not res:
        fail("no results")
    r = res[0]
    top = r.names[r.probs.top1] if r.probs else "unknown"
    conf = float(r.probs.top1conf) if r.probs else 0.0
    print(json.dumps({"label": top, "confidence": conf}))
except Exception as e:
    print(json.dumps({"error": str(e),
                      "trace": traceback.format_exc()}), file=sys.stderr)
    sys.exit(1)
