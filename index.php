<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Stream - Bird Detection</title>
    <style>
        body {
            background: #222;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            width: 100vw;
            margin: 0;
            font-family: Arial, sans-serif;
        }
        #video-container {
            position: relative;
            width: 90%;
            max-width: 90vw;
            max-height: 90vh;
        }
        video {
            width: 100%;
            border: 2px solid #444;
            border-radius: 8px;
            display: block;
        }
        #detection-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.85);
            padding: 15px;
            border-radius: 8px;
            max-width: 350px;
            max-height: 80%;
            overflow-y: auto;
            font-size: 14px;
            backdrop-filter: blur(10px);
        }
        .overlay-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #4CAF50;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 5px;
        }
        .bird-detection {
            margin-bottom: 15px;
            padding: 10px;
            background: rgba(76, 175, 80, 0.15);
            border-left: 3px solid #4CAF50;
            border-radius: 4px;
        }
        .bird-species {
            font-size: 16px;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 5px;
        }
        .bird-scientific {
            font-size: 12px;
            color: #aaa;
            font-style: italic;
            margin-bottom: 8px;
        }
        .bird-info {
            font-size: 12px;
            color: #ddd;
            margin: 3px 0;
        }
        .confidence-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .confidence-high {
            background: rgba(76, 175, 80, 0.3);
            color: #4CAF50;
        }
        .confidence-medium {
            background: rgba(255, 193, 7, 0.3);
            color: #FFC107;
        }
        .confidence-low {
            background: rgba(255, 152, 0, 0.3);
            color: #FF9800;
        }
        .no-detection {
            color: #999;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }
        .detection-status {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: rgba(0, 0, 0, 0.85);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .detection-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 20px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #4CAF50;
        }
        input:checked + .slider:before {
            transform: translateX(20px);
        }
        .hidden {
            display: none !important;
        }
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
            animation: pulse 2s infinite;
        }
        .status-active .status-dot {
            background: #4CAF50;
        }
        .status-inactive .status-dot {
            background: #f44336;
            animation: none;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .timestamp {
            font-size: 11px;
            color: #888;
            margin-top: 10px;
            text-align: center;
            border-top: 1px solid #444;
            padding-top: 8px;
        }
        #reset-button {
            width: 100%;
            margin-top: 10px;
            padding: 8px 16px;
            background: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        #reset-button:hover {
            background: #d32f2f;
        }
        #reset-button:active {
            transform: scale(0.98);
        }
        .bird-location {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }
        .bird-description {
            font-size: 12px;
            color: #ccc;
            margin-top: 5px;
            line-height: 1.4;
        }
        .captured-image {
            width: 100%;
            border-radius: 6px;
            margin-top: 15px;
            border: 1px solid #444;
        }
        #analyze-button {
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
            z-index: 10;
        }
        #analyze-button:hover {
            background: #45a049;
            transform: translateX(-50%) translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        }
        #analyze-button:active {
            transform: translateX(-50%) translateY(0);
        }
        #analyze-button:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
        }
        #analyze-button.analyzing {
            background: #FF9800;
        }
    </style>
</head>
<body>

<div id="video-container">
    <video id="video" controls autoplay></video>

    <button id="analyze-button">🔍 Identifier les espèces</button>

    <div id="detection-overlay">
        <div class="overlay-title">🦅 Identification</div>
        <div id="detections">
            <div class="no-detection">Connection au service d'analyse...</div>
        </div>
    </div>

    <div id="detection-status" class="detection-status status-inactive">
        <div style="display: flex; align-items: center; gap: 6px;">
            <span class="status-dot"></span>
            <span id="status-text">Démarrage...</span>
        </div>
        <label class="detection-toggle">
            <label class="switch">
                <input type="checkbox" id="detection-toggle" checked>
                <span class="slider"></span>
            </label>
        </label>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script>
    // Stream vidéo
    const video = document.getElementById('video');
    const streamUrl = "http://localhost:8080/live/camera/index.m3u8";
    const detectionsDiv = document.getElementById('detections');
    const statusDiv = document.getElementById('detection-status');
    const statusText = document.getElementById('status-text');

    // Initialize HLS video
    if (Hls.isSupported()) {
        const hls = new Hls();
        hls.loadSource(streamUrl);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
            video.play();
        });
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = streamUrl;
    } else {
        alert("Votre navigateur ne supporte pas HLS.");
    }

    // WebSocket connection for bird detections
    let ws;
    let reconnectInterval;

    function connectWebSocket() {
        ws = new WebSocket('ws://localhost:8765');

        ws.onopen = () => {
            console.log('Connected to bird detection service');
            statusDiv.className = 'detection-status status-active';
            statusText.textContent = 'Detection Active';
            clearInterval(reconnectInterval);
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
            statusDiv.className = 'detection-status status-inactive';
            statusText.textContent = 'Detection Offline';

            // Try to reconnect every 5 seconds
            reconnectInterval = setInterval(() => {
                console.log('Attempting to reconnect...');
                connectWebSocket();
            }, 5000);
        };
    }

    function displayDetections(data) {
        if (data.error) {
            detectionsDiv.innerHTML = `<div class="no-detection">Error: ${data.error}</div>`;
            return;
        }

        if (data.count === 0 || !data.birds || data.birds.length === 0) {
            detectionsDiv.innerHTML = '<div class="no-detection">Aucun oiseau trouvé</div>';
            if (data.raw_response) {
                detectionsDiv.innerHTML += `<div class="no-detection" style="margin-top: 10px; font-size: 11px;">${data.raw_response}</div>`;
            }
            if (data.timestamp) {
                detectionsDiv.innerHTML += `<div class="timestamp">Last check: ${formatTimestamp(data.timestamp)}</div>`;
            }
            return;
        }

        let html = '';
        data.birds.forEach((bird, index) => {
            // Map French confidence levels to CSS classes
            const confidenceMap = {
                'élevé': 'high',
                'moyen': 'medium',
                'faible': 'low',
                'high': 'high',
                'medium': 'medium',
                'low': 'low'
            };
            const confidenceKey = (bird.confidence || 'faible').toLowerCase();
            const confidenceClass = `confidence-${confidenceMap[confidenceKey] || 'low'}`;

            html += `
                <div class="bird-detection">
                    <div class="bird-species">${bird.species || 'Inconnu'}</div>
                    ${bird.scientific_name ? `<div class="bird-scientific">${bird.scientific_name}</div>` : ''}
                    <div class="bird-info">
                        <span class="confidence-badge ${confidenceClass}">
                            ${(bird.confidence || 'faible').toUpperCase()}
                        </span>
                    </div>
                    ${bird.location ? `<div class="bird-location">📍 ${bird.location}</div>` : ''}
                    ${bird.description ? `<div class="bird-description">${bird.description}</div>` : ''}
                </div>
            `;
        });

        // Add captured image if available
        if (data.captured_image) {
            html += `<img src="${data.captured_image}" alt="Image capturée" class="captured-image">`;
        }

        // Add reset button
        html += '<button id="reset-button">🗑️ Effacer</button>';

        detectionsDiv.innerHTML = html;

        // Attach reset button handler
        document.getElementById('reset-button').addEventListener('click', resetDetections);

        // Re-enable analyze button
        analyzeButton.disabled = false;
        analyzeButton.classList.remove('analyzing');
        analyzeButton.textContent = '🔍 Identifier les espèces';
    }

    function formatTimestamp(isoString) {
        const date = new Date(isoString);
        return date.toLocaleTimeString('fr-FR');
    }

    function resetDetections() {
        detectionsDiv.innerHTML = '<div class="no-detection">Cliquer sur "Identifier les espèces" pour tenter de trouver leur nom</div>';

        // Delete previous captures from server
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ action: 'delete_captures' }));
        }
    }

    // Analyze button functionality
    const analyzeButton = document.getElementById('analyze-button');

    analyzeButton.addEventListener('click', () => {
        if (ws && ws.readyState === WebSocket.OPEN) {
            // Delete previous captures before starting new analysis
            ws.send(JSON.stringify({ action: 'delete_captures' }));

            // Disable button and show analyzing state
            analyzeButton.disabled = true;
            analyzeButton.classList.add('analyzing');
            analyzeButton.textContent = '🔄 Analyse en cours...';

            detectionsDiv.innerHTML = '<div class="no-detection">Analyse en cours...</div>';

            // Send analyze request to backend
            ws.send(JSON.stringify({ action: 'analyze' }));

            // Re-enable button after response (timeout as backup)
            setTimeout(() => {
                analyzeButton.disabled = false;
                analyzeButton.classList.remove('analyzing');
                analyzeButton.textContent = '🔍 Identifier les espèces';
            }, 10000); // 10 second timeout
        } else {
            alert('Detection service is not connected');
        }
    });

    // Detection toggle functionality
    const detectionToggle = document.getElementById('detection-toggle');
    const analyzeButtonEl = document.getElementById('analyze-button');
    const detectionOverlay = document.getElementById('detection-overlay');

    detectionToggle.addEventListener('change', (e) => {
        const isEnabled = e.target.checked;

        if (isEnabled) {
            // Show detection UI
            analyzeButtonEl.classList.remove('hidden');
            detectionOverlay.classList.remove('hidden');
            detectionsDiv.innerHTML = '<div class="no-detection">Cliquer sur "Identifier les espèces" pour tenter de trouver leur nom</div>';

            // Update status text if connected
            if (ws && ws.readyState === WebSocket.OPEN) {
                statusText.textContent = 'Detection Active';
            }
        } else {
            // Hide detection UI
            analyzeButtonEl.classList.add('hidden');
            detectionOverlay.classList.add('hidden');

            // Update status text
            statusText.textContent = 'Détection désactivée';
        }
    });

    // Connect to WebSocket on page load
    connectWebSocket();

    // Initially show message to click button
    detectionsDiv.innerHTML = '<div class="no-detection">Cliquer sur "Identifier les espèces" pour tenter de trouver leur nom</div>';
</script>

</body>
</html>
