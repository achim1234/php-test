<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Classes\PhotoGlitcher;

$glitchedImage = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = __DIR__ . '/uploads/';
    $libDir = __DIR__ . '/lib/';
    $outputDir = __DIR__ . '/output/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    if (!is_dir($libDir)) {
        mkdir($libDir, 0777, true);
    }
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    if (isset($_POST['action']) && $_POST['action'] === 'save_to_output') {
        $filename = $_POST['filename'] ?? '';
        if ($filename && !str_contains($filename, '..') && !str_contains($filename, '/') && !str_contains($filename, '\\')) {
            $sourcePath = $uploadDir . $filename;
            if (file_exists($sourcePath)) {
                $pathInfo = pathinfo($filename);
                $timestamp = date('Ymd_His');
                $newFilename = $pathInfo['filename'] . '_' . $timestamp . '.' . $pathInfo['extension'];
                $destPath = $outputDir . $newFilename;
                if (copy($sourcePath, $destPath)) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit;
                }
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to save to output library.']);
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'morph') {
        $images = $_POST['images'] ?? [];
        if (count($images) >= 2) {
            $sourcePaths = [];
            foreach ($images as $img) {
                if (str_contains($img, '..') || str_contains($img, '/') || str_contains($img, '\\')) continue;
                
                $path = $libDir . $img;
                if (!file_exists($path)) {
                    $path = $outputDir . $img;
                }
                
                if (file_exists($path)) {
                    $sourcePaths[] = $path;
                }
            }
            
            if (count($sourcePaths) >= 2) {
                $extension = pathinfo($sourcePaths[0], PATHINFO_EXTENSION);
                $destFilename = 'morph_' . uniqid() . '.' . $extension;
                $destPath = $uploadDir . $destFilename;
                
                $glitcher = new PhotoGlitcher();
                if ($glitcher->morphImages($sourcePaths, $destPath)) {
                    $glitchedImage = 'uploads/' . $destFilename . '?t=' . time();
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'glitchedImage' => $glitchedImage]);
                        exit;
                    }
                } else {
                    $error = "Failed to morph images.";
                }
            } else {
                $error = "At least two valid images are required for morphing.";
            }
        } else {
            $error = "Please select at least two images.";
        }
        
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
    }

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        // Initial upload - save to library
        $file = $_FILES['photo'];
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('upload_', true) . '.' . $extension;
        $sourcePath = $libDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $sourcePath)) {
            $sourceFile = $filename;
            $isFromLib = true;
        } else {
            $error = "Failed to move uploaded file.";
        }
    } elseif (!empty($_POST['library_image'])) {
        // Selection from library
        $libImage = $_POST['library_image'];
        $libPath = __DIR__ . '/lib/' . $libImage;
        
        if (str_contains($libImage, '..') || str_contains($libImage, '/') || str_contains($libImage, '\\')) {
            $error = "Invalid library image.";
        } elseif (file_exists($libPath)) {
            $extension = pathinfo($libPath, PATHINFO_EXTENSION);
            $filename = uniqid('glitch_lib_', true) . '.' . $extension;
            $sourcePath = $uploadDir . $filename;
            if (copy($libPath, $sourcePath)) {
                $sourceFile = $filename;
            } else {
                $error = "Failed to copy library image.";
            }
        } else {
            $error = "Library image not found.";
        }
    } elseif (!empty($_POST['source_file'])) {
        // Re-glitching existing file
        $sourceFile = $_POST['source_file'];
        // Basic security check: ensure it's just a filename
        if (str_contains($sourceFile, '..') || str_contains($sourceFile, '/') || str_contains($sourceFile, '\\')) {
            $error = "Invalid source file.";
            $sourceFile = null;
        }
        
        // Check if file is in lib or uploads
        if ($sourceFile && !file_exists($libDir . $sourceFile) && !file_exists($uploadDir . $sourceFile)) {
            $error = "Source file not found.";
            $sourceFile = null;
        }
        
        $isFromLib = $sourceFile && file_exists($libDir . $sourceFile);
    }

    if (isset($sourceFile) && !$error) {
        $rgbShift = isset($_POST['rgb_shift']) ? (int)$_POST['rgb_shift'] : 10;
        $jitter = isset($_POST['jitter']) ? (int)$_POST['jitter'] : 20;
        $scanlines = isset($_POST['scanlines']) ? (int)$_POST['scanlines'] : 10;
        $brightness = isset($_POST['brightness']) ? (int)$_POST['brightness'] : 0;
        $contrast = isset($_POST['contrast']) ? (int)$_POST['contrast'] : 0;
        $invert = isset($_POST['invert']) && $_POST['invert'] === '1';
        $pixelate = isset($_POST['pixelate']) ? (int)$_POST['pixelate'] : 0;
        $vJitter = isset($_POST['v_jitter']) ? (int)$_POST['v_jitter'] : 0;

        $sourcePath = ($isFromLib ?? false) ? $libDir . $sourceFile : $uploadDir . $sourceFile;
        $destFilename = 'glitched_' . $sourceFile;
        $destPath = $uploadDir . $destFilename;

        $glitcher = new PhotoGlitcher();
        if ($glitcher->applyGlitch($sourcePath, $destPath, $rgbShift, $jitter, $scanlines, $brightness, $contrast, $invert, $pixelate, $vJitter)) {
            $glitchedImage = 'uploads/' . $destFilename . '?t=' . time();
            
            // If it's an AJAX request, return JSON
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'glitchedImage' => $glitchedImage]);
                exit;
            }
        } else {
            $error = "Failed to apply glitch effect.";
        }
    }

    // If it's an AJAX request and we reached here, it means something failed or was invalid
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error ?: "Invalid request."]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo Glitch App</title>
    <style>
        body { font-family: sans-serif; display: flex; flex-direction: column; align-items: center; background: #121212; color: #eee; margin: 0; padding: 20px; }
        .container { width: 100%; max-width: 1200px; text-align: center; }
        .main-layout { display: flex; gap: 30px; align-items: flex-start; margin-top: 30px; flex-wrap: wrap; }
        .form-section { flex: 1; min-width: 350px; text-align: left; background: #1e1e1e; padding: 20px; border-radius: 10px; border: 1px solid #333; }
        .result-section { flex: 1.5; min-width: 350px; background: #1e1e1e; padding: 20px; border-radius: 10px; border: 1px solid #333; display: flex; flex-direction: column; align-items: center; min-height: 400px; justify-content: center; }
        img { max-width: 100%; height: auto; border: 5px solid #444; }
        .library-section { margin-top: 40px; border-top: 1px solid #333; padding-top: 30px; width: 100%; }
        .library-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 15px; }
        .library-item { cursor: pointer; border: 2px solid transparent; transition: border 0.2s; position: relative; }
        .library-item:hover { border-color: #ff0055; }
        .library-item.selected { border-color: #00cc88; }
        .library-item .select-badge { position: absolute; top: 5px; right: 5px; background: #00cc88; color: white; width: 20px; height: 20px; border-radius: 50%; display: none; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; }
        .library-item.selected .select-badge { display: flex; }
        .library-item img { margin-top: 0; border: none; width: 100%; height: 80px; object-fit: cover; }
        
        /* Lightbox styles */
        .lightbox { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9); align-items: center; justify-content: center; flex-direction: column; }
        .lightbox-content { max-width: 80%; max-height: 70%; border: 3px solid #eee; }
        .lightbox-caption { color: #ccc; margin: 15px 0; font-size: 1.2rem; }
        .lightbox-close { position: absolute; top: 20px; right: 30px; color: #fff; font-size: 40px; font-weight: bold; cursor: pointer; }
        .lightbox-nav { position: absolute; top: 50%; width: 100%; display: flex; justify-content: space-between; padding: 0 20px; box-sizing: border-box; pointer-events: none; }
        .lightbox-arrow { color: #fff; font-size: 60px; font-weight: bold; cursor: pointer; pointer-events: auto; user-select: none; padding: 0 20px; }
        .lightbox-arrow:hover { color: #ff0055; }
        .lightbox-select { background: #00cc88; color: white; border: none; padding: 10px 25px; cursor: pointer; font-weight: bold; font-size: 1.1rem; z-index: 1001; }
        
        form { margin-top: 20px; text-align: left; }
        .control-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="file"] { margin-bottom: 10px; width: 100%; }
        input[type="range"] { width: 100%; }
        .button-group { text-align: center; margin-top: 20px; }
        button { background: #ff0055; color: white; border: none; padding: 10px 20px; cursor: pointer; font-weight: bold; }
        button:hover { background: #ff0077; }
        .download-button { background: #0088cc; color: white; border: none; padding: 10px 20px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; }
        .download-button:hover { background: #00aaff; }
        .error { color: #ff5555; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Photo Glitcher</h1>
        
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <div class="main-layout">
            <div class="form-section">
                <form id="glitchForm" action="index.php" method="post" enctype="multipart/form-data">
                    <?php if (isset($sourceFile)): ?>
                        <input type="hidden" name="source_file" id="source_file" value="<?php echo htmlspecialchars($sourceFile); ?>">
                    <?php endif; ?>
                    <input type="hidden" name="library_image" id="library_image_input">
                    
                    <div class="control-group">
                        <label for="photo">Upload Photo:</label>
                        <input type="file" name="photo" id="photo" accept="image/jpeg,image/png">
                    </div>

                    <div class="control-group">
                        <label for="rgb_shift">RGB Shift Intensity: <span id="val_rgb_shift">10</span></label>
                        <input type="range" name="rgb_shift" id="rgb_shift" min="0" max="50" value="10">
                    </div>

                    <div class="control-group">
                        <label for="jitter">Horizontal Jitter: <span id="val_jitter">20</span></label>
                        <input type="range" name="jitter" id="jitter" min="0" max="100" value="20">
                    </div>

                    <div class="control-group">
                        <label for="v_jitter">Vertical Jitter: <span id="val_v_jitter">0</span></label>
                        <input type="range" name="v_jitter" id="v_jitter" min="0" max="100" value="0">
                    </div>

                    <div class="control-group">
                        <label for="scanlines">Scanlines / Static: <span id="val_scanlines">10</span></label>
                        <input type="range" name="scanlines" id="scanlines" min="0" max="50" value="10">
                    </div>

                    <div class="control-group">
                        <label for="pixelate">Pixelate: <span id="val_pixelate">0</span></label>
                        <input type="range" name="pixelate" id="pixelate" min="0" max="20" value="0">
                    </div>

                    <div class="control-group">
                        <label for="brightness">Brightness: <span id="val_brightness">0</span></label>
                        <input type="range" name="brightness" id="brightness" min="-100" max="100" value="0">
                    </div>

                    <div class="control-group">
                        <label for="contrast">Contrast: <span id="val_contrast">0</span></label>
                        <input type="range" name="contrast" id="contrast" min="-100" max="100" value="0">
                    </div>

                    <div class="control-group">
                        <label for="invert" style="display: inline;">Invert Colors:</label>
                        <input type="checkbox" name="invert" id="invert" value="1">
                    </div>
                    
                    <div class="button-group">
                        <button type="submit">Upload and Glitch!</button>
                    </div>
                </form>
            </div>

            <div class="result-section">
                <div id="result-container" <?php echo !$glitchedImage ? 'style="display: none;"' : ''; ?>>
                    <h2>Your Glitched Result:</h2>
                    <img id="glitched-preview" src="<?php echo htmlspecialchars($glitchedImage ?? ''); ?>" alt="Glitched Photo">
                    <div style="margin-top: 20px;">
                        <a id="download-link" href="<?php echo htmlspecialchars($glitchedImage ?? ''); ?>" download class="download-button">Download Glitched Image</a>
                        <button id="save-output-btn" class="download-button" style="background: #00cc88; margin-left: 10px;">Save in Output Lib</button>
                    </div>
                    <br>
                    <a href="index.php" style="color: #00aaff; text-decoration: none; margin-top: 10px; display: inline-block;">Clear / New Upload</a>
                </div>
                <?php if (!$glitchedImage): ?>
                    <div id="placeholder" style="color: #666; text-align: center;">
                        <p>No image glitched yet.</p>
                        <p>Upload a photo or select one from the library below.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="library-section">
            <h3>Output Library:</h3>
            <div style="margin-bottom: 10px;">
                <button type="button" onclick="morphSelected()" style="background: #aa00ff;">Morph Selected Images</button>
            </div>
            <div class="library-grid" id="output-grid">
                <?php
                $outputDir = __DIR__ . '/output/';
                $outputImages = is_dir($outputDir) ? array_diff(scandir($outputDir), array('.', '..', '.gitkeep')) : [];
                if (empty($outputImages)): ?>
                    <p style="grid-column: 1/-1; color: #888;">Output folder is empty.</p>
                <?php else: 
                    foreach ($outputImages as $img):
                        $ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png'])):
                ?>
                    <div class="library-item" onclick="toggleSelect(this, 'output/<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($img); ?>')">
                        <div class="select-badge">✓</div>
                        <img src="output/<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($img); ?>">
                        <div style="font-size: 10px; position: absolute; bottom: 0; left: 0; background: rgba(0,0,0,0.5); width: 100%;" onclick="event.stopPropagation(); openLightbox('output/<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($img); ?>')">🔍 View</div>
                    </div>
                <?php 
                        endif;
                    endforeach; 
                endif; 
                ?>
            </div>
        </div>

        <div class="library-section">
            <h3>Or select from Library:</h3>
            <div class="library-grid">
                <?php
                $libDir = __DIR__ . '/lib/';
                $images = array_diff(scandir($libDir), array('.', '..', '.gitkeep'));
                if (empty($images)): ?>
                    <p style="grid-column: 1/-1; color: #888;">Lib folder is empty. Add images to <code>public/lib/</code>.</p>
                <?php else: 
                    foreach ($images as $img):
                        $ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png'])):
                ?>
                    <div class="library-item" onclick="toggleSelect(this, 'lib/<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($img); ?>')">
                        <div class="select-badge">✓</div>
                        <img src="lib/<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($img); ?>">
                        <div style="font-size: 10px; position: absolute; bottom: 0; left: 0; background: rgba(0,0,0,0.5); width: 100%;" onclick="event.stopPropagation(); openLightbox('lib/<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($img); ?>')">🔍 View</div>
                    </div>
                <?php 
                        endif;
                    endforeach; 
                endif; 
                ?>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <div id="lightbox" class="lightbox">
        <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
        <div class="lightbox-nav">
            <span class="lightbox-arrow" onclick="prevImage()">&#10094;</span>
            <span class="lightbox-arrow" onclick="nextImage()">&#10095;</span>
        </div>
        <img class="lightbox-content" id="lightbox-img">
        <div id="lightbox-caption" class="lightbox-caption"></div>
        <button id="select-lib-btn" class="lightbox-select">Use this image</button>
    </div>

    <script>
        const lightbox = document.getElementById('lightbox');
        const lightboxImg = document.getElementById('lightbox-img');
        const lightboxCaption = document.getElementById('lightbox-caption');
        const libImageInput = document.getElementById('library_image_input');
        const glitchForm = document.getElementById('glitchForm');
        let currentLibFile = '';
        let libraryImages = [];
        let currentIndex = -1;
        let selectedImages = new Set();

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
            currentIndex = libraryImages.findIndex(img => img.filename === filename);
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

        document.getElementById('select-lib-btn').addEventListener('click', () => {
            libImageInput.value = currentLibFile;
            // Clear file input to prioritize library selection
            document.getElementById('photo').value = '';
            glitchForm.submit();
        });

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
            const controls = form.querySelectorAll('input[type="range"], input[type="checkbox"]');
            const preview = document.getElementById('glitched-preview');
            const downloadLink = document.getElementById('download-link');
            const sourceFileInput = document.getElementById('source_file');

            let timeout = null;

            const applyGlitchLive = () => {
                if (!sourceFileInput) return;

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
    </script>
</body>
</html>
