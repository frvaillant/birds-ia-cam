// HLS Video Management

let hls;
let videoInitialized = false;
let hlsRetryInterval = null;
const HLS_RETRY_DELAY = 5000; // 5 seconds between retries

// Detect if running locally or in production
const isLocal = ['8080', '8888'].includes(window.location.port);
const streamUrl = isLocal ? "http://localhost:8080/live/camera/index.m3u8" : "/live/camera/index.m3u8";

function initializeVideo() {
    const video = document.getElementById('video');

    if (Hls.isSupported()) {
        // Destroy previous instance if exists
        if (hls) {
            hls.destroy();
        }

        hls = new Hls({
            enableWorker: true,
            lowLatencyMode: true,
            manifestLoadingMaxRetry: 3,
            manifestLoadingRetryDelay: 1000
        });
        hls.loadSource(streamUrl);
        hls.attachMedia(video);
        hls.on(Hls.Events.MANIFEST_PARSED, () => {
            video.play();
            videoInitialized = true;

            // Clear retry interval if it was running
            if (hlsRetryInterval) {
                clearInterval(hlsRetryInterval);
                hlsRetryInterval = null;
            }

            console.log('HLS stream loaded successfully');
        });
        hls.on(Hls.Events.ERROR, (event, data) => {
            if (data.fatal) {
                console.log('HLS fatal error, stream may have stopped');
                videoInitialized = false;

                // Start auto-retry mechanism (infinite retries)
                if (!hlsRetryInterval) {
                    console.log('Starting HLS auto-reconnection (every 5 seconds)...');
                    hlsRetryInterval = setInterval(() => {
                        console.log('Attempting to reconnect HLS stream...');

                        // Check if stream is available before reinitializing
                        fetch(streamUrl, { method: 'HEAD' })
                            .then(response => {
                                if (response.ok) {
                                    console.log('HLS stream is back, reinitializing...');
                                    initializeVideo();
                                } else {
                                    console.log('HLS stream not yet available (404)');
                                }
                            })
                            .catch(error => {
                                console.log('HLS stream not yet available (network error)');
                            });
                    }, HLS_RETRY_DELAY);
                }
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
    const video = document.getElementById('video');
    // Only reinitialize if video was previously working but may have stopped
    if (videoInitialized && (video.paused || video.readyState < 2)) {
        console.log('Reinitializing video stream after reconnection');
        initializeVideo();
    }
}