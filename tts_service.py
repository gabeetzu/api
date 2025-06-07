from flask import Flask, request, jsonify
from TTS.api import TTS
import base64
import os
import uuid

app = Flask(__name__)

@app.route('/ping', methods=['GET'])
def ping():
    return "OK", 200

# Load the Romanian model once at startup
tts = TTS("tts_models/ro/cv/vits")

@app.route('/speak', methods=['POST'])
def speak():
    data = request.get_json(silent=True) or {}
    text = data.get('text', '').strip()
    if not text:
        return jsonify({'error': 'Text is required'}), 400

    tmp_path = f"/tmp/{uuid.uuid4().hex}.wav"
    try:
        tts.tts_to_file(text=text, file_path=tmp_path)
        with open(tmp_path, 'rb') as f:
            audio_b64 = base64.b64encode(f.read()).decode('utf-8')
        return jsonify({'audio': audio_b64})
    except Exception as e:
        return jsonify({'error': str(e)}), 500
    finally:
        if os.path.exists(tmp_path):
            os.remove(tmp_path)

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5002)
