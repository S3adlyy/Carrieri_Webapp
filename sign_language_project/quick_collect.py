"""
Quick ASL data collector - hold each letter for 2 seconds to auto-collect.
Run this ONCE before using the main recognizer.
Usage: python quick_collect.py
"""
import cv2
import mediapipe as mp
import numpy as np
import json
import os
import time

from mediapipe.tasks.python import vision as mp_vision
from mediapipe.tasks.python.vision import HandLandmarker, HandLandmarkerOptions
from mediapipe.tasks.python.vision.core.vision_task_running_mode import VisionTaskRunningMode

LETTERS = list('ABCDEFGHIJKLMNOPQRSTUVWXYZ')
SAMPLES_PER_LETTER = 40
HOLD_SECONDS = 2.5
MODEL_PATH = "hand_landmarker.task"
CAMERA_INDEX = 0

def extract_features(hand_landmarks):
    lm = []
    for lmk in hand_landmarks:
        lm.extend([lmk.x, lmk.y, lmk.z])
    if len(lm) == 63:
        wrist = lm[0], lm[1], lm[2]
        finger_tips  = [4, 8, 12, 16, 20]
        finger_bases = [2, 5,  9, 13, 17]
        for ti in finger_tips:
            dx,dy,dz = lm[ti*3]-wrist[0], lm[ti*3+1]-wrist[1], lm[ti*3+2]-wrist[2]
            lm.append(np.sqrt(dx*dx+dy*dy+dz*dz))
        for ti,bi in zip(finger_tips, finger_bases):
            dx,dy = lm[ti*3]-lm[bi*3], lm[ti*3+1]-lm[bi*3+1]
            lm.append(np.sqrt(dx*dx+dy*dy))
        for i in range(len(finger_tips)-1):
            t1x,t1y = lm[finger_tips[i]*3],   lm[finger_tips[i]*3+1]
            t2x,t2y = lm[finger_tips[i+1]*3], lm[finger_tips[i+1]*3+1]
            lm.append(np.arctan2(t2y-t1y, t2x-t1x))
    return lm

os.makedirs('gestures_data', exist_ok=True)

options = HandLandmarkerOptions(
    base_options=mp.tasks.BaseOptions(model_asset_path=MODEL_PATH),
    running_mode=VisionTaskRunningMode.VIDEO,
    num_hands=1,
    min_hand_detection_confidence=0.5,
)
detector = HandLandmarker.create_from_options(options)
ts_ms = 0

cap = cv2.VideoCapture(CAMERA_INDEX)
cap.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)

# Warm up
for _ in range(20): cap.read()

letter_idx = 0
samples = []
hold_start = None
collected_letters = set()

# Load existing data so we can resume
for i, letter in enumerate(LETTERS):
    fname = f"gestures_data/gesture_{i}.json"
    if os.path.exists(fname):
        with open(fname) as f:
            existing = json.load(f)
        if len(existing) >= SAMPLES_PER_LETTER:
            collected_letters.add(letter)

# Skip already collected
while letter_idx < len(LETTERS) and LETTERS[letter_idx] in collected_letters:
    print(f"  Skipping {LETTERS[letter_idx]} (already collected)")
    letter_idx += 1

print(f"\nStarting from letter: {LETTERS[letter_idx] if letter_idx < len(LETTERS) else 'DONE'}")
print("Hold each ASL letter sign steady for 2.5 seconds to auto-collect.")
print("Press ESC to quit, SPACE to skip current letter.\n")

while letter_idx < len(LETTERS) and cap.isOpened():
    letter = LETTERS[letter_idx]
    ret, frame = cap.read()
    if not ret:
        continue

    frame = cv2.flip(frame, 1)
    frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
    ts_ms += 33
    mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=frame_rgb)
    results = detector.detect_for_video(mp_image, ts_ms)

    hand_detected = bool(results.hand_landmarks)
    collecting = False

    if hand_detected:
        feats = extract_features(results.hand_landmarks[0])
        if len(feats) == 77:
            if hold_start is None:
                hold_start = time.time()
            elapsed = time.time() - hold_start
            progress = min(elapsed / HOLD_SECONDS, 1.0)

            # Collect samples spread across hold time
            if elapsed <= HOLD_SECONDS:
                samples.append(feats)
                collecting = True

            # Draw progress bar
            bar_w = int(progress * 580)
            cv2.rectangle(frame, (30, 420), (610, 445), (60,60,60), -1)
            cv2.rectangle(frame, (30, 420), (30+bar_w, 445), (0,220,0), -1)

            # Draw landmarks
            from mediapipe.tasks.python.vision import drawing_utils as du
            from mediapipe.tasks.python.vision import HandLandmarksConnections
            du.draw_landmarks(frame, results.hand_landmarks[0],
                HandLandmarksConnections.HAND_CONNECTIONS)

            if progress >= 1.0:
                # Save collected samples
                gesture_id = ord(letter) - ord('A')
                fname = f"gestures_data/gesture_{gesture_id}.json"
                with open(fname, 'w') as f:
                    json.dump(samples, f)
                print(f"  ✓ {letter}: {len(samples)} samples saved")
                collected_letters.add(letter)
                samples = []
                hold_start = None
                letter_idx += 1
                while letter_idx < len(LETTERS) and LETTERS[letter_idx] in collected_letters:
                    letter_idx += 1
                time.sleep(0.5)
                continue
        else:
            hold_start = None
    else:
        hold_start = None

    # UI overlay
    overlay = frame.copy()
    cv2.rectangle(overlay, (0,0), (640,100), (0,0,0), -1)
    cv2.addWeighted(overlay, 0.55, frame, 0.45, 0, frame)

    status = f"Hold steady... {int((time.time()-hold_start)*100/HOLD_SECONDS if hold_start else 0)}%" if hand_detected else "Show your hand!"
    cv2.putText(frame, f"Letter: {letter}  ({letter_idx+1}/{len(LETTERS)})", (15,35),
                cv2.FONT_HERSHEY_SIMPLEX, 0.9, (0,255,180), 2)
    cv2.putText(frame, status, (15,70),
                cv2.FONT_HERSHEY_SIMPLEX, 0.65, (255,255,100), 2)
    cv2.putText(frame, f"Collected: {len(collected_letters)}/26 letters", (400,470),
                cv2.FONT_HERSHEY_SIMPLEX, 0.5, (200,200,200), 1)

    cv2.imshow('ASL Quick Collector', frame)
    key = cv2.waitKey(1) & 0xFF
    if key == 27:  # ESC
        break
    if key == 32:  # SPACE - skip letter
        print(f"  Skipped {letter}")
        samples = []
        hold_start = None
        letter_idx += 1

cap.release()
cv2.destroyAllWindows()

print(f"\nCollection done! {len(collected_letters)}/26 letters collected.")
print("Now run: python sign_language_recognizer.py  and choose option 4 to retrain.")