'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const proofInput = document.querySelector('[data-payment-proof-input]');
    const proofPreview = document.querySelector('[data-payment-proof-preview]');
    let proofObjectUrl = '';
    proofInput?.addEventListener('change', () => {
        if (proofObjectUrl) URL.revokeObjectURL(proofObjectUrl);
        proofObjectUrl = '';
        const file = proofInput.files?.[0];
        if (!proofPreview) return;
        proofPreview.replaceChildren();
        if (!file) {
            const empty = document.createElement('span');
            empty.textContent = 'No receipt selected';
            proofPreview.appendChild(empty);
            return;
        }
        const label = document.createElement('strong');
        label.textContent = `${file.name} · ${(file.size / (1024 * 1024)).toFixed(2)} MB`;
        proofPreview.appendChild(label);
        if (file.type.startsWith('image/')) {
            proofObjectUrl = URL.createObjectURL(file);
            const image = document.createElement('img');
            image.src = proofObjectUrl;
            image.alt = 'Selected payment receipt preview';
            proofPreview.appendChild(image);
        } else {
            const pdf = document.createElement('span');
            pdf.textContent = 'PDF receipt selected';
            proofPreview.appendChild(pdf);
        }
    });

    const proofDialog = document.querySelector('[data-proof-dialog]');
    const proofFrame = proofDialog?.querySelector('[data-proof-frame]');
    const proofTitle = proofDialog?.querySelector('[data-proof-title]');
    const closeProof = () => {
        if (!proofDialog) return;
        proofDialog.close();
        if (proofFrame) proofFrame.src = 'about:blank';
    };
    document.querySelectorAll('[data-proof-open]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!proofDialog || typeof proofDialog.showModal !== 'function') return;
            const url = button.dataset.proofUrl || '';
            if (!url.startsWith('/')) return;
            if (proofFrame) proofFrame.src = url;
            if (proofTitle) proofTitle.textContent = button.dataset.proofTitle || 'Payment proof';
            proofDialog.showModal();
        });
    });
    proofDialog?.querySelectorAll('[data-proof-close]').forEach((button) => button.addEventListener('click', closeProof));
    proofDialog?.addEventListener('click', (event) => {
        if (event.target === proofDialog) closeProof();
    });
});
