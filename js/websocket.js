// WebSocket Management for Bird Detection

let ws;
let reconnectInterval = null;
let wasConnected = false;
let isReconnecting = false;

function connectWebSocket() {
    // Prevent multiple simultaneous connection attempts
    if (ws && ws.readyState === WebSocket.CONNECTING) {
        return;
    }

    // Use different URL for local vs production
    let wsUrl;
    const isLocal = ['8080', '8888'].includes(window.location.port);

    if (isLocal) {
        // En local, connexion directe au port 8765
        wsUrl = 'ws://localhost:8765';
    } else {
        // En production, via nginx reverse proxy
        const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsHost = window.location.host;
        wsUrl = `${wsProtocol}//${wsHost}/ws`;
    }
    ws = new WebSocket(wsUrl);

    ws.onopen = () => {
        console.log('Connected to bird detection service');
        updateDetectionStatus('active', 'Detection Active');

        // Clear reconnection interval
        if (reconnectInterval) {
            clearInterval(reconnectInterval);
            reconnectInterval = null;
        }

        const isReconnection = isReconnecting;
        isReconnecting = false;
        wasConnected = true;

        // Reinitialize video stream after Docker restart (if it was a reconnection)
        if (isReconnection) {
            reinitializeVideoAfterReconnection();
        }

        // Re-enable detection toggle
        const detectionToggle = document.getElementById('detection-toggle');
        detectionToggle.disabled = false;

        // Automatically turn on detection when service comes online
        detectionToggle.checked = true;

        // Show UI elements and reset detections message
        showDetectionUI();
        const detectionsDiv = document.getElementById('detections');
        detectionsDiv.innerHTML = '<div class="no-detection">Cliquer sur "Identifier un oiseau" pour tenter de trouver leur nom</div>';
    };

    ws.onmessage = (event) => {
        const data = JSON.parse(event.data);
        displayDetections(data);
    };

    ws.onerror = (error) => {
        console.error('WebSocket error:', error);
    };

    ws.onclose = () => {
        console.log('Disconnected from bird detection service');
        updateDetectionStatus('inactive', 'Detection Offline');

        // Only disable UI if we were previously connected
        if (wasConnected) {
            // Disable detection toggle and hide UI elements when offline
            const detectionToggle = document.getElementById('detection-toggle');
            detectionToggle.checked = false;
            detectionToggle.disabled = true;
            hideDetectionUI();
        }

        // Try to reconnect every 5 seconds (only if not already reconnecting)
        if (!isReconnecting) {
            isReconnecting = true;
            reconnectInterval = setInterval(() => {
                console.log('Attempting to reconnect...');
                connectWebSocket();
            }, 5000);
        }
    };
}

function reinitializeVideoAfterReconnection() {
    // Wait longer for HLS stream to be ready after Docker restart
    let retryCount = 0;
    const maxRetries = 5;
    const streamUrl = ['8080', '8888'].includes(window.location.port)
        ? "http://localhost:8080/live/camera/index.m3u8"
        : "/live/camera/index.m3u8";

    const tryReinitVideo = () => {
        retryCount++;
        console.log(`Attempting to reinitialize video (attempt ${retryCount}/${maxRetries})`);

        // Check if HLS stream is available before reinitializing
        fetch(streamUrl, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    console.log('HLS stream is ready, reinitializing video');
                    initializeVideo();
                } else {
                    throw new Error('Stream not ready');
                }
            })
            .catch(error => {
                if (retryCount < maxRetries) {
                    console.log('HLS stream not ready yet, retrying in 2 seconds...');
                    setTimeout(tryReinitVideo, 2000);
                } else {
                    console.log('Failed to reinitialize video after max retries');
                }
            });
    };

    setTimeout(tryReinitVideo, 2000);
}

function sendWebSocketMessage(message) {
    if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify(message));
    }
}