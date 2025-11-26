// Selection Overlay and Bird Analysis

let capturedFrameCanvas = null;
let isDrawing = false;
let startX, startY, currentX, currentY;
let selectedRect = null;

// Get DOM elements
const selectionOverlay = document.getElementById('selection-overlay');
const selectionCanvas = document.getElementById('selection-canvas');
const selectionCtx = selectionCanvas.getContext('2d');
const cancelSelectionBtn = document.getElementById('cancel-selection');
const validateSelectionBtn = document.getElementById('validate-selection');

function initializeAnalyzeButton() {
    const analyzeButton = document.getElementById('analyze-button');
    const video = document.getElementById('video');

    analyzeButton.addEventListener('click', () => {
        // Check WebSocket connection (ws is global from websocket.js)
        if (typeof ws !== 'undefined' && ws.readyState === WebSocket.OPEN) {
            // Delete previous captures before starting new analysis
            sendWebSocketMessage({ action: 'delete_captures' });

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
}

function showSelectionOverlay() {
    // Setup canvas
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

function initializeCanvasDrawing() {
    // Mouse down - start drawing
    selectionCanvas.addEventListener('mousedown', (e) => {
        const rect = selectionCanvas.getBoundingClientRect();
        startX = (e.clientX - rect.left) * (selectionCanvas.width / rect.width);
        startY = (e.clientY - rect.top) * (selectionCanvas.height / rect.height);
        isDrawing = true;
    });

    // Mouse move - draw rectangle
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

    // Mouse up - finish drawing
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
}

function initializeSelectionButtons() {
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
        const analyzeButton = document.getElementById('analyze-button');
        const detectionsDiv = document.getElementById('detections');

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
        sendWebSocketMessage({
            action: 'analyze',
            frame: frameBase64
        });

        // Re-enable button after response (timeout as backup)
        setTimeout(() => {
            analyzeButton.disabled = false;
            analyzeButton.classList.remove('analyzing');
            analyzeButton.textContent = '📷 Identifier un oiseau';
        }, 10000); // 10 second timeout
    });
}