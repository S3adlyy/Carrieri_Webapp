import cv2
import mediapipe as mp
import numpy as np
import json
import os
import pickle
import urllib.request
from collections import deque
from datetime import datetime
import warnings

warnings.filterwarnings('ignore')

# MediaPipe Tasks API (replaces deprecated mp.solutions)
from mediapipe.tasks.python import vision as mp_vision
from mediapipe.tasks.python.vision import drawing_utils as mp_drawing
from mediapipe.tasks.python.vision import drawing_styles as mp_drawing_styles
from mediapipe.tasks.python.vision import HandLandmarker, HandLandmarkerOptions
from mediapipe.tasks.python.vision.core.vision_task_running_mode import VisionTaskRunningMode
from mediapipe.tasks.python.components.containers.landmark import NormalizedLandmark

# Machine learning imports
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, classification_report
from sklearn.preprocessing import StandardScaler

# Hand connections for drawing (replaces mp.solutions.hands.HAND_CONNECTIONS)
HAND_CONNECTIONS = mp_vision.HandLandmarksConnections.HAND_CONNECTIONS

MODEL_PATH = "hand_landmarker.task"
MODEL_URL = "https://storage.googleapis.com/mediapipe-models/hand_landmarker/hand_landmarker/float16/1/hand_landmarker.task"


def _ensure_model():
    """Download the hand landmarker model file if not present."""
    if not os.path.exists(MODEL_PATH):
        print(f"Downloading MediaPipe hand landmarker model...")
        urllib.request.urlretrieve(MODEL_URL, MODEL_PATH)
        print("Model downloaded successfully.")


DROIDCAM_IP = "192.168.1.12"  # Set automatically, or hardcode e.g. "192.168.1.5"
DROIDCAM_PORT = 4747


def _is_green(frame):
    """Return True if the frame is mostly solid green (broken MSMF/NV12 feed).
    Only returns True for 3-channel frames that are overwhelmingly green.
    """
    if frame is None:
        return False
    if frame.ndim != 3 or frame.shape[2] != 3:
        return False  # not a normal BGR frame, don't try to convert
    hsv = cv2.cvtColor(frame, cv2.COLOR_BGR2HSV)
    green_mask = cv2.inRange(hsv, (40, 100, 100), (80, 255, 255))
    green_ratio = green_mask.sum() / (frame.shape[0] * frame.shape[1] * 255)
    return green_ratio > 0.8  # raised threshold to avoid false positives


def find_camera():
    """Try every DroidCam HTTP endpoint until one delivers a real (non-green) frame."""
    global DROIDCAM_IP

    if not DROIDCAM_IP:
        print("  Open the DroidCam app on your iPhone.")
        DROIDCAM_IP = input("  Enter your iPhone IP (e.g. 192.168.1.12): ").strip()

    endpoints = [
        f"http://{DROIDCAM_IP}:{DROIDCAM_PORT}/mjpegfeed",
        f"http://{DROIDCAM_IP}:{DROIDCAM_PORT}/mjpegfeed?res=640x480",
        f"http://{DROIDCAM_IP}:{DROIDCAM_PORT}/video",
        f"http://{DROIDCAM_IP}:{DROIDCAM_PORT}/videofeed",
    ]

    for url in endpoints:
        print(f"  Trying {url} ...")
        cap = cv2.VideoCapture(url)
        if not cap.isOpened():
            cap.release()
            continue
        frame = None
        for _ in range(5):
            ret, f = cap.read()
            if ret and f is not None:
                frame = f
                break
        cap.release()
        if frame is None:
            continue
        b_mean = float(frame[:, :, 0].mean())
        g_mean = float(frame[:, :, 1].mean())
        r_mean = float(frame[:, :, 2].mean())
        if g_mean > 100 and b_mean < 5 and r_mean < 5:
            print(f"  ✗ Green frame from {url} — skipping")
            continue
        print(f"  ✓ Real video at {url}  (B={b_mean:.0f} G={g_mean:.0f} R={r_mean:.0f})")
        return url, 0

    print("  All HTTP endpoints returned green/empty frames.")
    print("  Falling back to index 0 (virtual driver — may be green).")
    return 0, cv2.CAP_ANY


class SignLanguageRecognizer:
    def __init__(self):
        # Ensure model file is available
        _ensure_model()

        # Initialize MediaPipe HandLandmarker (new Tasks API)
        options = HandLandmarkerOptions(
            base_options=mp.tasks.BaseOptions(model_asset_path=MODEL_PATH),
            running_mode=VisionTaskRunningMode.VIDEO,
            num_hands=1,
            min_hand_detection_confidence=0.7,
            min_tracking_confidence=0.5,
        )
        self.hands = HandLandmarker.create_from_options(options)
        self._frame_timestamp_ms = 0  # incremented each frame

        # Recognition settings
        self.text_buffer = deque(maxlen=10)
        self.predicted_text = ""
        self.last_prediction = ""
        self.prediction_counter = 0
        self.confidence_threshold = 0.35
        self.stable_frame_count = 3  # Frames needed for stable prediction

        # Gesture mapping (ASL alphabet)
        self.gesture_labels = {
            0: 'A', 1: 'B', 2: 'C', 3: 'D', 4: 'E', 5: 'F', 6: 'G',
            7: 'H', 8: 'I', 9: 'J', 10: 'K', 11: 'L', 12: 'M', 13: 'N',
            14: 'O', 15: 'P', 16: 'Q', 17: 'R', 18: 'S', 19: 'T', 20: 'U',
            21: 'V', 22: 'W', 23: 'X', 24: 'Y', 25: 'Z', 26: 'SPACE',
            27: 'DELETE', 28: 'CLEAR'
        }

        # Reverse mapping for display
        self.gesture_names = {v: k for k, v in self.gesture_labels.items()}

        # Model and scaler
        self.model = None
        self.scaler = StandardScaler()
        self.is_scaler_fitted = False

        # Load or create model
        self.load_or_create_model()

    def extract_hand_landmarks(self, hand_landmarks):
        """Extract comprehensive hand features.

        Accepts either the new-API list[NormalizedLandmark] or the legacy
        hand_landmarks object that has a .landmark attribute.
        """
        if not hand_landmarks:
            return None

        # Support both new Tasks API (plain list) and legacy .landmark attribute
        lm_iter = hand_landmarks if isinstance(hand_landmarks, (list, tuple)) else hand_landmarks.landmark

        landmarks = []
        # Extract x, y, z for all 21 landmarks
        for landmark in lm_iter:
            landmarks.extend([landmark.x, landmark.y, landmark.z])

        # Calculate additional features
        if len(landmarks) == 63:  # 21 * 3
            # Feature 1: Finger distances from wrist
            wrist_x, wrist_y, wrist_z = landmarks[0], landmarks[1], landmarks[2]

            # Finger tip indices (landmark indices)
            finger_tips = [4, 8, 12, 16, 20]  # Thumb, Index, Middle, Ring, Pinky
            finger_bases = [2, 5, 9, 13, 17]  # Base of each finger

            # Add distances from wrist to each finger tip
            for tip_idx in finger_tips:
                tip_x = landmarks[tip_idx * 3]
                tip_y = landmarks[tip_idx * 3 + 1]
                tip_z = landmarks[tip_idx * 3 + 2]

                dist = np.sqrt((tip_x - wrist_x) ** 2 + (tip_y - wrist_y) ** 2 + (tip_z - wrist_z) ** 2)
                landmarks.append(dist)

            # Add finger curl ratios (distance from tip to base)
            for tip_idx, base_idx in zip(finger_tips, finger_bases):
                tip_x = landmarks[tip_idx * 3]
                tip_y = landmarks[tip_idx * 3 + 1]
                base_x = landmarks[base_idx * 3]
                base_y = landmarks[base_idx * 3 + 1]

                curl_ratio = np.sqrt((tip_x - base_x) ** 2 + (tip_y - base_y) ** 2)
                landmarks.append(curl_ratio)

            # Add angles between fingers (simplified)
            for i in range(len(finger_tips) - 1):
                tip1_x = landmarks[finger_tips[i] * 3]
                tip1_y = landmarks[finger_tips[i] * 3 + 1]
                tip2_x = landmarks[finger_tips[i + 1] * 3]
                tip2_y = landmarks[finger_tips[i + 1] * 3 + 1]

                angle = np.arctan2(tip2_y - tip1_y, tip2_x - tip1_x)
                landmarks.append(angle)

        return np.array(landmarks)

    def train_model(self):
        """Train Random Forest model from collected gesture data"""
        gestures_data = self.load_training_data()

        if not gestures_data:
            print("No training data found. Please collect gestures first!")
            return False

        X = []
        y = []

        for gesture_id, samples in gestures_data.items():
            for sample in samples:
                X.append(sample)
                y.append(gesture_id)

        if len(X) == 0:
            print("No valid training samples found")
            return False

        X = np.array(X)
        y = np.array(y)

        print(f"\n=== Training Model ===")
        print(f"Total samples: {len(X)}")
        print(f"Feature size: {X.shape[1]}")
        print(f"Number of classes: {len(np.unique(y))}")

        # Split data
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42, stratify=y
        )

        # Fit scaler
        self.scaler.fit(X_train)
        self.is_scaler_fitted = True

        # Scale features
        X_train_scaled = self.scaler.transform(X_train)
        X_test_scaled = self.scaler.transform(X_test)

        # Train Random Forest
        self.model = RandomForestClassifier(
            n_estimators=100,  # Number of trees
            max_depth=20,  # Maximum depth of trees
            min_samples_split=5,
            min_samples_leaf=2,
            random_state=42,
            n_jobs=-1  # Use all CPU cores
        )

        self.model.fit(X_train_scaled, y_train)

        # Evaluate
        y_pred = self.model.predict(X_test_scaled)
        accuracy = accuracy_score(y_test, y_pred)

        print(f"\nModel Accuracy: {accuracy * 100:.2f}%")
        print("\nPer-class accuracy:")

        # Show per-class accuracy
        for gesture_id in np.unique(y):
            mask = y_test == gesture_id
            if np.sum(mask) > 0:
                class_acc = accuracy_score(y_test[mask], y_pred[mask])
                gesture_name = self.gesture_labels[gesture_id]
                print(f"  {gesture_name}: {class_acc * 100:.1f}%")

        # Save model and scaler
        self.save_model()

        return True

    def save_model(self):
        """Save trained model and scaler"""
        if self.model is None:
            return

        os.makedirs('models', exist_ok=True)

        # Save model
        with open('models/sign_language_model.pkl', 'wb') as f:
            pickle.dump(self.model, f)

        # Save scaler
        with open('models/scaler.pkl', 'wb') as f:
            pickle.dump(self.scaler, f)

        print("Model and scaler saved successfully!")

    def load_or_create_model(self):
        """Load existing model or prepare for training"""
        model_path = 'models/sign_language_model.pkl'
        scaler_path = 'models/scaler.pkl'

        if os.path.exists(model_path) and os.path.exists(scaler_path):
            try:
                with open(model_path, 'rb') as f:
                    self.model = pickle.load(f)
                with open(scaler_path, 'rb') as f:
                    self.scaler = pickle.load(f)
                self.is_scaler_fitted = True
                print("Model loaded successfully!")
                return True
            except Exception as e:
                print(f"Error loading model: {e}")
                self.model = None
                return False
        else:
            print("No existing model found. Please collect training data first.")
            return False

    def save_gesture_data(self, gesture_id, landmarks):
        """Save gesture sample to JSON file"""
        if landmarks is None:
            print("No landmarks to save!")
            return False

        os.makedirs('gestures_data', exist_ok=True)
        filename = f"gestures_data/gesture_{gesture_id}.json"

        # Load existing data
        if os.path.exists(filename):
            with open(filename, 'r') as f:
                data = json.load(f)
        else:
            data = []

        # Add new sample
        data.append(landmarks.tolist())

        # Save back
        with open(filename, 'w') as f:
            json.dump(data, f)

        gesture_name = self.gesture_labels[gesture_id]
        print(f"✓ Saved {gesture_name} - Sample {len(data)}")
        return True

    def load_training_data(self):
        """Load all saved gesture data"""
        gestures_data = {}

        if not os.path.exists('gestures_data'):
            return gestures_data

        for filename in os.listdir('gestures_data'):
            if filename.endswith('.json'):
                try:
                    gesture_id = int(filename.split('_')[1].split('.')[0])
                    with open(f"gestures_data/{filename}", 'r') as f:
                        data = json.load(f)
                        gestures_data[gesture_id] = [np.array(sample) for sample in data]
                except Exception as e:
                    print(f"Error loading {filename}: {e}")

        return gestures_data

    def predict_gesture(self, landmarks):
        """Predict gesture from hand landmarks"""
        if self.model is None or landmarks is None:
            return None, 0.0

        # Ensure landmarks is 2D
        if landmarks.ndim == 1:
            landmarks = landmarks.reshape(1, -1)

        # Scale features
        if self.is_scaler_fitted:
            landmarks_scaled = self.scaler.transform(landmarks)
        else:
            return None, 0.0

        # Get prediction probabilities
        probabilities = self.model.predict_proba(landmarks_scaled)[0]
        predicted_class = np.argmax(probabilities)
        confidence = np.max(probabilities)

        if confidence > self.confidence_threshold:
            return predicted_class, confidence
        return None, confidence

    def update_text_output(self, gesture_id):
        """Update text output with smoothing"""
        if gesture_id is None:
            return

        gesture_char = self.gesture_labels[gesture_id]

        # Smoothing - require consistent predictions
        if gesture_char == self.last_prediction:
            self.prediction_counter += 1
        else:
            self.prediction_counter = 0
            self.last_prediction = gesture_char

        # Add to text if stable
        if self.prediction_counter >= self.stable_frame_count:
            if gesture_char == 'DELETE':
                self.predicted_text = self.predicted_text[:-1] if self.predicted_text else ""
                print(f"DELETE: '{self.predicted_text}'")
            elif gesture_char == 'SPACE':
                self.predicted_text += " "
                print(f"SPACE: '{self.predicted_text}'")
            elif gesture_char == 'CLEAR':
                self.predicted_text = ""
                print("CLEAR: Text cleared")
            elif gesture_char != self.last_prediction:
                self.predicted_text += gesture_char
                print(f"Added '{gesture_char}': '{self.predicted_text}'")

            self.prediction_counter = 0

    def draw_hand_landmarks(self, image, results):
        """Draw hand landmarks on image"""
        hand_landmarks_list = getattr(results, 'hand_landmarks', None) or getattr(results, 'multi_hand_landmarks', None)
        if not hand_landmarks_list:
            return
        for hand_landmarks in hand_landmarks_list:
            mp_drawing.draw_landmarks(
                image,
                hand_landmarks,
                HAND_CONNECTIONS,
                mp_drawing_styles.get_default_hand_landmarks_style(),
                mp_drawing_styles.get_default_hand_connections_style(),
            )

    def get_training_stats(self):
        """Get statistics about collected training data"""
        gestures_data = self.load_training_data()

        if not gestures_data:
            return "No training data found!"

        stats = "\n=== Training Data Statistics ===\n"
        total_samples = 0

        for gesture_id, samples in sorted(gestures_data.items()):
            gesture_name = self.gesture_labels[gesture_id]
            sample_count = len(samples)
            total_samples += sample_count
            stats += f"{gesture_name}: {sample_count} samples\n"

        stats += f"\nTotal: {total_samples} samples across {len(gestures_data)} gestures"
        return stats

    def run_real_time_recognition(self):
        """Main loop for real-time gesture recognition"""
        cam_src, cam_backend = find_camera()
        cap = cv2.VideoCapture(cam_src) if isinstance(cam_src, str) else cv2.VideoCapture(cam_src, cam_backend)
        cap.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
        cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)

        if not cap.isOpened():
            print("Error: Could not open camera!")
            print("Make sure DroidCam is running on your iPhone AND the PC app.")
            print("Then try again — or manually set cam_index to 1, 2, or 3.")
            return

        # Warm up camera — discard first 30 frames so DroidCam exposure settles
        for _ in range(30):
            cap.read()

        # Mode flags
        training_mode = False
        current_training_gesture = 0
        samples_collected = 0
        required_samples = 30  # Samples needed per gesture
        auto_increment = False
        last_save_time = 0

        # FPS calculation
        fps = 0
        fps_counter = 0
        fps_time = time.time()

        print("\n" + "=" * 50)
        print("SIGN LANGUAGE RECOGNITION SYSTEM")
        print("=" * 50)
        print(f"\nCurrent gesture count: {len(self.load_training_data())} gestures with data")

        if self.model is None:
            print("\n⚠️  No model loaded! Please collect training data first.")
            print("Press 't' to enter training mode and collect gestures.")

        print("\nCONTROLS:")
        print("  't' - Training mode (on/off)")
        print("  'r' - Retrain model with current data")
        print("  'c' - Clear text")
        print("  's' - Save current gesture (training mode)")
        print("  'a' - Auto-increment gestures (training mode)")
        print("  '1-9' - Select gesture number (training mode)")
        print("  'q' - Quit")
        print("=" * 50 + "\n")

        while cap.isOpened():
            success, frame = cap.read()
            if not success:
                print("Failed to grab frame")
                break

            # Fix green frame: DroidCam via MSMF may deliver NV12, convert if needed
            if frame is not None and frame.ndim == 3 and frame.shape[2] == 1:
                frame = cv2.cvtColor(frame, cv2.COLOR_YUV2BGR_NV12)
            # Flip frame horizontally for mirror view
            frame = cv2.flip(frame, 1)
            frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            # New Tasks API: wrap in mp.Image and call detect_for_video
            self._frame_timestamp_ms += 33
            mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=frame_rgb)
            results = self.hands.detect_for_video(mp_image, self._frame_timestamp_ms)

            # Extract landmarks
            landmarks = None
            if results.hand_landmarks:
                landmarks = self.extract_hand_landmarks(results.hand_landmarks[0])
                self.draw_hand_landmarks(frame, results)

            # Calculate FPS
            fps_counter += 1
            if time.time() - fps_time > 1.0:
                fps = fps_counter
                fps_counter = 0
                fps_time = time.time()

            # Mode-specific processing
            if training_mode:
                # Training mode display
                gesture_name = self.gesture_labels[current_training_gesture]

                # Semi-transparent background for training info
                overlay = frame.copy()
                cv2.rectangle(overlay, (0, 0), (frame.shape[1], 150), (0, 0, 0), -1)
                cv2.addWeighted(overlay, 0.5, frame, 0.5, 0, frame)

                cv2.putText(frame, "TRAINING MODE", (10, 30),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 255, 255), 2)
                cv2.putText(frame, f"Gesture: {gesture_name} (ID: {current_training_gesture})",
                            (10, 60), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2)
                cv2.putText(frame, f"Samples: {samples_collected}/{required_samples}",
                            (10, 90), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2)

                # Progress bar
                progress = samples_collected / required_samples
                bar_width = int(progress * 300)
                cv2.rectangle(frame, (10, 105), (310, 120), (100, 100, 100), -1)
                cv2.rectangle(frame, (10, 105), (10 + bar_width, 120), (0, 255, 0), -1)

                cv2.putText(frame, "Press 's' to save | 'a' auto-mode | '1-9' change gesture",
                            (10, 145), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (200, 200, 200), 1)

                if landmarks is not None:
                    cv2.putText(frame, "✓ Hand detected - Ready to save",
                                (10, 400), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2)
                else:
                    cv2.putText(frame, "✗ No hand detected - Show your gesture",
                                (10, 400), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 0, 255), 2)

                # Auto-save mode
                if auto_increment and landmarks is not None and samples_collected < required_samples:
                    current_time = time.time()
                    if current_time - last_save_time > 0.5:  # Save every 0.5 seconds
                        if self.save_gesture_data(current_training_gesture, landmarks):
                            samples_collected += 1
                            last_save_time = current_time

                            if samples_collected >= required_samples:
                                print(f"\n✓ Completed {required_samples} samples for {gesture_name}")
                                if current_training_gesture < max(self.gesture_labels.keys()):
                                    current_training_gesture += 1
                                    samples_collected = 0
                                    print(f"Moving to next gesture: {self.gesture_labels[current_training_gesture]}")
                                else:
                                    print("All gestures collected! Exiting training mode.")
                                    training_mode = False
                                    auto_increment = False
            else:
                # Recognition mode
                if landmarks is not None:
                    gesture_id, confidence = self.predict_gesture(landmarks)
                    self.update_text_output(gesture_id)

                    # Display prediction
                    if gesture_id is not None:
                        gesture_name = self.gesture_labels[gesture_id]
                        pred_text = f"Gesture: {gesture_name} ({confidence:.2f})"
                        cv2.putText(frame, pred_text, (10, 60),
                                    cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 255, 0), 2)

                        # Show confidence bar
                        bar_width = int(confidence * 300)
                        cv2.rectangle(frame, (10, 85), (310, 100), (100, 100, 100), -1)
                        cv2.rectangle(frame, (10, 85), (10 + bar_width, 100), (0, 255, 0), -1)
                    else:
                        cv2.putText(frame, "Unknown gesture", (10, 60),
                                    cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 255), 2)
                else:
                    cv2.putText(frame, "No hand detected", (10, 60),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 255), 2)

            # Display recognized text (bottom area)
            overlay = frame.copy()
            cv2.rectangle(overlay, (0, frame.shape[0] - 120),
                          (frame.shape[1], frame.shape[0]), (0, 0, 0), -1)
            cv2.addWeighted(overlay, 0.5, frame, 0.5, 0, frame)

            # Show recognized text
            text_display = self.predicted_text if self.predicted_text else "Waiting for gestures..."
            cv2.putText(frame, "Recognized Text:", (10, frame.shape[0] - 95),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 0), 2)

            # Word wrap for long text
            wrapped_lines = self.wrap_text(text_display, 45)
            y_offset = frame.shape[0] - 65
            for i, line in enumerate(wrapped_lines[:3]):  # Show max 3 lines
                cv2.putText(frame, line, (15, y_offset + i * 25),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 1)

            # Mode indicator
            mode_color = (0, 255, 255) if training_mode else (0, 255, 0)
            mode_text = "TRAINING" if training_mode else "RECOGNITION"
            cv2.putText(frame, mode_text, (frame.shape[1] - 120, 30),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.7, mode_color, 2)

            # FPS counter
            cv2.putText(frame, f"FPS: {fps}", (frame.shape[1] - 70, frame.shape[0] - 10),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.5, (200, 200, 200), 1)

            # Show frame
            cv2.imshow('Sign Language Recognizer', frame)

            # Handle keyboard input
            key = cv2.waitKey(1) & 0xFF

            if key == ord('q'):
                break
            elif key == ord('t'):
                training_mode = not training_mode
                if training_mode:
                    samples_collected = 0
                    auto_increment = False
                    print("\nEntered TRAINING mode")
                else:
                    print("\nExited TRAINING mode")
            elif key == ord('r'):
                print("\nRetraining model...")
                if self.train_model():
                    print("✓ Model retrained successfully!")
                else:
                    print("✗ Failed to retrain model. Collect more data first.")
            elif key == ord('c'):
                self.predicted_text = ""
                print("Text cleared")
            elif key == ord('a') and training_mode:
                auto_increment = not auto_increment
                print(f"Auto-increment mode: {'ON' if auto_increment else 'OFF'}")
            elif training_mode and key == ord('s') and landmarks is not None:
                if self.save_gesture_data(current_training_gesture, landmarks):
                    samples_collected += 1
                    print(f"Sample {samples_collected}/{required_samples} saved")

                    if samples_collected >= required_samples:
                        print(
                            f"\n✓ Completed {required_samples} samples for {self.gesture_labels[current_training_gesture]}")
                        if auto_increment and current_training_gesture < max(self.gesture_labels.keys()):
                            current_training_gesture += 1
                            samples_collected = 0
                            print(f"Moving to: {self.gesture_labels[current_training_gesture]}")
                        else:
                            print("You can continue collecting or press 'r' to retrain the model")
            elif training_mode and key == ord('s') and landmarks is None:
                print("Cannot save: No hand detected!")

            # Number keys for gesture selection (1-9, 0)
            elif training_mode:
                for i in range(10):
                    if key == ord(str(i)):
                        target_gesture = i if i > 0 else 10
                        if target_gesture in self.gesture_labels:
                            current_training_gesture = target_gesture
                            samples_collected = len(self.load_training_data().get(current_training_gesture, []))
                            print(
                                f"Selected gesture: {self.gesture_labels[current_training_gesture]} ({samples_collected} samples collected)")

        cap.release()
        cv2.destroyAllWindows()
        print("\nApplication closed. Goodbye!")

    def wrap_text(self, text, max_chars):
        """Wrap text for display"""
        if len(text) <= max_chars:
            return [text]

        words = text.split()
        lines = []
        current_line = []
        current_length = 0

        for word in words:
            if current_length + len(word) + 1 <= max_chars:
                current_line.append(word)
                current_length += len(word) + 1
            else:
                if current_line:
                    lines.append(' '.join(current_line))
                current_line = [word]
                current_length = len(word)

        if current_line:
            lines.append(' '.join(current_line))

        return lines if lines else [""]


class DataCollectorGUI:
    """Interactive data collection utility"""

    @staticmethod
    def collect_all_gestures():
        """Guided auto-collection for all gestures A-Z.
        Hold each sign steady — samples collect automatically.
        SPACE = collect one sample manually
        ENTER = skip to next letter
        ESC   = quit
        """
        import time
        recognizer = SignLanguageRecognizer()

        print("\n" + "=" * 60)
        print("GUIDED GESTURE DATA COLLECTION  (A → Z)")
        print("=" * 60)
        print("Hold each ASL sign steady — samples auto-collect.")
        print("SPACE = manual sample  |  ENTER = skip letter  |  ESC = quit\n")

        cam_src, cam_backend = find_camera()
        cap = cv2.VideoCapture(cam_src) if isinstance(cam_src, str) else cv2.VideoCapture(cam_src, cam_backend)
        cap.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
        cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)

        if not cap.isOpened():
            print("Error: Could not open camera!")
            return

        # Warm up
        print("  Warming up camera...")
        for _ in range(30):
            cap.read()
        print("  Camera ready!\n")

        SAMPLES_NEEDED = 50      # samples per letter
        HOLD_SECONDS   = 3.0     # seconds to hold before auto-collect starts
        COLLECT_INTERVAL = 0.08  # seconds between auto-samples (~12/sec)

        gesture_id = 0
        key = 0

        while gesture_id < 26:
            gesture_char = recognizer.gesture_labels[gesture_id]
            samples_collected = 0
            hold_start = None
            last_collect_time = 0
            collecting_active = False

            print(f"📝  [{gesture_id+1}/26]  Letter: {gesture_char}  — make the ASL sign and hold still")

            while samples_collected < SAMPLES_NEEDED:
                success, frame = cap.read()
                if not success:
                    continue

                frame = cv2.flip(frame, 1)
                frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                recognizer._frame_timestamp_ms += 33
                mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=frame_rgb)
                results = recognizer.hands.detect_for_video(mp_image, recognizer._frame_timestamp_ms)

                landmarks = None
                hand_detected = bool(results.hand_landmarks)

                if hand_detected:
                    recognizer.draw_hand_landmarks(frame, results)
                    landmarks = recognizer.extract_hand_landmarks(results.hand_landmarks[0])

                now = time.time()

                # Auto-collect logic
                if landmarks is not None:
                    if hold_start is None:
                        hold_start = now
                    held = now - hold_start
                    if held >= HOLD_SECONDS:
                        collecting_active = True
                    if collecting_active and (now - last_collect_time) >= COLLECT_INTERVAL:
                        if recognizer.save_gesture_data(gesture_id, landmarks):
                            samples_collected += 1
                            last_collect_time = now
                else:
                    hold_start = None
                    collecting_active = False

                # ── UI ──────────────────────────────────────────────
                h, w = frame.shape[:2]

                # Top overlay
                overlay = frame.copy()
                cv2.rectangle(overlay, (0, 0), (w, 155), (0, 0, 0), -1)
                cv2.addWeighted(overlay, 0.55, frame, 0.45, 0, frame)

                # Letter + progress
                progress_ratio = samples_collected / SAMPLES_NEEDED
                bar_w = int(progress_ratio * (w - 30))
                cv2.rectangle(frame, (15, 105), (w-15, 130), (60, 60, 60), -1)
                cv2.rectangle(frame, (15, 105), (15 + bar_w, 130), (0, 210, 80), -1)

                cv2.putText(frame, f"Letter: {gesture_char}  [{gesture_id+1}/26]",
                            (15, 42), cv2.FONT_HERSHEY_SIMPLEX, 1.0, (0, 255, 120), 2)
                cv2.putText(frame, f"Samples: {samples_collected}/{SAMPLES_NEEDED}",
                            (15, 78), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 120), 2)
                cv2.putText(frame, "SPACE=manual  ENTER=skip  ESC=quit",
                            (15, 148), cv2.FONT_HERSHEY_SIMPLEX, 0.45, (200, 200, 200), 1)

                # Status bar bottom
                if not hand_detected:
                    status_txt = "Show your hand to the camera"
                    status_col = (0, 120, 255)
                elif not collecting_active:
                    held_pct = int(min((now - hold_start) / HOLD_SECONDS * 100, 100)) if hold_start else 0
                    status_txt = f"Hold still... {held_pct}%"
                    status_col = (0, 200, 255)
                else:
                    status_txt = f"Collecting! Keep holding {gesture_char}"
                    status_col = (0, 255, 80)

                cv2.rectangle(frame, (0, h-40), (w, h), (0,0,0), -1)
                cv2.putText(frame, status_txt, (15, h-12),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.65, status_col, 2)

                cv2.imshow('Data Collection', frame)
                key = cv2.waitKey(1) & 0xFF

                if key == ord(' ') and landmarks is not None:
                    # Manual single sample
                    if recognizer.save_gesture_data(gesture_id, landmarks):
                        samples_collected += 1
                elif key == 13 or key == 27:  # ENTER or ESC
                    break

            # Letter done
            if samples_collected > 0:
                print(f"   ✓ {gesture_char}: {samples_collected} samples saved")
            else:
                print(f"   — {gesture_char}: skipped")

            if key == 27:  # ESC = full quit
                break

            gesture_id += 1

        cap.release()
        cv2.destroyAllWindows()

        collected = sum(1 for i in range(26)
                        if os.path.exists(f"gestures_data/gesture_{i}.json"))
        print(f"\n{'='*60}")
        print(f"✓ Collection complete! {collected}/26 letters have data.")
        if collected >= 5:
            print("→ Now choose option 4 (Retrain Model) to train with your data.")
        print("=" * 60)

        # Train model after collection
        print("\nTraining model with collected data...")
        if recognizer.train_model():
            print("✓ Model trained successfully!")
        else:
            print("✗ Failed to train model")


def show_menu():
    """Display main menu"""
    print("\n" + "=" * 50)
    print("   SIGN LANGUAGE RECOGNITION SYSTEM")
    print("=" * 50)
    print("\n1. 🎯 Real-time Recognition Mode")
    print("2. 📝 Guided Data Collection Mode")
    print("3. 📊 View Training Data Statistics")
    print("4. 🔄 Retrain Model")
    print("5. ❌ Exit")
    print("-" * 50)


def main():
    """Main function"""
    recognizer = SignLanguageRecognizer()

    while True:
        show_menu()
        choice = input("\nSelect option (1-5): ").strip()

        if choice == '1':
            recognizer.run_real_time_recognition()
        elif choice == '2':
            DataCollectorGUI.collect_all_gestures()
        elif choice == '3':
            print(recognizer.get_training_stats())
            input("\nPress Enter to continue...")
        elif choice == '4':
            print("\nRetraining model...")
            if recognizer.train_model():
                print("✓ Model retrained successfully!")
            else:
                print("✗ Failed to retrain. Please collect data first.")
            input("\nPress Enter to continue...")
        elif choice == '5':
            print("\nThank you for using Sign Language Recognition System!")
            break
        else:
            print("\nInvalid choice. Please select 1-5.")


if __name__ == "__main__":
    import time

    # Create necessary directories
    os.makedirs('gestures_data', exist_ok=True)
    os.makedirs('models', exist_ok=True)

    # Run main application
    main()