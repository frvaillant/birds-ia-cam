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

    <button id="capture-button" title="Capturer une photo (ctrl + espace / cmd + shift + espace)">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 256 256">
            <path d="M208,56H180.28L166.65,35.56A8,8,0,0,0,160,32H96a8,8,0,0,0-6.65,3.56L75.71,56H48A24,24,0,0,0,24,80V192a24,24,0,0,0,24,24H208a24,24,0,0,0,24-24V80A24,24,0,0,0,208,56Zm8,136a8,8,0,0,1-8,8H48a8,8,0,0,1-8-8V80a8,8,0,0,1,8-8H80a8,8,0,0,0,6.66-3.56L100.28,48h55.43l13.63,20.44A8,8,0,0,0,176,72h32a8,8,0,0,1,8,8ZM128,88a44,44,0,1,0,44,44A44.05,44.05,0,0,0,128,88Zm0,72a28,28,0,1,1,28-28A28,28,0,0,1,128,160Z"></path>
        </svg>
    </button>

    <button id="fullscreen-button" title="Plein écran">⛶</button>

    <!-- Selection overlay for drawing rectangle -->
    <div id="selection-overlay" class="hidden">
        <canvas id="selection-canvas"></canvas>
        <div id="selection-instructions">
            <p>
                Dessinez un rectangle autour de l'oiseau à identifier
            </p>
            <p class="small-text">
                Privilégiez les moments où les oiseaux sont au premier plan et de profil
            </p>
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
                        <input type="checkbox" id="detection-toggle">
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
<script src="js/image-processing.js"></script>
<script src="js/selection.js"></script>
<script src="js/capture.js"></script>
<script src="js/main.js"></script>

</body>
</html>
