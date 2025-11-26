// Main Application Initialization

document.addEventListener('DOMContentLoaded', () => {
    // Initialize video stream
    initializeVideo();

    // Connect to WebSocket
    connectWebSocket();

    // Initialize detection toggle
    initializeDetectionToggle();

    // Initialize bird analysis and selection
    initializeAnalyzeButton();
    initializeCanvasDrawing();
    initializeSelectionButtons();

    // Initialize capture button
    initializeCaptureButton();

    // Initialize fullscreen functionality
    initializeFullscreen();

    // Set initial state - detection is off by default
    hideDetectionUI();

    const detectionsDiv = document.getElementById('detections');
    detectionsDiv.innerHTML = '<div class="no-detection">Activez la détection pour identifier les oiseaux</div>';
});

function initializeDetectionToggle() {
    const detectionToggle = document.getElementById('detection-toggle');
    const statusText = document.getElementById('status-text');

    detectionToggle.addEventListener('change', (e) => {
        const isEnabled = e.target.checked;
        const detectionsDiv = document.getElementById('detections');

        if (isEnabled) {
            // Show detection UI
            showDetectionUI();
            detectionsDiv.innerHTML = '<div class="no-detection">Cliquer sur "Identifier un oiseau" pour tenter de trouver leur nom</div>';

            // Update status text if connected (ws is global from websocket.js)
            if (typeof ws !== 'undefined' && ws.readyState === WebSocket.OPEN) {
                statusText.textContent = 'Detection Active';
            }
        } else {
            // Hide detection UI (but keep the status/switch visible)
            hideDetectionUI();

            // Update status text
            statusText.textContent = 'Détection désactivée';
        }
    });
}

function initializeFullscreen() {
    const fullscreenButton = document.getElementById('fullscreen-button');
    const videoContainer = document.getElementById('video-container');
    const video = document.getElementById('video');

    // Fullscreen toggle
    fullscreenButton.addEventListener('click', () => {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            videoContainer.requestFullscreen().catch(err => {
                console.error('Error attempting to enable fullscreen:', err);
            });
        }
    });

    // Update button icon when fullscreen state changes
    document.addEventListener('fullscreenchange', () => {
        if (document.fullscreenElement) {
            fullscreenButton.textContent = '⛶';
            fullscreenButton.title = 'Quitter le plein écran';
        } else {
            fullscreenButton.textContent = '⛶';
            fullscreenButton.title = 'Plein écran';
        }
    });

    // Position buttons at bottom-right of video
    video.addEventListener('loadedmetadata', positionVideoButtons);
    window.addEventListener('resize', positionVideoButtons);

    // Initial positioning
    setTimeout(positionVideoButtons, 100);
}

function positionVideoButtons() {
    const video = document.getElementById('video');
    const videoContainer = document.getElementById('video-container');
    const fullscreenButton = document.getElementById('fullscreen-button');
    const captureButton = document.getElementById('capture-button');

    const videoRect = video.getBoundingClientRect();
    const containerRect = videoContainer.getBoundingClientRect();

    // Calculate position relative to container
    const rightOffset = containerRect.right - videoRect.right + 10;
    const bottomOffset = containerRect.bottom - videoRect.bottom + 10;

    fullscreenButton.style.right = rightOffset + 'px';
    fullscreenButton.style.bottom = bottomOffset + 'px';

    captureButton.style.right = (rightOffset + 50) + 'px';
    captureButton.style.bottom = bottomOffset + 'px';
}