const lightbox = document.getElementById('lightbox');
const lightboxImg = document.getElementById('lightbox-img');
const lightboxCaption = document.getElementById('lightbox-caption');
const libImageInput = document.getElementById('library_image_input');
const glitchForm = document.getElementById('glitchForm');
let currentLibFile = '';
let libraryImages = [];
let currentIndex = -1;
let selectedImages = new Set();
let selectedPostId = '';

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
        document.dispatchEvent(new Event('clear-post-selection'));
        selectedPostId = '';
        const postSelect = document.getElementById('post-select');
        if (postSelect) postSelect.value = '';
        document.getElementById('post-editor-collage')?.setAttribute('hidden', '');
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
    const postSelect = document.getElementById('post-select');
    const postButton = document.getElementById('glitch-post-btn');
    const postStatus = document.getElementById('post-status');
    const posts = JSON.parse(document.getElementById('post-data').textContent);
    const postEditor = document.getElementById('post-editor-collage');
    const postEditorGrid = document.getElementById('post-editor-grid');
    let batchActive = false;
    let pendingPostPreview = false;
    let postPreviewTimeout = null;
    let postPreviewReady = false;
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
        postSelect.disabled = busy;
        postButton.disabled = busy || !postSelect.value || !postPreviewReady;
        controls.forEach(control => { control.disabled = batchActive; });
        document.getElementById('random-glitch-btn').disabled = batchActive;
        document.getElementById('achims-special-btn').disabled = batchActive;
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

    function showPostImages(post, containerId, downloadable = false) {
        const container = document.getElementById(containerId);
        container.replaceChildren();
        post.images.forEach(src => {
            const filename = src.split('/').pop();
            const figure = document.createElement('figure');
            figure.className = 'post-image';
            const image = document.createElement('img');
            image.src = src;
            image.alt = filename.startsWith('post_0.') ? 'Post overview' : filename;
            image.loading = 'lazy';
            const caption = document.createElement('figcaption');
            const link = document.createElement('a');
            link.href = src;
            if (downloadable) link.download = filename;
            else {
                link.target = '_blank';
                link.rel = 'noopener';
            }
            link.textContent = (downloadable ? 'Download ' : 'View ') + filename;
            caption.append(link);
            figure.append(image, caption);
            container.append(figure);
        });
    }

    document.addEventListener('clear-post-selection', () => {
        selectedPostId = '';
        postPreviewReady = false;
        pendingPostPreview = false;
        clearTimeout(postPreviewTimeout);
        postPreviewTimeout = null;
        postEditor.hidden = true;
        postSelect.value = '';
    });

    postSelect.addEventListener('change', () => {
        const selected = posts.find(post => post.id === postSelect.value);
        document.getElementById('post-result').hidden = true;
        postEditorGrid.replaceChildren();
        selectedPostId = selected?.id ?? '';
        postPreviewReady = false;
        sourceFileInput.value = '';
        resultCurrent = false;
        postStatus.classList.remove('error');
        if (selected) {
            showPostImages(selected, 'post-editor-grid');
            postEditor.hidden = false;
            document.getElementById('result-container').style.display = 'none';
            document.getElementById('placeholder')?.remove();
            postStatus.textContent = selected.images.length + ' images loaded into the editing canvas. Change a control to preview the effect.';
        } else {
            postEditor.hidden = true;
            postStatus.textContent = 'Select a post to see its images.';
        }
        updateBusyState();
    });

    postButton.addEventListener('click', async () => {
        if (active || pendingRender) return;
        const selected = posts.find(post => post.id === postSelect.value);
        if (!selected) return;
        // Snapshot before disabling controls so every image receives identical settings.
        const formData = new FormData(glitchForm);
        ['photo', 'library_image', 'source_file'].forEach(key => formData.delete(key));
        formData.set('action', 'glitch_post');
        formData.set('post_id', selected.id);
        active = true;
        batchActive = true;
        updateBusyState();
        postStatus.classList.remove('error');
        postStatus.textContent = 'Saving all ' + selected.images.length + ' glitched images…';
        const result = document.getElementById('post-result');
        result.hidden = true;
        postEditor.setAttribute('aria-busy', 'true');
        try {
            const data = await post(formData);
            if (!data.post?.images?.length) throw new Error('The server returned no post. Please try again.');
            showPostImages(data.post, 'post-result-images', true);
            document.getElementById('post-result-path').textContent = 'Saved to public/posts/' + data.post.id + '/';
            const captionLink = document.getElementById('post-caption-link');
            captionLink.hidden = !data.post.caption;
            if (data.post.caption) captionLink.href = data.post.caption;
            result.hidden = false;
            postPreviewReady = false;
            posts.unshift(data.post);
            const option = document.createElement('option');
            option.value = data.post.id;
            option.textContent = data.post.title + ' — ' + data.post.id;
            postSelect.append(option);
            postStatus.textContent = 'Saved a new post with all ' + data.post.images.length + ' glitched images. Your originals are unchanged.';
        } catch (error) {
            postStatus.textContent = error instanceof SyntaxError ? 'The server returned an invalid response. Please try again.' : error.message;
            postStatus.classList.add('error');
        } finally {
            active = false;
            batchActive = false;
            postEditor.setAttribute('aria-busy', 'false');
            updateBusyState();
            flushRender();
        }
    });

    async function renderPostPreview() {
        if (active || !selectedPostId || postPreviewTimeout !== null) return;
        active = true;
        pendingPostPreview = false;
        resultCurrent = false;
        const requestRevision = revision;
        updateBusyState();
        postStatus.textContent = 'Updating the collage preview…';
        const formData = new FormData(glitchForm);
        ['photo', 'library_image', 'source_file'].forEach(key => formData.delete(key));
        formData.set('action', 'preview_post');
        formData.set('post_id', selectedPostId);
        try {
            const data = await post(formData);
            if (!data.post?.images?.length) throw new Error('The server returned no collage. Please try again.');
            if (requestRevision === revision && selectedPostId === data.post.id) {
                showPostImages(data.post, 'post-editor-grid');
                postEditor.hidden = false;
                postPreviewReady = true;
                postStatus.textContent = 'Preview updated. Save when the whole post looks right.';
            }
        } catch (error) {
            postStatus.textContent = error instanceof SyntaxError ? 'The server returned an invalid response. Please try again.' : error.message;
            postStatus.classList.add('error');
        } finally {
            active = false;
            updateBusyState();
            flushRender();
        }
    }

    function flushPostPreview() {
        if (!selectedPostId || active || !pendingPostPreview || postPreviewTimeout !== null) return;
        renderPostPreview();
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
        if (selectedPostId) {
            flushPostPreview();
            return;
        }
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
        if (selectedPostId) {
            clearTimeout(postPreviewTimeout);
            pendingPostPreview = true;
            postPreviewReady = false;
            resultCurrent = false;
            postStatus.classList.remove('error');
            postStatus.textContent = 'Preview queued…';
            postPreviewTimeout = setTimeout(() => {
                postPreviewTimeout = null;
                flushPostPreview();
            }, 180);
            updateBusyState();
            return;
        }
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
        const randomInteger = (minimum, maximum) => minimum + Math.floor(Math.random() * (maximum - minimum + 1));
        const profiles = [
            { mode: 'signal', preset: 'none', effects: ['rgb_shift', 'jitter', 'scanlines'], count: 2 },
            { mode: 'vhs', preset: 'vintage', effects: ['rgb_shift', 'scanlines', 'chaos'], count: 3 },
            { mode: 'mirror', preset: 'none', effects: ['jitter', 'v_jitter', 'chaos'], count: 3 },
            { mode: 'melt', preset: 'sepia', effects: ['jitter', 'scanlines', 'chaos'], count: 3 },
        ];
        const safeRanges = {
            rgb_shift: [4, 16],
            jitter: [4, 20],
            v_jitter: [0, 10],
            scanlines: [2, 14],
            chaos: [8, 24],
        };
        const profile = profiles[randomInteger(0, profiles.length - 1)];

        // Start from a clean state so Surprise me never stacks new effects on old ones.
        controls.forEach(control => {
            if (control.type === 'range') {
                control.value = control.defaultValue || (Number(control.min) < 0 ? '0' : control.min);
                updateValue(control);
            } else if (control.type === 'checkbox') {
                control.checked = false;
            } else if (control.tagName === 'SELECT') {
                control.selectedIndex = 0;
            }
        });
        const mode = document.getElementById('glitch_mode');
        const channelFilter = document.getElementById('channel_filter');
        if (mode) mode.value = profile.mode;
        presetFilter.value = profile.preset;
        if (channelFilter) channelFilter.value = 'none';
        profile.effects.slice(0, profile.count).forEach(id => {
            const control = document.getElementById(id);
            const range = safeRanges[id];
            if (control && range) {
                control.value = String(randomInteger(range[0], range[1]));
                updateValue(control);
            }
        });
        updateDuotonePalette();
        scheduleRender();
    });

    document.getElementById('achims-special-btn').addEventListener('click', () => {
        presetFilter.value = 'achims_special';
        updateDuotonePalette();
        scheduleRender();
    });

    glitchForm.addEventListener('submit', event => {
        event.preventDefault();
        if (active || pendingRender) return;
        document.dispatchEvent(new Event('clear-post-selection'));
        if (!photoInput.files.length && !libImageInput.value && !sourceFileInput.value) {
            showStatus('Choose a JPEG or PNG to begin.', true);
            return;
        }
        revision++;
        render(new FormData(glitchForm), 'source');
    });

    photoInput.addEventListener('change', () => {
        document.dispatchEvent(new Event('clear-post-selection'));
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
