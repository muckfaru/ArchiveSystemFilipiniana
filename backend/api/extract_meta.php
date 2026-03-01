<?php
/**
 * Metadata Extraction API
 * Archive System - Quezon City Public Library
 *
 * Accepts an uploaded EPUB or MOBI file and returns its embedded metadata as JSON.
 * Only authenticated admin users can call this endpoint.
 */

require_once __DIR__ . '/../core/auth.php'; // ensures session + $pdo available

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'No file received']);
    exit;
}

$file = $_FILES['file'];
$tmpPath = $file['tmp_name'];
$origName = $file['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

$allowedExts = ['epub', 'mobi', 'pdf', 'jpg', 'jpeg', 'png'];

if (!in_array($ext, $allowedExts, true)) {
    echo json_encode(['success' => false, 'message' => 'File type not supported for auto-extraction']);
    exit;
}

$meta = [
    'title' => '',
    'creator' => '',   // author / publisher guess
    'publisher' => '',
    'publication_date' => '',
    'description' => '',
    'keywords' => '',
    'language' => '',
    'page_count' => '',
];

// ── EPUB ─────────────────────────────────────────────────────────────────────
if ($ext === 'epub') {
    $zip = new ZipArchive();
    if ($zip->open($tmpPath) !== true) {
        echo json_encode(['success' => false, 'message' => 'Could not open EPUB file']);
        exit;
    }

    // 1. Find OPF path from META-INF/container.xml
    $opfPath = null;
    $containerXml = $zip->getFromName('META-INF/container.xml');
    if ($containerXml) {
        $cDom = new DOMDocument();
        @$cDom->loadXML($containerXml);
        $rootfiles = $cDom->getElementsByTagName('rootfile');
        if ($rootfiles->length > 0) {
            $opfPath = $rootfiles->item(0)->getAttribute('full-path');
        }
    }

    // 2. Parse OPF for Dublin Core fields
    if ($opfPath) {
        $opfContent = $zip->getFromName($opfPath);
        if ($opfContent) {
            $opf = new DOMDocument();
            @$opf->loadXML($opfContent);

            $xp = new DOMXPath($opf);

            /**
             * Grab a single DC/OPF element by its local-name.
             * local-name() is namespace-agnostic — works on any well-formed OPF.
             */
            $grab = function (string $localName) use ($xp): string {
                $nodes = $xp->query("//*[local-name()='{$localName}']");
                if ($nodes && $nodes->length > 0) {
                    return trim($nodes->item(0)->textContent);
                }
                return '';
            };

            $grabAll = function (string $localName) use ($xp): array {
                $nodes = $xp->query("//*[local-name()='{$localName}']");
                $out = [];
                if ($nodes) {
                    foreach ($nodes as $n) {
                        $v = trim($n->textContent);
                        if ($v)
                            $out[] = $v;
                    }
                }
                return $out;
            };

            $meta['title'] = $grab('title');
            $meta['creator'] = $grab('creator');
            $meta['publisher'] = $grab('publisher');
            $meta['description'] = $grab('description');
            $meta['language'] = $grab('language');

            // Subject → keywords (there can be multiple subject elements)
            $subjects = $grabAll('subject');
            $meta['keywords'] = implode(', ', $subjects);

            // Date
            $rawDate = $grab('date');
            if (!$rawDate) {
                // EPUB3 uses <meta property="dcterms:modified"> or similar
                $metaNodes = $xp->query("//*[local-name()='meta']");
                foreach ($metaNodes as $mn) {
                    $prop = $mn->getAttribute('property');
                    if (stripos($prop, 'modified') !== false || stripos($prop, 'date') !== false) {
                        $rawDate = trim($mn->textContent);
                        break;
                    }
                }
            }
            if ($rawDate) {
                if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $rawDate, $dm)) {
                    $meta['publication_date'] = $dm[1] . '-' . $dm[2] . '-' . $dm[3];
                } elseif (preg_match('/(\d{4})-(\d{2})/', $rawDate, $dm)) {
                    $meta['publication_date'] = $dm[1] . '-' . $dm[2] . '-01';
                } elseif (preg_match('/(\d{4})/', $rawDate, $dm)) {
                    $meta['publication_date'] = $dm[1] . '-01-01';
                }
            }
        }
    }

    $zip->close();
}

// ── MOBI ─────────────────────────────────────────────────────────────────────
if ($ext === 'mobi') {
    // MOBI is a PalmDB file. The MOBI header contains a title field
    // and EXTHHeader contains publisher, date, description, etc.
    $fp = fopen($tmpPath, 'rb');
    if ($fp) {
        // PalmDB header: first 32 bytes = name (null-padded ASCII)
        $palmName = rtrim(fread($fp, 32), "\0");
        $meta['title'] = $palmName ?: '';

        // Skip rest of PalmDB header (78 bytes total before record list)
        // Record 0 offset is at byte 78; we need to read 2-byte numRecords first.
        fseek($fp, 76);
        $numRecords = unpack('n', fread($fp, 2))[1];

        // Read record 0 offset (the PalmDOC/MOBI record)
        fseek($fp, 78); // start of record list
        $rec0Offset = unpack('N', fread($fp, 4))[1];

        // Jump to MOBI record 0
        fseek($fp, $rec0Offset);

        // PalmDOC header: 16 bytes
        $palmdoc = fread($fp, 16);

        // MOBI header starts after PalmDOC header
        $mobi_id = fread($fp, 4);
        if ($mobi_id !== 'MOBI') {
            // Not a standard MOBI, close and return what we have
            fclose($fp);
            echo json_encode(['success' => true, 'meta' => $meta]);
            exit;
        }

        $headerLen = unpack('N', fread($fp, 4))[1];

        // Skip to EXTH flag offset (offset 16 from MOBI id, i.e. rec0_offset + 16 (palmdoc) + 4 (id) + 4 (len) + 8 = 32)
        // EXTH flag is at MOBI header offset 128 (from start of MOBI section)
        fseek($fp, $rec0Offset + 16 + 128);
        $exthFlag = unpack('N', fread($fp, 4))[1];

        if ($exthFlag & 0x40) {
            // EXTH block present: positioned right after the MOBI header
            fseek($fp, $rec0Offset + 16 + $headerLen);
            $exth_id = fread($fp, 4);
            if ($exth_id === 'EXTH') {
                $exthLen = unpack('N', fread($fp, 4))[1];
                $exthCount = unpack('N', fread($fp, 4))[1];

                for ($i = 0; $i < $exthCount; $i++) {
                    $recType = unpack('N', fread($fp, 4))[1];
                    $recLen = unpack('N', fread($fp, 4))[1];
                    $recData = fread($fp, $recLen - 8);

                    switch ($recType) {
                        case 100:
                            $meta['creator'] = trim($recData);
                            break; // Author
                        case 101:
                            $meta['publisher'] = trim($recData);
                            break;
                        case 103:
                            $meta['description'] = trim($recData);
                            break;
                        case 104:
                            $meta['keywords'] = trim($recData);
                            break;
                        case 105:
                            $meta['keywords'] = trim($recData);
                            break; // Subject
                        case 106:
                            // Date: YYYY or YYYY-MM-DD
                            $rawDate = trim($recData);
                            if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $rawDate, $m)) {
                                $meta['publication_date'] = $m[1] . '-' . $m[2] . '-' . $m[3];
                            } elseif (preg_match('/(\d{4})/', $rawDate, $m)) {
                                $meta['publication_date'] = $m[1] . '-01-01';
                            }
                            break;
                        case 524:
                            $meta['language'] = trim($recData);
                            break;
                    }
                }
            }
        }

        fclose($fp);
    }
}

// ── PDF & Images (Basic Info & Thumbnail Generation) ────────────────────────
$thumbnailUrl = null;
if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
    // 1. Basic Metadata Guesses from Filename
    $baseName = pathinfo($origName, PATHINFO_FILENAME);

    // Replace underscores/dashes with spaces for Title
    $cleanName = str_replace(['_', '-'], ' ', $baseName);
    $meta['title'] = ucwords(trim($cleanName));

    // Try to find a year in the filename (e.g., "Report_2023")
    if (preg_match('/(19|20)\d{2}/', $baseName, $matches)) {
        $meta['publication_date'] = $matches[0] . '-01-01';
    }

    // 2. Thumbnail Generation
    $uploadDir = __DIR__ . '/../../uploads/thumbnails/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $thumbFilename = uniqid('thumb_auto_') . '.jpg';
    $thumbDest = $uploadDir . $thumbFilename;
    $thumbRelPath = 'uploads/thumbnails/' . $thumbFilename;

    try {
        if ($ext === 'pdf') {
            // Attempt Imagick for PDF first page
            if (class_exists('Imagick')) {
                $im = new Imagick();
                $im->setResolution(150, 150);
                $im->readImage($tmpPath . '[0]'); // Read first page only
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(80);
                // Resize proportionally
                $im->resizeImage(400, 0, Imagick::FILTER_LANCZOS, 1);
                $im->writeImage($thumbDest);
                $im->clear();
                $im->destroy();
                $thumbnailUrl = $thumbRelPath;
            }
        } else {
            // It's an image, so it is its own thumbnail (or we compress it)
            if (class_exists('Imagick')) {
                $im = new Imagick($tmpPath);
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(80);
                $im->thumbnailImage(400, 400, true); // fit within 400x400
                $im->writeImage($thumbDest);
                $im->clear();
                $im->destroy();
                $thumbnailUrl = $thumbRelPath;
            } else {
                // Fallback GD resizing
                $sourceImage = null;
                if ($ext === 'png') {
                    $sourceImage = @imagecreatefrompng($tmpPath);
                } elseif ($ext === 'jpg' || $ext === 'jpeg') {
                    $sourceImage = @imagecreatefromjpeg($tmpPath);
                }

                if ($sourceImage) {
                    $srcW = imagesx($sourceImage);
                    $srcH = imagesy($sourceImage);
                    $dstW = 400;
                    $dstH = ($dstW / $srcW) * $srcH;

                    $destImage = imagecreatetruecolor($dstW, $dstH);
                    imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
                    imagejpeg($destImage, $thumbDest, 80);

                    imagedestroy($sourceImage);
                    imagedestroy($destImage);
                    $thumbnailUrl = $thumbRelPath;
                }
            }
        }
    } catch (Exception $e) {
        // Thumbnail generation failed (missing extension/Ghostscript), ignore silently
    }
}

// ── Post-process ─────────────────────────────────────────────────────────────
// If publisher is empty but creator is set, use creator as a fallback hint
// (creator is the author, not the publisher — keep them separate, just return both)

// Sanitize: strip HTML tags from description
$meta['description'] = strip_tags($meta['description']);

// Truncate overly long descriptions
if (mb_strlen($meta['description']) > 1000) {
    $meta['description'] = mb_substr($meta['description'], 0, 1000) . '…';
}

// Language: try to match against DB languages by ISO code or name
$detectedLang = $meta['language'];
$languageId = null;
if ($detectedLang) {
    // Try exact match by name, then by first 2 chars (ISO code)
    $stmt = $pdo->prepare("SELECT id FROM languages WHERE LOWER(name) = LOWER(?) OR LOWER(SUBSTR(name,1,2)) = LOWER(SUBSTR(?,1,2)) ORDER BY id ASC LIMIT 1");
    $stmt->execute([$detectedLang, $detectedLang]);
    $row = $stmt->fetch();
    if ($row)
        $languageId = $row['id'];
}

echo json_encode([
    'success' => true,
    'meta' => $meta,
    'languageId' => $languageId,
    'thumbnail_url' => $thumbnailUrl ? APP_URL . '/' . $thumbnailUrl : null,
    'thumbnail_path' => $thumbnailUrl
]);
exit;
