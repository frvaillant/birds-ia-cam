#!/usr/bin/env python3
"""
Bird Detection Service
Captures frames from HLS stream and uses Claude Vision API to identify bird species
"""

import cv2
import base64
import json
import asyncio
import websockets
from anthropic import Anthropic
import os
from datetime import datetime
import re

# Configuration
STREAM_URL = "http://nginx-rtmp:8080/live/camera/index.m3u8"
ANTHROPIC_API_KEY = os.getenv("ANTHROPIC_API_KEY")
WEBSOCKET_PORT = 8765

# Initialize Anthropic client
client = Anthropic(api_key=ANTHROPIC_API_KEY)

# Store connected WebSocket clients
connected_clients = set()

def capture_frame_from_stream():
    """Capture a single frame from the HLS stream"""
    cap = cv2.VideoCapture(STREAM_URL)

    if not cap.isOpened():
        print("Error: Cannot open stream")
        return None

    ret, frame = cap.read()
    cap.release()

    if not ret:
        print("Error: Cannot read frame")
        return None

    return frame

def frame_to_base64(frame):
    """Convert OpenCV frame to base64 string"""
    _, buffer = cv2.imencode('.jpg', frame)
    jpg_as_text = base64.b64encode(buffer).decode('utf-8')
    return jpg_as_text

async def analyze_frame_with_claude(frame_base64):
    """Send frame to Claude Vision API for bird identification"""
    try:
        message = client.messages.create(
            model="claude-sonnet-4-20250514",
            max_tokens=1024,
            messages=[
                {
                    "role": "user",
                    "content": [
                        {
                            "type": "image",
                            "source": {
                                "type": "base64",
                                "media_type": "image/jpeg",
                                "data": frame_base64,
                            },
                        },
                        {
                            "type": "text",
                            "text": """Analyse cette image et identifie tous les oiseaux présents.

Pour chaque oiseau détecté, fournis :
1. Nom de l'espèce (commun en français et scientifique)
2. Niveau de confiance : "élevé", "moyen", ou "faible"
3. Brève description des caractéristiques visuelles qui ont aidé à l'identifier
4. Position approximative dans l'image : "gauche"/"centre"/"droite", "haut"/"milieu"/"bas"

Si aucun oiseau n'est visible, réponds avec : "Aucun oiseau détecté"

Formate ta réponse en JSON uniquement, sans texte avant ou après :
{
  "birds": [
    {
      "species": "nom de l'espèce en français",
      "scientific_name": "nom scientifique",
      "confidence": "élevé/moyen/faible",
      "description": "caractéristiques visuelles en français",
      "location": "position dans l'image en français"
    }
  ],
  "count": nombre_d_oiseaux,
  "timestamp": "heure_actuelle"
}"""
                        }
                    ],
                }
            ],
        )

        response_text = message.content[0].text

        # Try to extract JSON from the response
        # Claude often includes text before/after the JSON block
        try:
            # First try direct parsing
            return json.loads(response_text)
        except json.JSONDecodeError:
            # Try to extract JSON from markdown code blocks
            json_match = re.search(r'```json\s*(\{.*?\})\s*```', response_text, re.DOTALL)
            if json_match:
                try:
                    return json.loads(json_match.group(1))
                except json.JSONDecodeError:
                    pass

            # Try to find any JSON object in the text
            json_match = re.search(r'\{[^{}]*"birds"[^{}]*\}', response_text, re.DOTALL)
            if json_match:
                try:
                    return json.loads(json_match.group(0))
                except json.JSONDecodeError:
                    pass

            # If all parsing fails, return raw response
            return {
                "birds": [],
                "count": 0,
                "raw_response": response_text,
                "timestamp": datetime.now().isoformat()
            }

    except Exception as e:
        print(f"Error analyzing frame: {e}")
        return {
            "error": str(e),
            "timestamp": datetime.now().isoformat()
        }

async def handle_analyze_request(websocket):
    """Handle an analyze request from a client"""
    print(f"[{datetime.now().strftime('%H:%M:%S')}] Received analyze request")

    # Capture frame
    frame = capture_frame_from_stream()

    if frame is None:
        error_response = {
            "error": "Could not capture frame from stream",
            "timestamp": datetime.now().isoformat()
        }
        await websocket.send(json.dumps(error_response))
        return

    # Convert to base64
    frame_base64 = frame_to_base64(frame)

    # Analyze with Claude
    print("Analyzing frame with Claude Vision API...")
    detection_result = await analyze_frame_with_claude(frame_base64)

    # Add timestamp
    detection_result["timestamp"] = datetime.now().isoformat()

    # Print results
    if detection_result.get("count", 0) > 0:
        print(f"✓ Detected {detection_result['count']} bird(s):")
        for bird in detection_result.get("birds", []):
            print(f"  - {bird.get('species')} ({bird.get('confidence')})")
    else:
        print("No birds detected")

    # Send response to client
    await websocket.send(json.dumps(detection_result))

async def websocket_handler(websocket):
    """Handle WebSocket connections and messages"""
    connected_clients.add(websocket)
    print(f"Client connected. Total clients: {len(connected_clients)}")

    try:
        async for message in websocket:
            try:
                data = json.loads(message)
                if data.get('action') == 'analyze':
                    await handle_analyze_request(websocket)
            except json.JSONDecodeError:
                print(f"Invalid JSON received: {message}")
            except Exception as e:
                print(f"Error handling message: {e}")
    finally:
        connected_clients.remove(websocket)
        print(f"Client disconnected. Total clients: {len(connected_clients)}")

async def main():
    """Start WebSocket server"""
    # Start WebSocket server
    ws_server = await websockets.serve(websocket_handler, "0.0.0.0", WEBSOCKET_PORT)
    print(f"WebSocket server started on port {WEBSOCKET_PORT}")
    print("Waiting for analyze requests from clients...")

    # Keep running
    await ws_server.wait_closed()

if __name__ == "__main__":
    if not ANTHROPIC_API_KEY:
        print("Error: ANTHROPIC_API_KEY environment variable not set")
        exit(1)

    print("Bird Detection Service (On-Demand Mode)")
    print("=" * 50)
    print(f"Stream URL: {STREAM_URL}")
    print(f"WebSocket port: {WEBSOCKET_PORT}")
    print("=" * 50)

    asyncio.run(main())