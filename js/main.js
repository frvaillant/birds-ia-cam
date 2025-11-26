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

    // Initialize fullscreen functionality
    initializeFullscreen();

    // Set initial message
    const detectionsDiv = document.getElementById('detections');
    detectionsDiv.innerHTML = '<div class="no-detection">Cliquer sur "Identifier un oiseau" pour tenter de trouver leur nom</div>';
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

    // Position fullscreen button at bottom-right of video
    video.addEventListener('loadedmetadata', positionFullscreenButton);
    window.addEventListener('resize', positionFullscreenButton);

    // Initial positioning
    setTimeout(positionFullscreenButton, 100);
}