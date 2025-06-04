from flask import Flask, request, jsonify
import tensorflow as tf
import numpy as np
from PIL import Image
import io

app = Flask(__name__)
model = tf.keras.models.load_model('plant_disease_model.h5')

CLASS_NAMES = [
    'Apple___Apple_scab',
    'Apple___Black_rot',
    # ... (copy ALL 38 class names from original notebook)
]

def preprocess_image(image_bytes):
    img = Image.open(io.BytesIO(image_bytes)).convert('RGB').resize((256, 256))
    img_array = np.array(img) / 255.0
    return np.expand_dims(img_array, axis=0)

@app.route('/predict', methods=['POST'])
def predict():
    if 'image' not in request.files:
        return jsonify({'error': 'No image uploaded'}), 400
    
    image = request.files['image'].read()
    img_array = preprocess_image(image)
    
    prediction = model.predict(img_array)
    class_idx = np.argmax(prediction)
    
    return jsonify({
        'disease': CLASS_NAMES[class_idx],
        'confidence': float
