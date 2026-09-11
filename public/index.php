<?php

declare(strict_types=1);

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
    <title>Foto Glitcher — Digital Image Lab</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="site-shell">
        <header class="masthead">
            <div class="masthead-meta">
                <span><i class="status-dot"></i> Digital image lab</span>
                <span>Edition 01 / <?php echo date('Y'); ?></span>
            </div>
            <div class="title-lockup">
                <h1>Foto <em>Glitcher</em></h1>
                <p>Distort the familiar.<br>Create a beautiful accident.</p>
            </div>
        </header>

        <p id="editor-status" role="status" aria-live="polite"<?php echo $error ? ' class="error"' : ''; ?>><?php echo htmlspecialchars($error ?? ''); ?></p>

        <main>
            <div class="main-layout">
                <section class="form-section" aria-labelledby="controls-title">
                    <div class="panel-heading">
                        <span class="panel-number">01</span>
                        <div>
                            <p class="kicker">Source &amp; treatment</p>
                            <h2 id="controls-title">Shape the signal</h2>
                        </div>
                    </div>
                <form id="glitchForm" action="index.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="source_file" id="source_file" value="<?php echo htmlspecialchars($sourceFile ?? ''); ?>">
                    <input type="hidden" name="library_image" id="library_image_input">

                    <div class="upload-control">
                        <label for="photo">
                            <span>Choose a source</span>
                            <small>JPEG or PNG</small>
                        </label>
                        <input type="file" name="photo" id="photo" accept="image/jpeg,image/png">
                    </div>

                    <div class="controls-grid">
                        <div class="control-group">
                            <label for="rgb_shift">RGB shift <span id="val_rgb_shift">10</span></label>
                            <input type="range" name="rgb_shift" id="rgb_shift" min="0" max="50" value="10">
                        </div>

                        <div class="control-group">
                            <label for="jitter">Horizontal jitter <span id="val_jitter">20</span></label>
                            <input type="range" name="jitter" id="jitter" min="0" max="100" value="20">
                        </div>

                        <div class="control-group">
                            <label for="v_jitter">Vertical jitter <span id="val_v_jitter">0</span></label>
                            <input type="range" name="v_jitter" id="v_jitter" min="0" max="100" value="0">
                        </div>

                        <div class="control-group">
                            <label for="scanlines">Signal noise <span id="val_scanlines">10</span></label>
                            <input type="range" name="scanlines" id="scanlines" min="0" max="50" value="10">
                        </div>

                        <div class="control-group">
                            <label for="pixelate">Pixelate <span id="val_pixelate">0</span></label>
                            <input type="range" name="pixelate" id="pixelate" min="0" max="20" value="0">
                        </div>

                        <div class="control-group">
                            <label for="color_intensity">Tint strength <span id="val_color_intensity">0</span></label>
                            <input type="range" name="color_intensity" id="color_intensity" min="0" max="100" value="0">
                        </div>

                        <div class="control-group">
                            <label for="brightness">Brightness <span id="val_brightness">0</span></label>
                            <input type="range" name="brightness" id="brightness" min="-100" max="100" value="0">
                        </div>

                        <div class="control-group">
                            <label for="contrast">Contrast <span id="val_contrast">0</span></label>
                            <input type="range" name="contrast" id="contrast" min="-100" max="100" value="0">
                        </div>

                        <div class="control-group chaos-control">
                            <label for="chaos">Chaos intensity <span id="val_chaos">50</span></label>
                            <input type="range" name="chaos" id="chaos" min="0" max="100" value="50">
                        </div>
                    </div>

                    <div class="control-row">
                        <div class="select-control">
                            <label for="glitch_mode">Distortion engine</label>
                            <select name="glitch_mode" id="glitch_mode">
                                <option value="signal">Signal Shift</option>
                                <option value="datamosh">Data Mosh</option>
                                <option value="melt">Pixel Melt</option>
                                <option value="mirror">Mirror Fold</option>
                                <option value="vhs">VHS Rupture</option>
                                <option value="shred">Slice Shred</option>
                            </select>
                        </div>

                        <div class="select-control">
                            <label for="preset_filter">Color process</label>
                            <select name="preset_filter" id="preset_filter">
                                <option value="none">Natural</option>
                                <option value="grayscale">Monochrome</option>
                                <option value="sepia">Sepia</option>
                                <option value="vintage">Vintage film</option>
                                <option value="dramatic">Dramatic</option>
                                <option value="neon">Neon Edge</option>
                                <option value="solarize">Solarize</option>
                                <option value="thermal">Thermal Map</option>
                                <option value="toxic">Toxic Chrome</option>
                                <option value="posterize">Posterize</option>
                            </select>
                        </div>

                        <div class="color-control">
                            <label for="colorize">Tint</label>
                            <input type="color" name="colorize" id="colorize" value="#7967ff">
                        </div>
                    </div>

                    <label class="toggle-control" for="invert">
                        <span>Invert spectrum</span>
                        <input type="checkbox" name="invert" id="invert" value="1">
                        <i aria-hidden="true"></i>
                    </label>

                    <div class="button-group">
                        <button type="submit"><span>Glitch image</span><b aria-hidden="true">↗</b></button>
                        <button type="button" id="random-glitch-btn" class="secondary-button">Surprise me</button>
                    </div>
                </form>
                </section>

                <section class="result-section" aria-labelledby="preview-title">
                    <div class="preview-heading">
                        <div>
                            <p class="kicker">Live canvas</p>
                            <h2 id="preview-title">The beautiful error</h2>
                        </div>
                        <span class="live-label">Preview</span>
                    </div>
                <div id="result-container" <?php echo !$glitchedImage ? 'style="display: none;"' : ''; ?>>
                    <div class="image-frame">
                        <img id="glitched-preview" src="<?php echo htmlspecialchars($glitchedImage ?? ''); ?>" alt="Glitched photo">
                    </div>
                    <div class="action-buttons">
                        <a id="download-link" href="<?php echo htmlspecialchars($glitchedImage ?? ''); ?>" download class="download-button">Download</a>
                        <button id="save-output-btn" class="download-button">Add to collection</button>
                        <a href="index.php" class="clear-link">Reset</a>
                    </div>
                </div>
                <?php if (!$glitchedImage): ?>
                    <div id="placeholder">
                        <span class="placeholder-mark" aria-hidden="true">+</span>
                        <p>Your next<br><em>accident</em><br>starts here.</p>
                        <small>Upload or select an image</small>
                    </div>
                <?php endif; ?>
                </section>
            </div>

            <section class="collection" aria-labelledby="collection-title">
                <div class="collection-heading">
                    <div>
                        <p class="kicker">Archive &amp; remix</p>
                        <h2 id="collection-title">The collection</h2>
                    </div>
                    <div class="mode-toggle-container">
                        <span>Mode</span>
                        <label>
                            <input type="radio" name="interaction-mode" value="view" checked>
                            <i>Curate</i>
                        </label>
                        <label>
                            <input type="radio" name="interaction-mode" value="select">
                            <i>Composite</i>
                        </label>
                    </div>
                </div>

                <div class="library-section">
                    <div class="library-heading">
                        <div><span>01</span><h3>Saved works</h3></div>
                        <button type="button" id="morph-btn" onclick="morphSelected()">Morph selected <b aria-hidden="true">↗</b></button>
                    </div>
                    <div class="library-grid" id="output-grid">
                        <?php
                        $outputImages = $controller->getOutputImages();
                        if (empty($outputImages)): ?>
                            <p class="empty-state">No saved works yet.</p>
                        <?php else:
                            foreach ($outputImages as $img):
                                $ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
                                if (in_array($ext, ['jpg', 'jpeg', 'png'])):
                        ?>
                            <div class="library-item" onclick="handleItemClick(this, 'output/<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($img); ?>')">
                                <div class="select-badge">✓</div>
                                <img src="output/<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($img); ?>" loading="lazy" decoding="async">
                                <div class="view-overlay" onclick="event.stopPropagation(); openLightbox('output/<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($img); ?>')">Inspect <span>↗</span></div>
                            </div>
                        <?php
                                endif;
                            endforeach;
                        endif;
                        ?>
                    </div>
                </div>

                <div class="library-section">
                    <div class="library-heading">
                        <div><span>02</span><h3>Source material</h3></div>
                        <p>Select an image to begin</p>
                    </div>
                    <div class="library-grid" id="library-grid">
                        <?php
                        $images = $controller->getLibraryImages();
                        if (empty($images)): ?>
                            <p class="empty-state">Add images to <code>public/lib/</code>.</p>
                        <?php else:
                            foreach ($images as $img):
                                $ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
                                if (in_array($ext, ['jpg', 'jpeg', 'png'])):
                        ?>
                            <div class="library-item" onclick="handleItemClick(this, 'lib/<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($img); ?>')">
                                <div class="select-badge">✓</div>
                                <img src="lib/<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($img); ?>" loading="lazy" decoding="async">
                                <div class="view-overlay" onclick="event.stopPropagation(); openLightbox('lib/<?php echo htmlspecialchars($img); ?>', '<?php echo htmlspecialchars($img); ?>')">Inspect <span>↗</span></div>
                            </div>
                        <?php
                                endif;
                            endforeach;
                        endif;
                        ?>
                    </div>
                </div>
            </section>
        </main>

        <footer>
            <span>Foto Glitcher</span>
            <span>Make something strange.</span>
        </footer>
    </div>

    <div id="lightbox" class="lightbox" role="dialog" aria-modal="true" aria-label="Image preview">
        <button type="button" class="lightbox-close" onclick="closeLightbox()" aria-label="Close preview">×</button>
        <div class="lightbox-nav">
            <button type="button" class="lightbox-arrow" onclick="prevImage()" aria-label="Previous image">←</button>
            <button type="button" class="lightbox-arrow" onclick="nextImage()" aria-label="Next image">→</button>
        </div>
        <img class="lightbox-content" id="lightbox-img" alt="">
        <div id="lightbox-caption" class="lightbox-caption"></div>
        <button type="button" id="select-lib-btn" class="lightbox-select">Use this image <span aria-hidden="true">↗</span></button>
    </div>

    <script src="js/scripts.js"></script>
</body>
</html>
