<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Classes\PhotoGlitcher;

$glitchedImage = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        // Initial upload
        $file = $_FILES['photo'];
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('glitch_', true) . '.' . $extension;
        $sourcePath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $sourcePath)) {
            $sourceFile = $filename;
        } else {
            $error = "Failed to move uploaded file.";
        }
    } elseif (isset($_POST['source_file'])) {
        // Re-glitching existing file
        $sourceFile = $_POST['source_file'];
        // Basic security check: ensure it's just a filename in the uploads dir
        if (str_contains($sourceFile, '..') || str_contains($sourceFile, '/') || str_contains($sourceFile, '\\')) {
            $error = "Invalid source file.";
            $sourceFile = null;
        }
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

        $sourcePath = $uploadDir . $sourceFile;
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo Glitch App</title>
    <style>
        body { font-family: sans-serif; display: flex; flex-direction: column; align-items: center; background: #121212; color: #eee; }
        .container { max-width: 600px; margin-top: 50px; text-align: center; border: 2px solid #333; padding: 20px; border-radius: 10px; }
        img { max-width: 100%; height: auto; margin-top: 20px; border: 5px solid #444; }
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

        <form id="glitchForm" action="index.php" method="post" enctype="multipart/form-data">
            <?php if (isset($sourceFile)): ?>
                <input type="hidden" name="source_file" id="source_file" value="<?php echo htmlspecialchars($sourceFile); ?>">
            <?php endif; ?>
            <div class="control-group">
                <label for="photo">Select Photo:</label>
                <input type="file" name="photo" id="photo" accept="image/jpeg,image/png" <?php echo isset($sourceFile) ? '' : 'required'; ?>>
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

        <?php if ($glitchedImage): ?>
            <div id="result-container">
                <h2>Your Glitched Result:</h2>
                <img id="glitched-preview" src="<?php echo htmlspecialchars($glitchedImage); ?>" alt="Glitched Photo">
                <br>
                <div style="margin-top: 20px;">
                    <a id="download-link" href="<?php echo htmlspecialchars($glitchedImage); ?>" download class="download-button">Download Glitched Image</a>
                </div>
                <br>
                <a href="index.php" style="color: #00aaff; text-decoration: none; margin-top: 10px; display: inline-block;">Upload another one</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
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
        });
    </script>
</body>
</html>
