// UI Management Functions

function updateDetectionStatus(status, text) {
    const statusDiv = document.getElementById('detection-status');
    const statusText = document.getElementById('status-text');

    statusDiv.className = `detection-status status-${status}`;
    statusText.textContent = text;
}

function showDetectionUI() {
    document.getElementById('analyze-button').classList.remove('hidden');
    document.querySelector('.overlay-title').classList.remove('hidden');
    document.getElementById('detections').classList.remove('hidden');
}

function hideDetectionUI() {
    document.getElementById('analyze-button').classList.add('hidden');
    document.querySelector('.overlay-title').classList.add('hidden');
    document.getElementById('detections').classList.add('hidden');
}

function formatTimestamp(isoString) {
    const date = new Date(isoString);
    return date.toLocaleTimeString('fr-FR');
}

function displayDetections(data) {
    const detectionsDiv = document.getElementById('detections');
    const analyzeButton = document.getElementById('analyze-button');

    // Ignore status messages (like delete confirmations)
    if (data.status && !data.birds && !data.count) {
        return;
    }

    // Re-enable analyze button
    analyzeButton.disabled = false;
    analyzeButton.classList.remove('analyzing');
    analyzeButton.textContent = '📷 Identifier un oiseau';

    // Delete all captures after displaying results
    sendWebSocketMessage({ action: 'delete_captures' });

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

    // Group birds by species
    const birdsBySpecies = {};
    data.birds.forEach(bird => {
        const speciesKey = bird.species || 'Inconnu';
        if (!birdsBySpecies[speciesKey]) {
            birdsBySpecies[speciesKey] = {
                species: bird.species,
                scientific_name: bird.scientific_name,
                confidence: bird.confidence,
                description: bird.description,
                locations: [],
                count: 0
            };
        }
        birdsBySpecies[speciesKey].count++;
        if (bird.location) {
            birdsBySpecies[speciesKey].locations.push(bird.location);
        }
    });

    // Display grouped birds
    let html = '';
    Object.values(birdsBySpecies).forEach(birdGroup => {
        // Map French confidence levels to CSS classes
        const confidenceMap = {
            'élevé': 'high',
            'moyen': 'medium',
            'faible': 'low',
            'high': 'high',
            'medium': 'medium',
            'low': 'low'
        };
        const confidenceKey = (birdGroup.confidence || 'faible').toLowerCase();
        const confidenceClass = `confidence-${confidenceMap[confidenceKey] || 'low'}`;

        // Format species name with count
        const speciesDisplay = birdGroup.count > 1
            ? `${birdGroup.count} ${birdGroup.species}s`
            : birdGroup.species;

        html += `
            <div class="bird-detection">
                <div class="bird-species">${speciesDisplay || 'Inconnu'}</div>
                ${birdGroup.scientific_name ? `<div class="bird-scientific">${birdGroup.scientific_name}</div>` : ''}
                <div class="bird-info">
                    <span class="confidence-badge ${confidenceClass}">
                        ${(birdGroup.confidence || 'faible').toUpperCase()}
                    </span>
                </div>
                ${birdGroup.locations.length > 0 ? `<div class="bird-location">📍 ${birdGroup.locations.join(', ')}</div>` : ''}
                ${birdGroup.description ? `<div class="bird-description">${birdGroup.description}</div>` : ''}
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

function resetDetections() {
    const detectionsDiv = document.getElementById('detections');
    detectionsDiv.innerHTML = '<div class="no-detection">Cliquer sur "Identifier un oiseau" pour tenter de trouver leur nom</div>';

    // Delete previous captures from server
    sendWebSocketMessage({ action: 'delete_captures' });
}