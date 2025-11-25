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

# Store connected WebSocket clients and their captures
connected_clients = set()
user_captures = {}  # Maps websocket to list of their capture filenames

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

def draw_bounding_boxes(frame, birds):
    """Draw bounding boxes around detected birds"""
    if not birds:
        return frame

    frame_height, frame_width = frame.shape[:2]
    annotated_frame = frame.copy()

    for bird in birds:
        if 'bbox' not in bird:
            continue

        bbox = bird['bbox']

        # Convert percentage coordinates to pixel coordinates
        x = int(bbox['x'] * frame_width / 100)
        y = int(bbox['y'] * frame_height / 100)
        width = int(bbox['width'] * frame_width / 100)
        height = int(bbox['height'] * frame_height / 100)

        # Choose color based on confidence
        confidence = bird.get('confidence', 'faible').lower()
        if confidence == 'élevé' or confidence == 'high':
            color = (0, 255, 0)  # Green
        elif confidence == 'moyen' or confidence == 'medium':
            color = (0, 255, 255)  # Yellow
        else:
            color = (0, 165, 255)  # Orange

        # Draw a filled circle at the center of the bounding box
        circle_center = (x + width // 2, y + height // 2)
        circle_radius = 25
        cv2.circle(annotated_frame, circle_center, circle_radius, color, -1)

        # Add a white border to make it more visible
        cv2.circle(annotated_frame, circle_center, circle_radius, (255, 255, 255), 2)

    return annotated_frame

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

IMPORTANT : N'identifie que les oiseaux réels qui ont l'air vrais à l'image. Réagis comme un ornithologue professionnel. Si tu as un doute, ne dis rien. Tu peux en revanche, si tu es sûr de la famille ou du genre de l'oiseau, répondre quelque chose comme "Rapace indéterminé" ou "Corvidés indéterminé".

GUIDE D'IDENTIFICATION - Espèces souvent confondues :

MÉSANGES (attention aux détails !) :
- Mésange charbonnière (Parus major) : bande ventrale noire LARGE (en tous cas visible) et continue du menton au bas-ventre, joues blanches éclatantes, grande taille (14-15cm), calotte noire brillante, le ventre a clairement des teintes jaunes et le dos, notamment dans le haut, des teintes vert/jaune/olive
- Mésange noire (Periparus ater) : TACHE BLANCHE sur la NUQUE (derrière la tête) - c'est la clé !, bande ventrale noire fine ou ABSENTE mais généralement absente, plus petite (11cm), calotte noire mate, PAS DE JAUNE SUR LE VENTRE, dos à dominante clairement GRISE : on dirait un dos noir et blanc, pas de couleurs spécifique
- Mésange bleue (Cyanistes caeruleus) : calotte bleue vif, ailes et queue bleues, joues blanches avec trait noir sur l'œil
- Mésange huppée (Lophophanes cristatus) : HUPPE pointue noire et blanche très visible, dos brun/marron moyen et ventre brun/fauve clair
- Mésange nonnette (Poecile palustris) : calotte noire mate, SANS bande nucale blanche, menton noir, joues blanches sales
- Si hésitation entre charbonnière et noire : cherche la tache nucale blanche (noire) ou la bande ventrale plus ou moins large (charbonnière)

MOINEAUX :
- Moineau domestique mâle (Passer domesticus) : calotte grise, joue blanche, bavette noire, dos brun strié
- Moineau domestique femelle : entièrement brun-beige strié, sourcil clair
- Moineau friquet (Passer montanus) : calotte MARRON (pas grise), tache noire sur joue blanche, plus petit
- Si hésitation : le friquet a TOUJOURS une tache noire sur la joue, le domestique mâle a une calotte grise

PINSONS :
- Pinson des arbres (Fringilla coelebs) : poitrine rosée, double barre alaire blanche bien visible
- Verdier d'Europe (Chloris chloris) : corps vert-jaune, bec fort et conique
- Chardonneret élégant (Carduelis carduelis) : masque facial rouge vif, ailes noires avec barre jaune

ROUGES-GORGES vs ROUGEQUEUES :
- Rouge-gorge (Erithacus rubecula) : plastron orange-roux sur poitrine ET face
- Rougequeue noir (Phoenicurus ochruros) : queue rousse en mouvement, corps gris-noir, mâle avec bavette noire

En cas de doute entre deux espèces proches, indique "Mésange sp." ou "Moineau sp." avec mention des deux possibilités dans la description.

Pour chaque oiseau détecté, fournis :
1. Nom de l'espèce (commun en français et scientifique)
2. Niveau de confiance : "élevé", "moyen", ou "faible"
3. Brève description des caractéristiques visuelles qui ont aidé à l'identifier
4. Position approximative dans l'image : "gauche"/"centre"/"droite", "haut"/"milieu"/"bas"
5. Coordonnées de la bounding box : position x,y du coin supérieur gauche et largeur/hauteur en pourcentages (0-100) de la zone de l'image qui contient l'oiseau.

Si aucun oiseau n'est visible, réponds avec : "Aucun oiseau détecté"

Formate ta réponse en JSON uniquement, sans texte avant ou après :
{
  "birds": [
    {
      "species": "nom de l'espèce en français",
      "scientific_name": "nom scientifique",
      "confidence": "élevé/moyen/faible",
      "description": "caractéristiques visuelles en français",
      "location": "position dans l'image en français",
      "bbox": {
        "x": pourcentage_x,
        "y": pourcentage_y,
        "width": pourcentage_largeur,
        "height": pourcentage_hauteur
      }
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

    # Generate unique identifier for this user and capture
    user_id = id(websocket)
    timestamp_str = datetime.now().strftime("%Y%m%d_%H%M%S")
    frame_filename = f"captures/user_{user_id}_frame_{timestamp_str}.jpg"

    # Create captures directory if it doesn't exist
    os.makedirs("captures", exist_ok=True)

    # Save the frame
    import cv2 as cv
    cv.imwrite(frame_filename, frame)
    print(f"Frame saved: {frame_filename}")

    # Track this capture for this user
    if websocket not in user_captures:
        user_captures[websocket] = []
    user_captures[websocket].append(frame_filename)

    # Analyze with Claude
    print("Analyzing frame with Claude Vision API...")
    detection_result = await analyze_frame_with_claude(frame_base64)

    # Print results
    if detection_result.get("count", 0) > 0:
        print(f"✓ Detected {detection_result['count']} bird(s):")
        for bird in detection_result.get("birds", []):
            print(f"  - {bird.get('species')} ({bird.get('confidence')})")

        # Draw bounding boxes on the frame
        annotated_frame = draw_bounding_boxes(frame, detection_result.get("birds", []))

        # Save annotated frame
        annotated_filename = f"captures/user_{user_id}_annotated_{timestamp_str}.jpg"
        cv.imwrite(annotated_filename, annotated_frame)
        print(f"Annotated frame saved: {annotated_filename}")

        # Track annotated file too
        user_captures[websocket].append(annotated_filename)

        # Convert annotated frame to base64
        annotated_base64 = frame_to_base64(annotated_frame)
        detection_result["captured_image"] = f"data:image/jpeg;base64,{annotated_base64}"
        detection_result["annotated_filename"] = annotated_filename
    else:
        print("No birds detected")
        # Use original frame if no birds detected
        detection_result["captured_image"] = f"data:image/jpeg;base64,{frame_base64}"

    # Add timestamp and metadata
    detection_result["timestamp"] = datetime.now().isoformat()
    detection_result["saved_filename"] = frame_filename

    # Send response to client
    await websocket.send(json.dumps(detection_result))

async def handle_delete_captures(websocket):
    """Delete all captures for a specific user"""
    if websocket not in user_captures:
        return

    deleted_count = 0
    for filename in user_captures[websocket]:
        try:
            if os.path.exists(filename):
                os.remove(filename)
                deleted_count += 1
                print(f"Deleted: {filename}")
        except Exception as e:
            print(f"Error deleting {filename}: {e}")

    # Clear the list
    user_captures[websocket] = []
    print(f"Deleted {deleted_count} files for user {id(websocket)}")

async def websocket_handler(websocket):
    """Handle WebSocket connections and messages"""
    connected_clients.add(websocket)
    user_captures[websocket] = []  # Initialize empty capture list for this user
    print(f"Client connected. Total clients: {len(connected_clients)}")

    try:
        async for message in websocket:
            try:
                data = json.loads(message)
                if data.get('action') == 'analyze':
                    await handle_analyze_request(websocket)
                elif data.get('action') == 'delete_captures':
                    await handle_delete_captures(websocket)
                    await websocket.send(json.dumps({"status": "deleted"}))
            except json.JSONDecodeError:
                print(f"Invalid JSON received: {message}")
            except Exception as e:
                print(f"Error handling message: {e}")
    finally:
        # Clean up user's captures when they disconnect
        if websocket in user_captures:
            print(f"Cleaning up captures for disconnected user {id(websocket)}")
            await handle_delete_captures(websocket)
            del user_captures[websocket]

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
