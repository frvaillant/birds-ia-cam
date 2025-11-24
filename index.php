<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Stream - Bird Detection</title>
    <link rel="stylesheet" href="style.css">
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
