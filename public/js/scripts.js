const lightbox = document.getElementById('lightbox');
const lightboxImg = document.getElementById('lightbox-img');
const lightboxCaption = document.getElementById('lightbox-caption');
const libImageInput = document.getElementById('library_image_input');
const glitchForm = document.getElementById('glitchForm');
let currentLibFile = '';
let libraryImages = [];
let currentIndex = -1;
let selectedImages = new Set();

function handleItemClick(element, src, filename) {
    const mode = document.querySelector('input[name="interaction-mode"]:checked').value;
    if (mode === 'select') {
        toggleSelect(element, src, filename);
    } else {
        openLightbox(src, filename);
    }
}

function toggleSelect(element, src, filename) {
    if (selectedImages.has(src)) {
        selectedImages.delete(src);
        element.classList.remove('selected');
    } else {
        selectedImages.add(src);
        element.classList.add('selected');
    }
}

function morphSelected() {
    glitchForm.dispatchEvent(new Event('morph'));
}

// Initialize library images array
function initLibraryImages() {
    libraryImages = [];
    const items = document.querySelectorAll('.library-item img');
    items.forEach((img, index) => {
        libraryImages.push({
            src: img.getAttribute('src'),
            filename: img.getAttribute('alt')
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initLibraryImages();
});

function openLightbox(src, filename) {
    currentIndex = libraryImages.findIndex(img => img.src === src);
    updateLightbox();
    lightbox.style.display = 'flex';
}

function updateLightbox() {
    if (currentIndex >= 0 && currentIndex < libraryImages.length) {
        const img = libraryImages[currentIndex];
        lightboxImg.src = img.src;
        lightboxCaption.textContent = img.filename;
        currentLibFile = img.src;
    }
}

function nextImage() {
    if (libraryImages.length === 0) return;
    currentIndex = (currentIndex + 1) % libraryImages.length;
    updateLightbox();
}

function prevImage() {
    if (libraryImages.length === 0) return;
    currentIndex = (currentIndex - 1 + libraryImages.length) % libraryImages.length;
    updateLightbox();
}

function closeLightbox() {
    lightbox.style.display = 'none';
}

const selectLibBtn = document.getElementById('select-lib-btn');
if (selectLibBtn) {
    selectLibBtn.addEventListener('click', () => {
        libImageInput.value = currentLibFile;
        // Clear file input to prioritize library selection
        document.getElementById('photo').value = '';
        closeLightbox();
        glitchForm.dispatchEvent(new Event('reset-effects'));
        glitchForm.requestSubmit();
    });
}

window.onclick = function(event) {
    if (event.target == lightbox) {
        closeLightbox();
    }
}

document.addEventListener('keydown', (e) => {
    if (lightbox.style.display === 'flex') {
        if (e.key === 'ArrowRight') nextImage();
        if (e.key === 'ArrowLeft') prevImage();
        if (e.key === 'Escape') closeLightbox();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const controls = glitchForm.querySelectorAll('input[type="range"], input[type="checkbox"], input[type="color"], select');
    const preview = document.getElementById('glitched-preview');
    const downloadLink = document.getElementById('download-link');
    const sourceFileInput = document.getElementById('source_file');
    const photoInput = document.getElementById('photo');
    const submitButton = glitchForm.querySelector('button[type="submit"]');
    const saveButton = document.getElementById('save-output-btn');
    const morphButton = document.getElementById('morph-btn');
    const status = document.getElementById('editor-status');
    const presetFilter = document.getElementById('preset_filter');
    const duotonePalette = document.getElementById('duotone-palette');
    const duotonePaletteStatus = document.getElementById('duotone-palette-status');
    let active = false;
    let pendingRender = false;
    let timeout = null;
    let revision = 0;
    let resultCurrent = Boolean(preview.getAttribute('src'));

    function showStatus(message, error = false) {
        status.textContent = message;
        status.classList.toggle('error', error);
    }

    function updateBusyState() {
        const busy = active || pendingRender;
        photoInput.disabled = busy;
        submitButton.disabled = busy;
        selectLibBtn.disabled = busy;
        morphButton.disabled = busy;
        saveButton.disabled = busy || !resultCurrent;
        downloadLink.setAttribute('aria-disabled', String(busy || !resultCurrent));
        document.getElementById('result-container').setAttribute('aria-busy', String(busy));
        submitButton.textContent = photoInput.files.length || !sourceFileInput.value ? 'Upload and Glitch!' : 'Apply Effects';
    }

    async function post(formData) {
        const response = await fetch(glitchForm.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) {
            throw new Error('The request failed. Please try again (HTTP ' + response.status + ').');
        }
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Unable to process this image.');
        return data;
    }

    function addLibraryImage(src, gridId) {
        const grid = document.getElementById(gridId);
        const filename = src.split('/').pop();
        if (libraryImages.some(image => image.src === src)) return;
        const item = document.createElement('div');
        item.className = 'library-item';
        item.addEventListener('click', () => handleItemClick(item, src, filename));
        const badge = document.createElement('div');
        badge.className = 'select-badge';
        badge.textContent = '✓';
        const image = document.createElement('img');
        image.src = src;
        image.alt = filename;
        image.loading = 'lazy';
        image.decoding = 'async';
        const inspect = document.createElement('div');
        inspect.className = 'view-overlay';
        inspect.textContent = 'Inspect';
        inspect.addEventListener('click', event => {
            event.stopPropagation();
            openLightbox(src, filename);
        });
        item.append(badge, image, inspect);
        grid.querySelector('p')?.remove();
        grid.prepend(item);
        initLibraryImages();
    }

    async function render(formData, kind = 'edit') {
        if (active) return;
        active = true;
        pendingRender = false;
        resultCurrent = false;
        const requestRevision = revision;
        updateBusyState();
        showStatus(kind === 'morph' ? 'Blending selected images…' : 'Applying effects…');
        try {
            const data = await post(formData);
            if (!data.glitchedImage || !data.sourceFile) throw new Error('The server returned no image. Please try again.');
            sourceFileInput.value = data.sourceFile;
            if (kind !== 'edit') {
                photoInput.value = '';
                libImageInput.value = '';
            }
            if (data.libraryImage) addLibraryImage(data.libraryImage, 'library-grid');
            if (kind === 'morph') {
                selectedImages.clear();
                document.querySelectorAll('.library-item.selected').forEach(item => item.classList.remove('selected'));
                // A composite becomes the new original. Apply the visible controls
                // to it so that the next slider adjustment does not suddenly jump.
                pendingRender = true;
            } else if (requestRevision === revision) {
                const image = new Image();
                image.src = data.glitchedImage;
                await image.decode();
                if (requestRevision === revision) {
                    preview.src = data.glitchedImage;
                    downloadLink.href = data.glitchedImage;
                    document.getElementById('result-container').style.display = 'block';
                    document.getElementById('placeholder')?.remove();
                    resultCurrent = true;
                    showStatus('Ready.');
                }
            }
        } catch (error) {
            if (kind !== 'edit') {
                pendingRender = false;
                clearTimeout(timeout);
                timeout = null;
            }
            showStatus(error instanceof SyntaxError ? 'The server returned an invalid response. Please try again.' : error.message, true);
        } finally {
            active = false;
            updateBusyState();
            flushRender();
        }
    }

    function flushRender() {
        if (active || !pendingRender || timeout !== null) return;
        if (!sourceFileInput.value) {
            pendingRender = false;
            updateBusyState();
            return;
        }
        const formData = new FormData(glitchForm);
        formData.delete('photo');
        formData.delete('library_image');
        render(formData);
    }

    function scheduleRender() {
        revision++;
        clearTimeout(timeout);
        if (!sourceFileInput.value && !active) return;
        pendingRender = true;
        resultCurrent = false;
        updateBusyState();
        showStatus('Updating preview…');
        timeout = setTimeout(() => {
            timeout = null;
            flushRender();
        }, 180);
    }

    function updateValue(control) {
        const value = document.getElementById('val_' + control.id);
        if (value) value.textContent = control.value;
    }

    function updateDuotonePalette() {
        const isActive = presetFilter.value === 'duotone';
        duotonePalette.classList.toggle('is-active', isActive);
        duotonePaletteStatus.textContent = isActive
            ? 'Active — choose any two colors'
            : 'Choose Duotone Gradient to activate';
    }

    function resetEffects() {
        controls.forEach(control => {
            if (control.type === 'range') {
                control.value = '0';
                updateValue(control);
            } else if (control.type === 'checkbox') {
                control.checked = false;
            } else if (control.tagName === 'SELECT') {
                control.selectedIndex = 0;
                control.value = control.options[0]?.value ?? control.options[0] ?? '';
            }
        });
        updateDuotonePalette();
    }

    controls.forEach(control => {
        control.addEventListener(control.type === 'checkbox' ? 'change' : 'input', () => {
            if (control.id === 'duotone_shadow' || control.id === 'duotone_highlight') {
                presetFilter.value = 'duotone';
            }
            updateValue(control);
            updateDuotonePalette();
            scheduleRender();
        });
    });

    updateDuotonePalette();
    glitchForm.addEventListener('reset-effects', resetEffects);

    document.getElementById('random-glitch-btn').addEventListener('click', () => {
        controls.forEach(control => {
            if (control.type === 'range') {
                const min = Number(control.min);
                const step = Number(control.step) || 1;
                const steps = Math.floor((Number(control.max) - min) / step);
                control.value = min + Math.floor(Math.random() * (steps + 1)) * step;
                updateValue(control);
            } else if (control.type === 'checkbox') {
                control.checked = Math.random() > 0.8;
            } else if (control.tagName === 'SELECT') {
                control.selectedIndex = Math.floor(Math.random() * control.options.length);
            } else if (control.type === 'color') {
                control.value = '#' + Math.floor(Math.random() * 16777216).toString(16).padStart(6, '0');
            }
        });
        updateDuotonePalette();
        scheduleRender();
    });

    glitchForm.addEventListener('submit', event => {
        event.preventDefault();
        if (active || pendingRender) return;
        if (!photoInput.files.length && !libImageInput.value && !sourceFileInput.value) {
            showStatus('Choose a JPEG or PNG to begin.', true);
            return;
        }
        revision++;
        render(new FormData(glitchForm), 'source');
    });

    photoInput.addEventListener('change', () => {
        libImageInput.value = '';
        resetEffects();
        updateBusyState();
    });

    glitchForm.addEventListener('morph', () => {
        if (active || pendingRender) return;
        if (selectedImages.size < 2) {
            showStatus('Choose Composite mode and select at least two images to morph.', true);
            return;
        }
        revision++;
        const formData = new FormData();
        formData.append('action', 'morph');
        selectedImages.forEach(src => formData.append('images[]', src));
        render(formData, 'morph');
    });

    downloadLink.addEventListener('click', event => {
        if (downloadLink.getAttribute('aria-disabled') === 'true') event.preventDefault();
    });

    saveButton.addEventListener('click', async () => {
        if (active || pendingRender || !resultCurrent) return;
        active = true;
        updateBusyState();
        showStatus('Saving to collection…');
        const formData = new FormData();
        formData.append('action', 'save_to_output');
        formData.append('filename', new URL(preview.src).pathname.split('/').pop());
        try {
            const data = await post(formData);
            addLibraryImage(data.outputImage, 'output-grid');
            showStatus('Saved to collection.');
        } catch (error) {
            showStatus(error.message, true);
        } finally {
            active = false;
            updateBusyState();
            flushRender();
        }
    });

    updateBusyState();
});
