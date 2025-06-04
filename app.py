from flask import Flask, request, jsonify
import tensorflow as tf
import numpy as np
from PIL import Image
import io

app = Flask(__name__)
model = tf.keras.models.load_model('resnet50_model.h5')
CLASS_NAMES = ['Apple Scab', 'Tomato Early Blight', 'Potato Late Blight', ...]  # Use the class list from the repo

def preprocess_image(image_bytes):
    img = Image.open(io.BytesIO(image_bytes)).convert('RGB').resize((224, 224))
    img_array = np.array(img) / 255.0
    img_array = np.expand_dims(img_array, axis=0)
    return img_array

@app.route('/predict', methods=['POST'])
def predict():
    if 'image' not in request.files:
        return jsonify({'error': 'No image uploaded'}), 400
    image_bytes = request.files['image'].read()
    img_array = preprocess_image(image_bytes)
    preds = model.predict(img_array)
    idx = np.argmax(preds)
    return jsonify({
        'disease': CLASS_NAMES[idx],
        'confidence': float(preds[0][idx])
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
