<?php
/**
 * Universal Document Reader - Premium Desktop Edition
 * Archive System - Quezon City Public Library
 */

require_once __DIR__ . '/../backend/core/config.php';
require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';
require_once __DIR__ . '/../backend/core/calibre.php';

// Get file ID
$fileId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$fileId) {
    header('Location: ../dashboard.php');
    exit;
}

// Fetch file data
$stmt = $pdo->prepare("
    SELECT n.*, c.name AS category_name
    FROM newspapers n
    LEFT JOIN categories c ON n.category_id = c.id
    WHERE n.id = ?
      AND n.deleted_at IS NULL
");
$stmt->execute([$fileId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    header('Location: ../dashboard.php');
    exit;
}

// File info
$fileType = strtolower($file['file_type']);
$fileUrl = 'serve_file.php?file=' . urlencode($file['file_path']);
$filePath = __DIR__ . '/../' . $file['file_path'];

// Handle MOBI → EPUB
$epubUrl = null;
$conversionError = null;

if ($fileType === 'mobi') {
    $result = ensureEpubExists($filePath);
    if ($result['success']) {
        $epubRelativePath = preg_replace('/\.mobi$/i', '.epub', $file['file_path']);
        $epubUrl = 'serve_file.php?file=' . urlencode($epubRelativePath);
    } else {
        $conversionError = $result['error'];
    }
}

// Handle CBZ - Get list of images
$cbzImages = [];
if ($fileType === 'cbz') {
    $zip = new ZipArchive;
    if ($zip->open($filePath) === TRUE) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $cbzImages[] = $name;
            }
        }
        $zip->close();
        natsort($cbzImages);
        $cbzImages = array_values($cbzImages);
    }
}

// Handle Gallery - JSON array
if ($fileType === 'gallery' && !empty($file['image_paths'])) {
    $cbzImages = json_decode($file['image_paths'], true) ?: [];
}

// Reader type determination
$readerType = 'unknown';
$pdfViewerUrl = '';

if ($fileType === 'pdf') {
    $readerType = 'pdf';
    $pdfViewerUrl = 'pdf_viewer.php?file=' . urlencode($file['file_path']);

} elseif ($fileType === 'epub' || ($fileType === 'mobi' && $epubUrl)) {
    $readerType = 'epub';
} elseif ($fileType === 'cbz' || $fileType === 'gallery') {
    $readerType = 'cbz';
} elseif (in_array($fileType, ['jpg', 'jpeg', 'png', 'tiff', 'tif'])) {
    $readerType = 'image';
}

// Log activity
logActivity($currentUser['id'], 'read', $file['title']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($file['title']) ?> - <?= APP_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Preload Critical Scripts -->
    <?php if ($readerType === 'epub'): ?>
        <link rel="preload" href="../assets/js/jszip.min.js" as="script">
        <link rel="preload" href="../assets/js/epub.min.js" as="script">
    <?php endif; ?>

    <!-- Styles -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Merriweather:wght@300;400;700&family=Lora:wght@400;500;600&family=Open+Dyslexic&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link
        href="../assets/css/pages/reader.css?v=<?= file_exists('../assets/css/pages/reader.css') ? filemtime('../assets/css/pages/reader.css') : time() ?>"
        rel="stylesheet">

    <?php if ($readerType === 'epub'): ?>
        <script src="../assets/js/jszip.min.js"></script>
        <script src="../assets/js/epub.min.js"></script>
    <?php endif; ?>
</head>

<body class="theme-dark <?= $readerType === 'pdf' ? 'reader-mode-pdf' : '' ?>">
    <!-- UI Chrome (Auto-hiding) -->
    <div class="reader-chrome-top" id="chrome-top">
        <div class="chrome-left">
            <a href="../dashboard.php" class="chrome-btn" title="Back to Library">
                <i class="bi bi-arrow-left"></i>
            </a>
        </div>
        <div class="chrome-center">
            <h1 class="book-title"><?= htmlspecialchars($file['title']) ?></h1>
        </div>
        <div class="chrome-right">
            <button class="chrome-btn" id="btn-theme" title="Theme Settings">
                <i class="bi bi-palette"></i>
            </button>
            <button class="chrome-btn" id="btn-text" title="Text Settings">
                <i class="bi bi-type"></i>
            </button>
        </div>
    </div>

    <!-- Reading Area -->
    <div class="reader-container" id="reader-container">

        <?php if ($readerType === 'pdf'): ?>
            <iframe src="<?= $pdfViewerUrl ?>" class="pdf-viewer"></iframe>

        <?php elseif ($readerType === 'epub'): ?>
            <div id="epub-viewer" class="epub-viewer"></div>

            <!-- Click Zones -->
            <div class="click-zone zone-left" id="zone-prev" title="Previous Page"></div>
            <div class="click-zone zone-right" id="zone-next" title="Next Page"></div>
            <div class="click-zone zone-center" id="zone-menu" title="Toggle Menu"></div>

            <!-- Loading -->
            <div class="epub-loading" id="epub-loading">
                <div class="spinner"></div>
                <div class="loading-text">Opening File...</div>
            </div>

        <?php elseif ($readerType === 'image'): ?>
            <div class="image-viewer">
                <img src="<?= $fileUrl ?>" alt="">
            </div>

        <?php elseif ($readerType === 'cbz'): ?>
            <!-- CBZ / Gallery Viewer -->
            <div id="cbz-viewer"
                style="display:flex; justify-content:center; align-items:center; height:100%; position:relative;">
                <img id="cbz-image" src=""
                    style="max-height:90vh; max-width:100%; object-fit:contain; border-radius:8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">

                <!-- Left/Right Nav Arrows -->
                <button class="gallery-arrow gallery-left" id="galleryPrev"
                    style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.6); color: white; border: none; border-radius: 50%; width: 50px; height: 50px; font-size: 28px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; transition: background 0.2s;">&#8249;</button>
                <button class="gallery-arrow gallery-right" id="galleryNext"
                    style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.6); color: white; border: none; border-radius: 50%; width: 50px; height: 50px; font-size: 28px; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; transition: background 0.2s;">&#8250;</button>
            </div>

            <script>
                const cbzImages = <?= json_encode($cbzImages) ?>;
                const fileType = '<?= $fileType ?>';
                const appUrl = '<?= APP_URL ?>';
                let currentGalleryIndex = 0;

                function renderGalleryImage() {
                    if (cbzImages.length > 0) {
                        const imgPath = cbzImages[currentGalleryIndex];

                        // For gallery it's a relative path from app root, for CBZ it's a temp extracted path 
                        // Note: actual CBZ extract logic would serve via serve_file.php or similar, 
                        // but gallery specifically uses direct uploads path natively
                        if (fileType === 'gallery') {
                            document.getElementById('cbz-image').src = appUrl + '/' + imgPath;
                        } else {
                            // Basic CBZ fallback placeholder logic 
                            // (If CBZ extraction is not fully implemented on server yet)
                            document.getElementById('cbz-image').src = appUrl + '/' + imgPath;
                        }

                        // Update Progress Counter in Footer
                        const progressPercent = document.getElementById('progress-percent');
                        const locationRef = document.getElementById('location-ref');
                        const progressFill = document.getElementById('progress-fill');

                        if (progressPercent && locationRef && progressFill) {
                            const pct = Math.round(((currentGalleryIndex + 1) / cbzImages.length) * 100);
                            progressPercent.textContent = pct + '%';
                            locationRef.textContent = `Image ${currentGalleryIndex + 1} of ${cbzImages.length}`;
                            progressFill.style.width = pct + '%';
                        }
                    }
                }

                document.getElementById('galleryPrev').onclick = function () {
                    if (cbzImages.length > 0) {
                        currentGalleryIndex = (currentGalleryIndex - 1 + cbzImages.length) % cbzImages.length;
                        renderGalleryImage();
                    }
                };

                document.getElementById('galleryNext').onclick = function () {
                    if (cbzImages.length > 0) {
                        currentGalleryIndex = (currentGalleryIndex + 1) % cbzImages.length;
                        renderGalleryImage();
                    }
                };

                document.addEventListener('keydown', function (e) {
                    if (cbzImages.length > 0) {
                        if (e.key === 'ArrowLeft') {
                            currentGalleryIndex = (currentGalleryIndex - 1 + cbzImages.length) % cbzImages.length;
                            renderGalleryImage();
                        }
                        if (e.key === 'ArrowRight') {
                            currentGalleryIndex = (currentGalleryIndex + 1) % cbzImages.length;
                            renderGalleryImage();
                        }
                    }
                });

                // Hide loading overlay explicitly since book.epub logic won't run
                setTimeout(() => {
                    const loadingMenu = document.getElementById('epub-loading');
                    if (loadingMenu) loadingMenu.style.display = 'none';
                }, 500);

                // Init rendering
                renderGalleryImage();
            </script>
        <?php else: ?>
            <div class="reader-fallback">
                <div class="fallback-card">
                    <i class="bi bi-file-earmark-break mb-3" style="font-size: 3rem;"></i>
                    <h3>Unsupported Format</h3>
                    <p>This file format cannot be viewed in the browser.</p>
                    <a href="<?= $fileUrl ?>" download class="btn-download">Download File</a>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- UI Chrome Bottom -->
    <div class="reader-chrome-bottom" id="chrome-bottom">
        <div class="progress-container">
            <div class="chapter-info" id="chapter-info">Loading...</div>
            <div class="progress-bar-wrapper" id="progress-bar-wrapper">
                <div class="progress-bar-fill" id="progress-fill"></div>
            </div>
            <div class="progress-stats">
                <span id="progress-percent">0%</span>
                <span class="separator">•</span>
                <span id="location-ref">Page 1</span>
            </div>
        </div>
    </div>

    <!-- Text Settings Overlay -->
    <div class="settings-overlay" id="settings-overlay-text">
        <div class="settings-header">
            <h3>Typography</h3>
            <button class="close-settings" data-target="settings-overlay-text"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="setting-group">
            <label>Font Family</label>
            <select id="font-family-select">
                <option value="'Merriweather', serif">Merriweather (Serif)</option>
                <option value="'Lora', serif">Lora (Serif)</option>
                <option value="'Inter', sans-serif">Inter (Sans)</option>
                <option value="'OpenDyslexic', sans-serif">Dyslexic</option>
            </select>
        </div>

        <div class="setting-group">
            <label>Font Size</label>
            <div class="range-control">
                <span style="font-size: 14px">A</span>
                <input type="range" id="font-size-range" min="14" max="32" step="1" value="18">
                <span style="font-size: 20px">A</span>
            </div>
        </div>

        <div class="setting-group">
            <label>Line Spacing</label>
            <div class="range-control">
                <i class="bi bi-text-paragraph" style="font-size: 0.8em"></i>
                <input type="range" id="line-height-range" min="1.2" max="2.4" step="0.1" value="1.6">
                <i class="bi bi-text-paragraph" style="font-size: 1.2em"></i>
            </div>
        </div>
    </div>

    <!-- Theme Settings Overlay -->
    <div class="settings-overlay" id="settings-overlay-theme">
        <div class="settings-header">
            <h3>Color Theme</h3>
            <button class="close-settings" data-target="settings-overlay-theme"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="setting-group">
            <div class="theme-selector">
                <button class="theme-btn theme-light" data-theme="theme-light">Aa</button>
                <button class="theme-btn theme-sepia" data-theme="theme-sepia">Aa</button>
                <button class="theme-btn theme-dark active" data-theme="theme-dark">Aa</button>
                <button class="theme-btn theme-contrast" data-theme="theme-contrast">Aa</button>
            </div>
        </div>
    </div>

    <!-- JavaScript Logic -->
    <?php if ($readerType === 'epub'): ?>
        <script>
            // Configuration
            const BOOK_URL = "<?= $fileType === 'mobi' ? $epubUrl : $fileUrl ?>";
            const STORAGE_KEY = 'reader_settings_v1';

            // Defaults
            let settings = {
                theme: 'theme-dark',
                fontFamily: "'Merriweather', serif",
                fontSize: 18,
                lineHeight: 1.6,
                margin: 60
            };

            // Load Settings
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) settings = { ...settings, ...JSON.parse(saved) };

            // DOM Elements
            const els = {
                container: document.getElementById('reader-container'),
                viewer: document.getElementById('epub-viewer'),
                body: document.body,
                chromeTop: document.getElementById('chrome-top'),
                chromeBottom: document.getElementById('chrome-bottom'),
                overlayText: document.getElementById('settings-overlay-text'),
                overlayTheme: document.getElementById('settings-overlay-theme'),
                btnTheme: document.getElementById('btn-theme'),
                btnText: document.getElementById('btn-text'),
                closeButtons: document.querySelectorAll('.close-settings'),
                zoneMenu: document.getElementById('zone-menu'),
                zonePrev: document.getElementById('zone-prev'),
                zoneNext: document.getElementById('zone-next'),
                loading: document.getElementById('epub-loading'),
                chapterInfo: document.getElementById('chapter-info'),
                progressFill: document.getElementById('progress-fill'),
                progressPercent: document.getElementById('progress-percent'),
                btnThemes: document.querySelectorAll('.theme-btn'),
                inputFont: document.getElementById('font-family-select'),
                inputSize: document.getElementById('font-size-range'),
                inputLine: document.getElementById('line-height-range')
            };

            // Remove inputMargin from els since it's deleted

            // Apply Theme immediately
            els.body.className = settings.theme;
            els.btnThemes.forEach(b => b.classList.toggle('active', b.dataset.theme === settings.theme));
            els.inputFont.value = settings.fontFamily;
            els.inputSize.value = settings.fontSize;
            els.inputLine.value = settings.lineHeight;

            // Initialize Book
            const book = ePub(BOOK_URL);
            const rendition = book.renderTo("epub-viewer", {
                width: "100%",
                height: "100%",
                flow: "paginated",
                manager: "default",
                spread: "none" // Force single column for distraction-free reading
            });

            // ─── Theme & Layout Algorithm ────────────────────────────────────────

            function updateLayout() {
                // Apply text styles
                rendition.themes.default({
                    'p': {
                        'font-family': `${settings.fontFamily} !important`,
                        'font-size': `${settings.fontSize}px !important`,
                        'line-height': `${settings.lineHeight} !important`,
                        'text-align': 'justify',
                        'margin-bottom': '1em !important'
                    },
                    'h1': { 'font-family': 'inherit !important', 'line-height': '1.3 !important', 'margin-bottom': '0.5em !important' },
                    'h2': { 'font-family': 'inherit !important', 'line-height': '1.3 !important' },
                    'h3': { 'font-family': 'inherit !important' },
                    'img': { 'max-width': '100% !important', 'height': 'auto !important', 'margin': '0 auto !important', 'display': 'block !important' },
                    'body': {
                        'padding': `40px 60px !important`,
                        'max-width': '900px !important',
                        'margin': '0 auto !important'
                    }
                });

                // Adjust container color based on theme
                const themeColors = {
                    'theme-light': '#fdfaf6',
                    'theme-sepia': '#f4ecd8',
                    'theme-dark': '#1a1a1a',
                    'theme-contrast': '#000000'
                };
                const textColor = {
                    'theme-light': '#2c2c2c',
                    'theme-sepia': '#5b4636',
                    'theme-dark': '#cfcfcf',
                    'theme-contrast': '#ffffff'
                };

                rendition.themes.register(settings.theme, {
                    'body': { 'color': `${textColor[settings.theme]} !important`, 'background': `${themeColors[settings.theme]} !important` },
                    'p': { 'color': `${textColor[settings.theme]} !important` },
                    'div': { 'color': `${textColor[settings.theme]} !important` },
                    'span': { 'color': `${textColor[settings.theme]} !important` }
                });
                rendition.themes.select(settings.theme);
            }

            // ─── UI Chrome / Auto-Hide ──────────────────────────────────────────

            let chromeTimeout;
            const hideChrome = () => {
                if (!els.overlayText.classList.contains('visible') &&
                    !els.overlayTheme.classList.contains('visible') &&
                    !els.chromeTop.matches(':hover') &&
                    !els.chromeBottom.matches(':hover')) {
                    els.body.classList.remove('chrome-visible');
                }
            };

            const showChrome = () => {
                els.body.classList.add('chrome-visible');
                clearTimeout(chromeTimeout);
                chromeTimeout = setTimeout(hideChrome, 2500);
            };

            // Show on any mouse movement
            document.addEventListener('mousemove', showChrome);

            // Show on click/touch
            document.addEventListener('click', showChrome);

            // Keep visible while settings are open
            const keepChrome = () => {
                clearTimeout(chromeTimeout);
                els.body.classList.add('chrome-visible');
            };
            els.btnText.addEventListener('click', keepChrome);
            els.btnTheme.addEventListener('click', keepChrome);
            // This closing brace was for the event listener, not the promise chain.
            // The original code had an extra `});` here, which is now removed.

            // Initial show
            showChrome();

            // ─── Events ──────────────────────────────────────────────────────────

            // Location Storage Key
            const LOCATION_KEY = 'reader_location_' + encodeURIComponent(BOOK_URL);

            book.ready.then(() => {
                // Load saved location for THIS book
                const savedLoc = localStorage.getItem(LOCATION_KEY);
                return rendition.display(savedLoc || undefined);
            }).then(() => {
                // Critical Path Complete: Show Book
                els.loading.classList.add('hidden');

                // Defer non-critical setup
                updateLayout(); // Applied once book is visible

                // Check Cache for Locations
                const cacheKey = 'epub_locations_' + encodeURIComponent(BOOK_URL);
                const cached = localStorage.getItem(cacheKey);

                if (cached) {
                    book.locations.load(cached);
                    console.log("Locations loaded from cache");
                } else {
                    // Generate in background if not cached
                    book.locations.generate(1000).then((locations) => {
                        localStorage.setItem(cacheKey, book.locations.save());
                        console.log("Locations generated and cached");
                    });
                }
            }).catch(err => {
                console.error("Error loading book:", err);
                els.loading.classList.add('hidden');
                // If section not found (corrupted cache), try clearing and reloading start
                if (err.message && err.message.includes('No Section Found')) {
                    console.warn("Invalid location detected. Resetting.");
                    localStorage.removeItem(LOCATION_KEY);
                    rendition.display(); // fallback to start
                } else {
                    alert("Error loading eBook: " + err.message);
                }
            });

            rendition.on('relocated', (location) => {
                // Save location specific to THIS book
                localStorage.setItem(LOCATION_KEY, location.start.cfi);

                // Update Progress
                if (book.locations.length() > 0) {
                    const percentage = book.locations.percentageFromCfi(location.start.cfi);
                    const pct = Math.round(percentage * 100);
                    els.progressFill.style.width = `${pct}%`;
                    els.progressPercent.textContent = `${pct}%`;
                }

                // Update Chapter Title (approximate)
                // simplified chapter fetching logic
                /* const chapter = book.navigation.get(location.start.href);
                if(chapter) els.chapterInfo.textContent = chapter.label; */
            });

            // Navigation
            const next = () => rendition.next();
            const prev = () => rendition.prev();
            const toggleMenu = () => {
                els.body.classList.toggle('chrome-visible');
            };

            els.zoneNext.addEventListener('click', next);
            els.zonePrev.addEventListener('click', prev);
            els.zoneMenu.addEventListener('click', toggleMenu);

            // Keyboard
            document.addEventListener('keyup', (e) => {
                if (e.key === 'ArrowRight' || e.key === ' ') next();
                if (e.key === 'ArrowLeft') prev();
                if (e.key === 'm') toggleMenu();
                if (e.key === 'Escape') {
                    els.overlayText.classList.remove('visible');
                    els.overlayTheme.classList.remove('visible');
                    els.body.classList.remove('chrome-visible');
                }
            });

            // ─── Settings UI ─────────────────────────────────────────────────────

            function saveSettings() {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
            }

            // Text Settings
            els.btnText.addEventListener('click', () => {
                els.overlayText.classList.toggle('visible');
                els.overlayTheme.classList.remove('visible'); // Close other
            });

            // Theme Settings
            els.btnTheme.addEventListener('click', () => {
                els.overlayTheme.classList.toggle('visible');
                els.overlayText.classList.remove('visible'); // Close other
            });

            // Close Buttons
            els.closeButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const targetId = e.currentTarget.dataset.target;
                    document.getElementById(targetId).classList.remove('visible');
                });
            });

            document.addEventListener('keyup', (e) => {
                if (e.key === 'Escape') {
                    els.overlayText.classList.remove('visible');
                    els.overlayTheme.classList.remove('visible');
                    els.body.classList.remove('chrome-visible');
                }
            });

            // Theme Buttons
            els.btnThemes.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    settings.theme = e.target.dataset.theme;
                    els.body.className = settings.theme;
                    els.btnThemes.forEach(b => b.classList.remove('active'));
                    e.target.classList.add('active');
                    updateLayout();
                    saveSettings();
                });
            });

            // Range Inputs
            const updateSetting = (key, value) => {
                settings[key] = value;
                updateLayout();
                saveSettings();
            };

            els.inputFont.addEventListener('change', (e) => updateSetting('fontFamily', e.target.value));
            els.inputSize.addEventListener('input', (e) => updateSetting('fontSize', e.target.value));
            els.inputLine.addEventListener('input', (e) => updateSetting('lineHeight', e.target.value));

        </script>
    <?php endif; ?>
</body>