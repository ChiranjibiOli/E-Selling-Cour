'use strict';

(() => {
    const cardSelector = '.course-card, .catalog-card, .provided-course-card, .learning-course-card';
    const imageSelector = '.course-card-media img, .catalog-media img, .provided-course-thumb img, .learning-course-media img';

    const clamp = (value, minimum, maximum) => Math.min(maximum, Math.max(minimum, value));

    const applyFallback = (card) => {
        if (!(card instanceof HTMLElement) || card.dataset.courseToneReady === '1') return;
        card.style.setProperty('--course-tone', '18, 27, 44');
        card.style.setProperty('--course-glow', '92, 102, 184');
    };

    const normaliseCardControls = (card) => {
        card.querySelectorAll('.status-badge').forEach((badge) => {
            if (!(badge instanceof HTMLElement)) return;
            badge.style.width = 'auto';
            badge.style.height = 'auto';
            badge.style.minHeight = '0';
            badge.style.display = 'inline-flex';
            badge.style.placeItems = 'initial';
        });
    };

    const sampleBottomTone = (image, card) => {
        if (!(image instanceof HTMLImageElement) || !(card instanceof HTMLElement)) return;
        if (!image.complete || image.naturalWidth < 1 || image.naturalHeight < 1) return;

        try {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d', { willReadFrequently: true });
            if (!context) return;

            const sampleWidth = 64;
            const sampleHeight = 28;
            canvas.width = sampleWidth;
            canvas.height = sampleHeight;

            const sourceHeight = Math.max(1, Math.floor(image.naturalHeight * 0.32));
            const sourceY = Math.max(0, image.naturalHeight - sourceHeight);
            context.drawImage(
                image,
                0,
                sourceY,
                image.naturalWidth,
                sourceHeight,
                0,
                0,
                sampleWidth,
                sampleHeight,
            );

            const pixels = context.getImageData(0, 0, sampleWidth, sampleHeight).data;
            let red = 0;
            let green = 0;
            let blue = 0;
            let weightTotal = 0;

            for (let index = 0; index < pixels.length; index += 4) {
                const alpha = pixels[index + 3] / 255;
                if (alpha < 0.08) continue;

                const pixelRed = pixels[index];
                const pixelGreen = pixels[index + 1];
                const pixelBlue = pixels[index + 2];
                const maximum = Math.max(pixelRed, pixelGreen, pixelBlue);
                const minimum = Math.min(pixelRed, pixelGreen, pixelBlue);
                const saturation = maximum === 0 ? 0 : (maximum - minimum) / maximum;
                const weight = alpha * (0.72 + saturation * 0.55);

                red += pixelRed * weight;
                green += pixelGreen * weight;
                blue += pixelBlue * weight;
                weightTotal += weight;
            }

            if (weightTotal <= 0) return;

            const averageRed = clamp(Math.round(red / weightTotal), 0, 255);
            const averageGreen = clamp(Math.round(green / weightTotal), 0, 255);
            const averageBlue = clamp(Math.round(blue / weightTotal), 0, 255);
            const luminance = (averageRed * 0.2126) + (averageGreen * 0.7152) + (averageBlue * 0.0722);
            const darkening = luminance > 185 ? 0.25 : luminance > 125 ? 0.34 : luminance > 72 ? 0.44 : 0.58;

            const toneRed = clamp(Math.round((averageRed * darkening) + 5), 7, 105);
            const toneGreen = clamp(Math.round((averageGreen * darkening) + 7), 9, 105);
            const toneBlue = clamp(Math.round((averageBlue * darkening) + 12), 14, 120);

            card.style.setProperty('--course-tone', `${toneRed}, ${toneGreen}, ${toneBlue}`);
            card.style.setProperty('--course-glow', `${averageRed}, ${averageGreen}, ${averageBlue}`);
            card.dataset.courseToneReady = '1';
        } catch (error) {
            applyFallback(card);
        }
    };

    const wireCard = (card) => {
        if (!(card instanceof HTMLElement)) return;
        normaliseCardControls(card);
        applyFallback(card);

        const image = card.querySelector(imageSelector) || card.querySelector('img');
        if (!(image instanceof HTMLImageElement)) return;
        if (image.dataset.courseToneWired !== '1') {
            image.dataset.courseToneWired = '1';
            image.addEventListener('load', () => {
                delete card.dataset.courseToneReady;
                sampleBottomTone(image, card);
            });
            image.addEventListener('error', () => applyFallback(card));
        }
        if (image.complete && image.naturalWidth > 0) {
            window.requestAnimationFrame(() => sampleBottomTone(image, card));
        }
    };

    const scan = (root = document) => {
        if (root instanceof HTMLElement && root.matches(cardSelector)) wireCard(root);
        root.querySelectorAll?.(cardSelector).forEach(wireCard);
    };

    const start = () => {
        scan(document);
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof HTMLElement)) return;
                    const card = node.closest(cardSelector);
                    if (card) wireCard(card);
                    scan(node);
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
