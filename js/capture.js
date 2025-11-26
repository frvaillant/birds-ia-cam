// Image Capture Functionality

let captureTimeout = null;
let captureIntervalId = null;
let capturedImageData = null;
let screenshotWs = null;

// Connect to screenshot WebSocket service (separate from bird detection)
function connectScreenshotWebSocket() {
    const isLocal = ['8080', '8888'].includes(window.location.port);
    let wsUrl;

    if (isLocal) {
        wsUrl = 'ws://localhost:8766';
    } else {
        const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsHost = window.location.host;
        wsUrl = `${wsProtocol}//${wsHost}/ws-screenshot`;
    }

    screenshotWs = new WebSocket(wsUrl);

    screenshotWs.onopen = () => {
        console.log('Connected to screenshot service');
    };

    screenshotWs.onerror = (error) => {
        console.error('Screenshot WebSocket error:', error);
    };

    screenshotWs.onclose = () => {
        console.log('Disconnected from screenshot service');
        // Try to reconnect after 5 seconds
        setTimeout(connectScreenshotWebSocket, 5000);
    };
}

// Initialize screenshot WebSocket on load
connectScreenshotWebSocket();

function initializeCaptureButton() {
    const captureButton = document.getElementById('capture-button');
    const video = document.getElementById('video');

    captureButton.addEventListener('click', () => {
        captureImage(video);
    });

    // Add keyboard shortcut: Ctrl + Shift + Space (Cmd + Shift + Space on Mac)
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === ' ') {
            e.preventDefault(); // Prevent default browser behavior
            captureImage(video);
        }
    });
}

function captureImage(video) {
    // Delete any previous captures first
    if (capturedImageData) {
        deleteCapturedImage();
    }

    // Create canvas to capture frame
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    // Convert to base64
    const imageBase64 = canvas.toDataURL('image/jpeg', 0.9).split(',')[1];

    // Send to screenshot server to save
    if (screenshotWs && screenshotWs.readyState === WebSocket.OPEN) {
        screenshotWs.send(JSON.stringify({
            action: 'save_capture',
            image: imageBase64
        }));
    }

    // Store capture data
    capturedImageData = {
        base64: canvas.toDataURL('image/jpeg', 0.9),
        timestamp: Date.now()
    };

    // Show preview overlay
    showCapturePreview(capturedImageData.base64);

    // Start 10 second deletion timeout
    startCaptureTimeout(10000);
}

function showCapturePreview(imageDataUrl) {
    // Remove existing preview if any
    let preview = document.getElementById('capture-preview');
    if (!preview) {
        preview = document.createElement('div');
        preview.id = 'capture-preview';
        preview.className = 'capture-preview';
        document.getElementById('video-container').appendChild(preview);
    }

    preview.innerHTML = `
        <div class="capture-preview-header">
            <span>📸 Photo capturée</span>
            <span id="capture-timer" class="capture-timer">10s</span>
        </div>
        <img src="${imageDataUrl}" alt="Capture" class="capture-preview-image">
        <div class="capture-preview-actions">
            <button id="download-capture" class="capture-action-btn download-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 256 256">
                    <path d="M224,144v64a8,8,0,0,1-8,8H40a8,8,0,0,1-8-8V144a8,8,0,0,1,16,0v56H208V144a8,8,0,0,1,16,0Zm-101.66,5.66a8,8,0,0,0,11.32,0l40-40a8,8,0,0,0-11.32-11.32L136,124.69V40a8,8,0,0,0-16,0v84.69L93.66,98.34a8,8,0,0,0-11.32,11.32Z"></path>
                </svg>
                Télécharger
            </button>
            <button id="delete-capture" class="capture-action-btn delete-btn">
                Annuler
            </button>
        </div>
    `;

    // Attach event listeners
    document.getElementById('download-capture').addEventListener('click', downloadCapturedImage);
    document.getElementById('delete-capture').addEventListener('click', deleteCapturedImage);
}

function downloadCapturedImage() {
    if (!capturedImageData) return;

    // Clear the 10s timeout
    if (captureTimeout) {
        clearTimeout(captureTimeout);
    }

    // Create download link
    const link = document.createElement('a');
    link.href = capturedImageData.base64;
    link.download = `bird-capture-${new Date().toISOString().replace(/:/g, '-').split('.')[0]}.jpg`;
    link.click();

    // Start 20 second timeout after download
    startCaptureTimeout(20000);
}

function deleteCapturedImage() {
    // Clear timeout and interval
    if (captureTimeout) {
        clearTimeout(captureTimeout);
        captureTimeout = null;
    }
    if (captureIntervalId) {
        clearInterval(captureIntervalId);
        captureIntervalId = null;
    }

    // Remove preview
    const preview = document.getElementById('capture-preview');
    if (preview) {
        preview.remove();
    }

    // Delete from screenshot server
    if (screenshotWs && screenshotWs.readyState === WebSocket.OPEN) {
        screenshotWs.send(JSON.stringify({
            action: 'delete_captures'
        }));
    }

    capturedImageData = null;
}

function startCaptureTimeout(duration) {
    // Clear existing timeout and interval
    if (captureTimeout) {
        clearTimeout(captureTimeout);
    }
    if (captureIntervalId) {
        clearInterval(captureIntervalId);
    }

    const timerElement = document.getElementById('capture-timer');
    let remainingSeconds = Math.floor(duration / 1000);

    // Update timer display every second
    captureIntervalId = setInterval(() => {
        remainingSeconds--;
        if (timerElement) {
            timerElement.textContent = `${remainingSeconds}s`;
        }
        if (remainingSeconds <= 0) {
            clearInterval(captureIntervalId);
        }
    }, 1000);

    // Set main timeout
    captureTimeout = setTimeout(() => {
        clearInterval(captureIntervalId);
        deleteCapturedImage();
    }, duration);
}