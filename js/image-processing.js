// Image Processing Functions

// Enhancement settings
const SATURATION_BOOST = 1.5; // 1.0 = normal, 1.3 = +30% saturation

/**
 * Enhance image saturation to help AI recognition
 * @param {HTMLCanvasElement} canvas - Canvas containing the image to enhance
 * @param {number} saturationMultiplier - Saturation boost factor (1.0 = normal)
 */
function enhanceImageSaturation(canvas, saturationMultiplier) {
    const ctx = canvas.getContext('2d');
    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const data = imageData.data;

    for (let i = 0; i < data.length; i += 4) {
        const r = data[i];
        const g = data[i + 1];
        const b = data[i + 2];

        // Convert RGB to HSL
        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        const l = (max + min) / 2 / 255;

        let h = 0, s = 0;

        if (max !== min) {
            const d = max - min;
            s = l > 0.5 ? d / (510 - max - min) : d / (max + min);

            switch (max) {
                case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
                case g: h = ((b - r) / d + 2) / 6; break;
                case b: h = ((r - g) / d + 4) / 6; break;
            }
        }

        // Boost saturation
        s = Math.min(1, s * saturationMultiplier);

        // Convert back to RGB
        const hue2rgb = (p, q, t) => {
            if (t < 0) t += 1;
            if (t > 1) t -= 1;
            if (t < 1/6) return p + (q - p) * 6 * t;
            if (t < 1/2) return q;
            if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
            return p;
        };

        let newR, newG, newB;
        if (s === 0) {
            newR = newG = newB = l * 255;
        } else {
            const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            const p = 2 * l - q;
            newR = hue2rgb(p, q, h + 1/3) * 255;
            newG = hue2rgb(p, q, h) * 255;
            newB = hue2rgb(p, q, h - 1/3) * 255;
        }

        data[i] = Math.round(newR);
        data[i + 1] = Math.round(newG);
        data[i + 2] = Math.round(newB);
    }

    ctx.putImageData(imageData, 0, 0);
}

/**
 * Apply all image enhancements before sending to AI
 * @param {HTMLCanvasElement} canvas - Canvas to enhance
 */
function applyImageEnhancements(canvas) {
    enhanceImageSaturation(canvas, SATURATION_BOOST);
    // Add more enhancements here in the future
}
