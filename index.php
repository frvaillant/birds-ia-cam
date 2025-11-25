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

    <!-- Selection overlay for drawing rectangle -->
    <div id="selection-overlay" class="hidden">
        <canvas id="selection-canvas"></canvas>
        <div id="selection-instructions">
            Dessinez un rectangle autour de la zone à analyser
            <div class="selection-buttons">
                <button id="cancel-selection">Annuler</button>
                <button id="validate-selection" disabled>Analyser</button>
            </div>
        </div>
    </div>

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

    // Détecter si on est en local (port 8888 pour PHP ou 8080 pour nginx direct) ou en production (via nginx reverse proxy)
    const isLocal = ['8080', '8888'].includes(window.location.port);
    const streamUrl = isLocal ? "http://localhost:8080/live/camera/index.m3u8" : "/live/camera/index.m3u8";

    const detectionsDiv = document.getElementById('detections');
    const statusDiv = document.getElementById('detection-status');
    const statusText = document.getElementById('status-text');

    // Initialize HLS video
    let hls;
    let videoInitialized = false;

    function initializeVideo() {
        if (Hls.isSupported()) {
            // Destroy previous instance if exists
            if (hls) {
                hls.destroy();
            }

            hls = new Hls();
            hls.loadSource(streamUrl);
            hls.attachMedia(video);
            hls.on(Hls.Events.MANIFEST_PARSED, () => {
                video.play();
                videoInitialized = true;
            });
            hls.on(Hls.Events.ERROR, (event, data) => {
                if (data.fatal) {
                    console.log('HLS fatal error, stream may have stopped');
                    videoInitialized = false;
                }
            });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = streamUrl;
            video.load();
            video.play();
            videoInitialized = true;
        } else {
            alert("Votre navigateur ne supporte pas HLS.");
        }
    }

    function reinitializeVideoIfNeeded() {
        // Only reinitialize if video was previously working but may have stopped
        if (videoInitialized && (video.paused || video.readyState < 2)) {
            console.log('Reinitializing video stream after reconnection');
            initializeVideo();
        }
    }

    // Initialize video on page load
    initializeVideo();

    // WebSocket connection for bird detections
    let ws;
    let reconnectInterval = null;
    let wasConnected = false;
    let isReconnecting = false;
    let isAnalyzing = false;

    function connectWebSocket() {
        // Prevent multiple simultaneous connection attempts
        if (ws && ws.readyState === WebSocket.CONNECTING) {
            return;
        }

        // Use different URL for local vs production
        let wsUrl;
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
            statusDiv.className = 'detection-status status-active';
            statusText.textContent = 'Detection Active';

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
                // Wait longer for HLS stream to be ready after Docker restart
                let retryCount = 0;
                const maxRetries = 5;

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

            // Re-enable detection toggle
            const detectionToggle = document.getElementById('detection-toggle');
            detectionToggle.disabled = false;

            // Automatically turn on detection when service comes online
            detectionToggle.checked = true;

            // Show UI elements and reset detections message
            document.getElementById('analyze-button').classList.remove('hidden');
            document.getElementById('detection-overlay').classList.remove('hidden');
            detectionsDiv.innerHTML = '<div class="no-detection">Cliquer sur "Identifier les espèces" pour tenter de trouver leur nom</div>';
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

            // Only disable UI if we were previously connected
            if (wasConnected) {
                // Disable detection toggle and hide UI elements when offline
                const detectionToggle = document.getElementById('detection-toggle');
                detectionToggle.checked = false;
                detectionToggle.disabled = true;
                document.getElementById('analyze-button').classList.add('hidden');
                document.getElementById('detection-overlay').classList.add('hidden');
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

    function displayDetections(data) {
        // Ignore status messages (like delete confirmations)
        if (data.status && !data.birds && !data.count) {
            return;
        }

        // Mark analysis as complete
        isAnalyzing = false;

        // Re-enable analyze button
        analyzeButton.disabled = false;
        analyzeButton.classList.remove('analyzing');
        analyzeButton.textContent = '🔍 Identifier les espèces';

        if (data.error) {
            detectionsDiv.innerHTML = `<div class="no-detection">Error: ${data.error} - please retry</div>`;
            return;
        }

        // Check if no birds were found
        if (data.count === 0 || !data.birds || data.birds.length === 0) {
            let html = '<div class="no-detection">Aucun oiseau trouvé</div>';
            if (data.raw_response) {
                html += `<div class="no-detection" style="margin-top: 10px; font-size: 11px;">${data.raw_response}</div>`;
            }
            if (data.timestamp) {
                html += `<div class="timestamp">Last check: ${formatTimestamp(data.timestamp)}</div>`;
            }
            detectionsDiv.innerHTML = html;
            return;
        }

        // Birds were found - display them
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

    // Selection overlay functionality
    const selectionOverlay = document.getElementById('selection-overlay');
    const selectionCanvas = document.getElementById('selection-canvas');
    const selectionCtx = selectionCanvas.getContext('2d');
    const cancelSelectionBtn = document.getElementById('cancel-selection');
    const validateSelectionBtn = document.getElementById('validate-selection');

    let capturedFrame = null;
    let capturedFrameCanvas = null;
    let isDrawing = false;
    let startX, startY, currentX, currentY;
    let selectedRect = null;

    // Analyze button functionality
    const analyzeButton = document.getElementById('analyze-button');

    analyzeButton.addEventListener('click', () => {
        if (ws && ws.readyState === WebSocket.OPEN) {
            // Delete previous captures before starting new analysis
            ws.send(JSON.stringify({ action: 'delete_captures' }));

            // Capture frame from video element
            capturedFrameCanvas = document.createElement('canvas');

            // Resize to max 1280px width to reduce message size
            const maxWidth = 1280;
            const scale = Math.min(1, maxWidth / video.videoWidth);
            capturedFrameCanvas.width = video.videoWidth * scale;
            capturedFrameCanvas.height = video.videoHeight * scale;

            const ctx = capturedFrameCanvas.getContext('2d');
            ctx.drawImage(video, 0, 0, capturedFrameCanvas.width, capturedFrameCanvas.height);

            // Show selection overlay
            showSelectionOverlay();
        } else {
            alert('Detection service is not connected');
        }
    });

    function showSelectionOverlay() {
        // Setup canvas
        const videoRect = video.getBoundingClientRect();
        selectionCanvas.width = capturedFrameCanvas.width;
        selectionCanvas.height = capturedFrameCanvas.height;

        // Draw captured frame on selection canvas
        selectionCtx.drawImage(capturedFrameCanvas, 0, 0);

        // Show overlay
        selectionOverlay.classList.remove('hidden');
        selectedRect = null;
        validateSelectionBtn.disabled = true;
    }

    function hideSelectionOverlay() {
        selectionOverlay.classList.add('hidden');
        capturedFrameCanvas = null;
        selectedRect = null;
    }

    // Canvas drawing handlers
    selectionCanvas.addEventListener('mousedown', (e) => {
        const rect = selectionCanvas.getBoundingClientRect();
        startX = (e.clientX - rect.left) * (selectionCanvas.width / rect.width);
        startY = (e.clientY - rect.top) * (selectionCanvas.height / rect.height);
        isDrawing = true;
    });

    selectionCanvas.addEventListener('mousemove', (e) => {
        if (!isDrawing) return;

        const rect = selectionCanvas.getBoundingClientRect();
        currentX = (e.clientX - rect.left) * (selectionCanvas.width / rect.width);
        currentY = (e.clientY - rect.top) * (selectionCanvas.height / rect.height);

        // Redraw image
        selectionCtx.clearRect(0, 0, selectionCanvas.width, selectionCanvas.height);
        selectionCtx.drawImage(capturedFrameCanvas, 0, 0);

        // Draw rectangle
        selectionCtx.strokeStyle = '#4CAF50';
        selectionCtx.lineWidth = 3;
        selectionCtx.strokeRect(startX, startY, currentX - startX, currentY - startY);

        // Draw semi-transparent overlay outside selection
        selectionCtx.fillStyle = 'rgba(0, 0, 0, 0.5)';
        selectionCtx.fillRect(0, 0, selectionCanvas.width, selectionCanvas.height);
        selectionCtx.clearRect(startX, startY, currentX - startX, currentY - startY);
        selectionCtx.drawImage(capturedFrameCanvas, startX, startY, currentX - startX, currentY - startY,
                              startX, startY, currentX - startX, currentY - startY);
        selectionCtx.strokeRect(startX, startY, currentX - startX, currentY - startY);
    });

    selectionCanvas.addEventListener('mouseup', (e) => {
        if (!isDrawing) return;
        isDrawing = false;

        const rect = selectionCanvas.getBoundingClientRect();
        currentX = (e.clientX - rect.left) * (selectionCanvas.width / rect.width);
        currentY = (e.clientY - rect.top) * (selectionCanvas.height / rect.height);

        // Normalize rectangle coordinates
        const x = Math.min(startX, currentX);
        const y = Math.min(startY, currentY);
        const width = Math.abs(currentX - startX);
        const height = Math.abs(currentY - startY);

        if (width > 10 && height > 10) {
            selectedRect = { x, y, width, height };
            validateSelectionBtn.disabled = false;
        }
    });

    // Cancel selection
    cancelSelectionBtn.addEventListener('click', () => {
        hideSelectionOverlay();
    });

    // Validate selection and send for analysis
    validateSelectionBtn.addEventListener('click', () => {
        if (!selectedRect) {
            console.error('No selection rectangle found');
            return;
        }

        // Save reference to selected rectangle before hiding overlay
        const rectToAnalyze = { ...selectedRect };
        const canvasToAnalyze = capturedFrameCanvas;

        // Hide selection overlay
        hideSelectionOverlay();

        // Show analyzing state
        analyzeButton.disabled = true;
        analyzeButton.classList.add('analyzing');
        analyzeButton.textContent = '🔄 Analyse en cours...';
        detectionsDiv.innerHTML = '<div class="analyzing-in-progress">Analyse en cours...</div>';

        // Crop the image to selected rectangle
        const croppedCanvas = document.createElement('canvas');
        croppedCanvas.width = rectToAnalyze.width;
        croppedCanvas.height = rectToAnalyze.height;
        const croppedCtx = croppedCanvas.getContext('2d');
        croppedCtx.drawImage(canvasToAnalyze,
            rectToAnalyze.x, rectToAnalyze.y, rectToAnalyze.width, rectToAnalyze.height,
            0, 0, rectToAnalyze.width, rectToAnalyze.height
        );

        // Convert to base64
        const frameBase64 = croppedCanvas.toDataURL('image/jpeg', 0.8).split(',')[1];

        console.log(`Sending cropped frame: ${croppedCanvas.width}x${croppedCanvas.height}, size: ${(frameBase64.length / 1024).toFixed(0)}KB`);

        // Send analyze request with cropped frame to backend
        ws.send(JSON.stringify({
            action: 'analyze',
            frame: frameBase64
        }));

        // Re-enable button after response (timeout as backup)
        setTimeout(() => {
            analyzeButton.disabled = false;
            analyzeButton.classList.remove('analyzing');
            analyzeButton.textContent = '🔍 Identifier les espèces';
            isAnalyzing = false;
        }, 10000); // 10 second timeout
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
