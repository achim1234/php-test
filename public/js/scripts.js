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
    if (selectedImages.has(filename)) {
        selectedImages.delete(filename);
        element.classList.remove('selected');
    } else {
        selectedImages.add(filename);
        element.classList.add('selected');
    }
}

function morphSelected() {
    if (selectedImages.size < 2) {
        alert('Please select at least two images to morph.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'morph');
    selectedImages.forEach(img => {
        formData.append('images[]', img);
    });

    fetch('index.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const resultContainer = document.getElementById('result-container');
            if (!resultContainer) {
                location.reload(); // If we don't have a result container yet, just reload
                return;
            }
            
            const preview = document.getElementById('glitched-preview');
            const downloadLink = document.getElementById('download-link');
            
            preview.src = data.glitchedImage;
            downloadLink.href = data.glitchedImage;

            // Update source_file for live glitching
            const sourceFileField = document.getElementById('source_file');
            if (sourceFileField) {
                sourceFileField.value = data.glitchedImage.split('?')[0].split('/').pop();
            }
            
            // Show result container and hide placeholder if they exist
            const placeholder = document.getElementById('placeholder');
            if (resultContainer) resultContainer.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
            
            // Clear selection
            selectedImages.clear();
            document.querySelectorAll('.library-item.selected').forEach(el => el.classList.remove('selected'));
            
            // Scroll to result
            preview.scrollIntoView({ behavior: 'smooth' });
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => console.error('Error morphing:', error));
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
        currentLibFile = img.filename;
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
        glitchForm.submit();
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
    const form = document.getElementById('glitchForm');
    const controls = form.querySelectorAll('input[type="range"], input[type="checkbox"], input[type="color"], select');
    const preview = document.getElementById('glitched-preview');
    const downloadLink = document.getElementById('download-link');
    const sourceFileInput = document.getElementById('source_file');

    let timeout = null;

    const applyGlitchLive = () => {
        if (!sourceFileInput || !sourceFileInput.value) return;

        const formData = new FormData(form);
        // Don't re-upload the photo during live glitching
        formData.delete('photo');
        
        // Ensure checkbox is handled correctly if unchecked
        if (!document.getElementById('invert').checked) {
            formData.delete('invert');
        }

        fetch('index.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                preview.src = data.glitchedImage;
                downloadLink.href = data.glitchedImage;
            }
        })
        .catch(error => console.error('Error applying glitch:', error));
    };

    controls.forEach(control => {
        const eventType = control.type === 'checkbox' ? 'change' : 'input';
        control.addEventListener(eventType, () => {
            if (control.type === 'range') {
                // Update value display
                const valSpan = document.getElementById('val_' + control.id);
                if (valSpan) {
                    valSpan.textContent = control.value;
                }
            }

            // Debounce to avoid too many requests
            clearTimeout(timeout);
            timeout = setTimeout(applyGlitchLive, 150);
        });
    });

    const saveOutputBtn = document.getElementById('save-output-btn');
    if (saveOutputBtn) {
        saveOutputBtn.addEventListener('click', () => {
            const preview = document.getElementById('glitched-preview');
            const url = new URL(preview.src);
            const filename = url.pathname.split('/').pop();
            
            const formData = new FormData();
            formData.append('action', 'save_to_output');
            formData.append('filename', filename);

            fetch('index.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Saved to Output Library!');
                    location.reload(); // Reload to show the new image in the grid
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => console.error('Error saving to output:', error));
        });
    }
});
