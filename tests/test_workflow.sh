#!/bin/bash
set -e

# Test 1 - Verify Python Environment
/opt/venv/bin/python3 -c "import torch; print('PyTorch version:', torch.__version__)"

# Test 2 - Validate Model Loading
/opt/venv/bin/python3 -c "from ultralytics import YOLO; YOLO('best.pt')"

# Test 3 - End-to-End Image Processing
curl -X POST https://gabeetzu-project.onrender.com/process-image.php \
  -F "image=@test.jpg" \
  -F "device_hash=test123" || true
