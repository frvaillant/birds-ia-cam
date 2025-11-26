#!/usr/bin/env python3
"""
Screenshot Service
Handles image capture and storage for users
"""

import cv2
import base64
import json
import asyncio
import websockets
import os
from datetime import datetime

# Configuration
WEBSOCKET_PORT = 8766  # Different port from bird detector

# Store connected WebSocket clients and their captures
connected_clients = set()
user_captures = {}  # Maps websocket to list of their capture filenames

async def handle_save_capture(websocket, image_base64):
    """Save a captured image from the user"""
    try:
        # Decode base64 to image
        import numpy as np
        nparr = np.frombuffer(base64.b64decode(image_base64), np.uint8)
        frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

        if frame is None:
            raise ValueError("Failed to decode image")

        # Generate unique identifier for this user and capture
        user_id = id(websocket)
        timestamp_str = datetime.now().strftime("%Y%m%d_%H%M%S_%f")[:-3]  # Include milliseconds
        capture_filename = f"captures/user_{user_id}_capture_{timestamp_str}.jpg"

        # Create captures directory if it doesn't exist
        os.makedirs("captures", exist_ok=True)

        # Save the image
        cv2.imwrite(capture_filename, frame)
        print(f"[{datetime.now().strftime('%H:%M:%S')}] Capture saved: {capture_filename}")

        # Track this capture for this user
        if websocket not in user_captures:
            user_captures[websocket] = []
        user_captures[websocket].append(capture_filename)

        # Send confirmation to client
        await websocket.send(json.dumps({
            "status": "saved",
            "filename": capture_filename
        }))

    except Exception as e:
        print(f"Error saving capture: {e}")
        await websocket.send(json.dumps({
            "status": "error",
            "error": str(e)
        }))

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
                print(f"[{datetime.now().strftime('%H:%M:%S')}] Deleted: {filename}")
        except Exception as e:
            print(f"Error deleting {filename}: {e}")

    # Clear the list
    user_captures[websocket] = []
    print(f"[{datetime.now().strftime('%H:%M:%S')}] Deleted {deleted_count} files for user {id(websocket)}")

async def websocket_handler(websocket):
    """Handle WebSocket connections and messages"""
    connected_clients.add(websocket)
    user_captures[websocket] = []  # Initialize empty capture list for this user
    print(f"[{datetime.now().strftime('%H:%M:%S')}] Screenshot client connected. Total clients: {len(connected_clients)}")

    try:
        async for message in websocket:
            try:
                data = json.loads(message)

                if data.get('action') == 'save_capture':
                    # Save captured image
                    image_base64 = data.get('image')
                    if image_base64:
                        await handle_save_capture(websocket, image_base64)
                    else:
                        await websocket.send(json.dumps({
                            "status": "error",
                            "error": "No image data provided"
                        }))

                elif data.get('action') == 'delete_captures':
                    await handle_delete_captures(websocket)
                    await websocket.send(json.dumps({"status": "deleted"}))

            except json.JSONDecodeError:
                print(f"Invalid JSON received: {message[:100]}...")
            except Exception as e:
                print(f"Error handling message: {e}")

    finally:
        # Clean up user's captures when they disconnect
        if websocket in user_captures:
            print(f"[{datetime.now().strftime('%H:%M:%S')}] Cleaning up captures for disconnected user {id(websocket)}")
            await handle_delete_captures(websocket)
            del user_captures[websocket]

        connected_clients.remove(websocket)
        print(f"[{datetime.now().strftime('%H:%M:%S')}] Screenshot client disconnected. Total clients: {len(connected_clients)}")

async def main():
    """Start WebSocket server"""
    # Start WebSocket server with increased message size limit (10MB for base64 images)
    ws_server = await websockets.serve(
        websocket_handler,
        "0.0.0.0",
        WEBSOCKET_PORT,
        max_size=10 * 1024 * 1024  # 10MB
    )
    print(f"Screenshot WebSocket server started on port {WEBSOCKET_PORT}")
    print("Waiting for capture requests from clients...")
    print("=" * 50)

    # Keep running
    await ws_server.wait_closed()

if __name__ == "__main__":
    print("Screenshot Service")
    print("=" * 50)
    print(f"WebSocket port: {WEBSOCKET_PORT}")
    print("=" * 50)

    asyncio.run(main())