<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Stream - Bird Detection</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>

<div id="video-container">
    <video id="video" autoplay muted playsinline></video>

    <button id="analyze-button">📷 Identifier un oiseau</button>

    <button id="fullscreen-button" title="Plein écran">⛶</button>

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
        <div class="overlay-header">
            <div class="overlay-title">🦅 Identification</div>
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
        <div id="detections">
            <div class="no-detection">Connection au service d'analyse...</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script src="js/video.js"></script>
<script src="js/websocket.js"></script>
<script src="js/ui.js"></script>
<script src="js/selection.js"></script>
<script src="js/main.js"></script>

</body>
</html>