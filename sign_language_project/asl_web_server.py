"""
ASL Web Server - Bridges the working sign_language_recognizer with Symfony
Run: python asl_web_server.py
"""
from flask import Flask, Response, jsonify, render_template_string
from flask_cors import CORS
import cv2
import json
import threading
import time
import numpy as np
from collections import deque
import sys
import os

# Import mediapipe (this was missing!)
import mediapipe as mp

# Import your working recognizer
from sign_language_recognizer import SignLanguageRecognizer

app = Flask(__name__)
CORS(app)  # Enable CORS for Symfony

# Global state
current_state = {
    'gesture': None,
    'confidence': 0.0,
    'hand_detected': False,
    'recognized_text': '',
    'recent_gestures': deque(maxlen=10)
}
state_lock = threading.Lock()
latest_frame = None
frame_lock = threading.Lock()


class WebRecognizer:
    def __init__(self):
        self.recognizer = SignLanguageRecognizer()
        self.cap = None
        self.running = False

    def initialize_camera(self):
        """Use the same camera finding logic as the original"""
        from sign_language_recognizer import find_camera
        cam_src, cam_backend = find_camera()

        if isinstance(cam_src, str):
            self.cap = cv2.VideoCapture(cam_src)
        else:
            self.cap = cv2.VideoCapture(cam_src, cam_backend)

        self.cap.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
        self.cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)

        # Warm up
        for _ in range(30):
            self.cap.read()

        return self.cap.isOpened()

    def run(self):
        """Main recognition loop (same as option 1)"""
        if not self.initialize_camera():
            print("Failed to initialize camera")
            return

        self.running = True
        fps_counter = 0
        fps_time = time.time()

        print("Web recognizer started - using DroidCam")

        while self.running:
            success, frame = self.cap.read()
            if not success:
                time.sleep(0.01)
                continue

            # Process frame (same as original)
            frame = cv2.flip(frame, 1)
            frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)

            self.recognizer._frame_timestamp_ms += 33
            mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=frame_rgb)
            results = self.recognizer.hands.detect_for_video(
                mp_image,
                self.recognizer._frame_timestamp_ms
            )

            # Extract landmarks and predict
            landmarks = None
            hand_detected = bool(results.hand_landmarks)

            if hand_detected and results.hand_landmarks:
                landmarks = self.recognizer.extract_hand_landmarks(results.hand_landmarks[0])
                self.recognizer.draw_hand_landmarks(frame, results)

                # Predict gesture
                gesture_id, confidence = self.recognizer.predict_gesture(landmarks)

                gesture_char = None
                if gesture_id is not None:
                    gesture_char = self.recognizer.gesture_labels[gesture_id]
                    self.recognizer.update_text_output(gesture_id)

                    # Update state
                    with state_lock:
                        current_state['gesture'] = gesture_char
                        current_state['confidence'] = confidence
                        current_state['hand_detected'] = True
                        current_state['recognized_text'] = self.recognizer.predicted_text

                        # Add to recent gestures if new
                        if gesture_char and (not current_state['recent_gestures'] or
                                             current_state['recent_gestures'][-1][0] != gesture_char):
                            current_state['recent_gestures'].append((gesture_char, time.time()))
                else:
                    with state_lock:
                        if current_state['gesture'] is not None:
                            current_state['gesture'] = None
                        current_state['hand_detected'] = True
            else:
                with state_lock:
                    if current_state['gesture'] is not None:
                        current_state['gesture'] = None
                    current_state['hand_detected'] = False

            # Add overlay text for web display
            with state_lock:
                recognized_text = current_state['recognized_text']
                gesture_char = current_state['gesture']
                confidence = current_state['confidence']

            # Draw overlay (similar to original but cleaner for web)
            h, w = frame.shape[:2]
            cv2.rectangle(frame, (0, 0), (w, 80), (0, 0, 0), -1)

            if gesture_char:
                cv2.putText(frame, f"Gesture: {gesture_char} ({confidence:.2f})",
                            (10, 35), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 255, 0), 2)
            elif hand_detected:
                cv2.putText(frame, "Analyzing...", (10, 35),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 200, 255), 2)
            else:
                cv2.putText(frame, "No hand detected - Show your sign", (10, 35),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 255), 2)

            # Show recognized text
            if recognized_text:
                lines = self.wrap_text(recognized_text, 40)
                y = 65
                for line in lines[:2]:
                    cv2.putText(frame, line, (10, y),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 0), 1)
                    y += 25

            # Encode frame for streaming
            _, jpg = cv2.imencode('.jpg', frame, [cv2.IMWRITE_JPEG_QUALITY, 70])
            with frame_lock:
                global latest_frame
                latest_frame = jpg.tobytes()

            # Small delay to control CPU usage
            time.sleep(0.01)

        self.cap.release()

    def wrap_text(self, text, max_chars):
        """Helper to wrap text"""
        if len(text) <= max_chars:
            return [text]
        words = text.split()
        lines = []
        current_line = []
        current_len = 0
        for word in words:
            if current_len + len(word) + 1 <= max_chars:
                current_line.append(word)
                current_len += len(word) + 1
            else:
                if current_line:
                    lines.append(' '.join(current_line))
                current_line = [word]
                current_len = len(word)
        if current_line:
            lines.append(' '.join(current_line))
        return lines if lines else [""]

    def stop(self):
        self.running = False


# Global recognizer instance
recognizer_instance = WebRecognizer()


# Routes
@app.route('/api/gesture')
def get_gesture():
    """Return current gesture state"""
    with state_lock:
        # Convert recent gestures to serializable format
        recent = [(g, t) for g, t in current_state['recent_gestures']]
        return jsonify({
            'gesture': current_state['gesture'],
            'confidence': round(current_state['confidence'], 3),
            'hand_detected': current_state['hand_detected'],
            'recognized_text': current_state['recognized_text'],
            'recent_gestures': recent[-5:]  # Last 5 gestures
        })


@app.route('/video_feed')
def video_feed():
    """Stream video feed"""

    def generate():
        while True:
            with frame_lock:
                frame_bytes = latest_frame
            if frame_bytes:
                yield (b'--frame\r\n'
                       b'Content-Type: image/jpeg\r\n\r\n' +
                       frame_bytes + b'\r\n')
            time.sleep(0.033)  # ~30 fps

    return Response(generate(),
                    mimetype='multipart/x-mixed-replace; boundary=frame')


@app.route('/api/clear-text', methods=['POST'])
def clear_text():
    """Clear recognized text"""
    with state_lock:
        current_state['recognized_text'] = ''
    if recognizer_instance.recognizer:
        recognizer_instance.recognizer.predicted_text = ''
    return jsonify({'success': True})


@app.route('/api/delete-last', methods=['POST'])
def delete_last():
    """Delete last character"""
    with state_lock:
        current_state['recognized_text'] = current_state['recognized_text'][:-1]
    if recognizer_instance.recognizer:
        recognizer_instance.recognizer.predicted_text = current_state['recognized_text']
    return jsonify({'success': True})


@app.route('/api/add-space', methods=['POST'])
def add_space():
    """Add space"""
    with state_lock:
        current_state['recognized_text'] += ' '
    if recognizer_instance.recognizer:
        recognizer_instance.recognizer.predicted_text = current_state['recognized_text']
    return jsonify({'success': True})


@app.route('/health')
def health():
    """Health check"""
    return jsonify({
        'status': 'ok',
        'model_loaded': recognizer_instance.recognizer and recognizer_instance.recognizer.model is not None,
        'camera_running': recognizer_instance.running
    })


if __name__ == '__main__':
    # Start recognition in background thread
    recognizer_thread = threading.Thread(target=recognizer_instance.run, daemon=True)
    recognizer_thread.start()

    print("\n" + "=" * 50)
    print("ASL Web Server Started!")
    print("=" * 50)
    print("Server running on: http://localhost:5001")
    print("Available endpoints:")
    print("  - GET  /api/gesture     → Current gesture state")
    print("  - GET  /video_feed      → Camera stream")
    print("  - GET  /health          → Health check")
    print("  - POST /api/clear-text  → Clear text")
    print("  - POST /api/delete-last → Delete last character")
    print("  - POST /api/add-space   → Add space")
    print("=" * 50 + "\n")

    app.run(host='0.0.0.0', port=5001, debug=False, threaded=True)