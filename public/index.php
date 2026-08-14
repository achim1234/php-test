<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Classes\GlitchController;

$controller = new GlitchController();
$data = $controller->handleRequest();

$glitchedImage = $data['glitchedImage'];
$error = $data['error'];
$sourceFile = $data['sourceFile'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo Glitch App</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
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
                    <input type="hidden" name="source_file" id="source_file" value="<?php echo htmlspecialchars($sourceFile ?? ''); ?>">
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
                        <label for="preset_filter">Artistic Filter:</label>
                        <select name="preset_filter" id="preset_filter">
                            <option value="none">None</option>
                            <option value="grayscale">Grayscale</option>
                            <option value="sepia">Sepia</option>
                            <option value="vintage">Vintage</option>
                            <option value="dramatic">Dramatic</option>
                        </select>
                    </div>

                    <div class="control-group">
                        <label for="colorize">Color Tint:</label>
                        <input type="color" name="colorize" id="colorize" value="#000000">
                    </div>

                    <div class="control-group">
                        <label for="color_intensity">Tint Intensity: <span id="val_color_intensity">0</span></label>
                        <input type="range" name="color_intensity" id="color_intensity" min="0" max="100" value="0">
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
                    <div class="action-buttons">
                        <a id="download-link" href="<?php echo htmlspecialchars($glitchedImage ?? ''); ?>" download class="download-button">Download Result</a>
                        <button id="save-output-btn" class="download-button">Save to Collection</button>
                    </div>
                    <br>
                    <a href="index.php" class="clear-link">Start Fresh</a>
                </div>
                <?php if (!$glitchedImage): ?>
                    <div id="placeholder">
                        <p>Select or upload an artifact to begin.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mode-toggle-container">
            <span>Interaction Mode</span>
            <label>
                <input type="radio" name="interaction-mode" value="view" checked> Curate
            </label>
            <label>
                <input type="radio" name="interaction-mode" value="select"> Composite
            </label>
        </div>

        <div class="library-section">
            <h3>Output Library:</h3>
            <div style="margin-bottom: 20px;">
                <button type="button" onclick="morphSelected()">Morph Selected</button>
            </div>
            <div class="library-grid" id="output-grid">
                <?php
                $outputImages = $controller->getOutputImages();
                if (empty($outputImages)): ?>
                    <p style="grid-column: 1/-1; color: #888;">Output folder is empty.</p>
                <?php else: 
                    foreach ($outputImages as $img):
                        $ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png'])):
                ?>
                    <div class="library-item" onclick="handleItemClick(this, 'output/<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($img); ?>')">
                        <div class="select-badge">✓</div>
                        <img src="output/<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($img); ?>">
                        <div class="view-overlay" onclick="event.stopPropagation(); openLightbox('output/<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($img); ?>')">Inspect</div>
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
                $images = $controller->getLibraryImages();
                if (empty($images)): ?>
                    <p style="grid-column: 1/-1; color: #888;">Lib folder is empty. Add images to <code>public/lib/</code>.</p>
                <?php else: 
                    foreach ($images as $img):
                        $ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png'])):
                ?>
                    <div class="library-item" onclick="handleItemClick(this, 'lib/<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($img); ?>')">
                        <div class="select-badge">✓</div>
                        <img src="lib/<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($img); ?>">
                        <div class="view-overlay" onclick="event.stopPropagation(); openLightbox('lib/<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($img); ?>')">Inspect</div>
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

    <script src="js/scripts.js"></script>
</body>
</html>
