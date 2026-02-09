<?php
/**
 * Calibre Conversion Helper Functions
 * Archive System - Quezon City Public Library
 * 
 * This file provides functions for converting ebook formats using Calibre's ebook-convert tool.
 */

/**
 * Check if Calibre is available on the system
 * @return bool Whether Calibre ebook-convert is accessible
 */
function isCalibreAvailable()
{
    if (!defined('CALIBRE_CONVERT_PATH')) {
        return false;
    }

    $calibrePath = CALIBRE_CONVERT_PATH;

    // Check if the file exists
    if (!file_exists($calibrePath)) {
        error_log("Calibre not found at: $calibrePath");
        return false;
    }

    return true;
}

/**
 * Convert MOBI file to EPUB using Calibre
 * 
 * @param string $mobiFilePath Full path to the source MOBI file
 * @param string|null $outputPath Optional output path (defaults to same directory with .epub extension)
 * @return array ['success' => bool, 'epub_path' => string|null, 'error' => string|null]
 */
function convertMobiToEpub($mobiFilePath, $outputPath = null)
{
    // Check if Calibre is available
    if (!isCalibreAvailable()) {
        return [
            'success' => false,
            'epub_path' => null,
            'error' => 'Calibre ebook-convert is not available. Please check the CALIBRE_CONVERT_PATH in config.php'
        ];
    }

    // Check if source file exists
    if (!file_exists($mobiFilePath)) {
        return [
            'success' => false,
            'epub_path' => null,
            'error' => 'Source MOBI file not found: ' . $mobiFilePath
        ];
    }

    // Generate output path if not provided
    if ($outputPath === null) {
        $outputPath = preg_replace('/\.mobi$/i', '.epub', $mobiFilePath);
    }

    // Ensure output directory exists
    $outputDir = dirname($outputPath);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    // Build the command
    $calibrePath = CALIBRE_CONVERT_PATH;

    // Escape paths for command line
    $escapedInput = escapeshellarg($mobiFilePath);
    $escapedOutput = escapeshellarg($outputPath);
    $escapedCalibre = escapeshellarg($calibrePath);

    // Build command - use quotes around the path on Windows
    $command = "$escapedCalibre $escapedInput $escapedOutput 2>&1";

    // Execute the conversion
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);

    // Check if conversion was successful
    if ($returnCode === 0 && file_exists($outputPath)) {
        return [
            'success' => true,
            'epub_path' => $outputPath,
            'error' => null
        ];
    } else {
        $errorMessage = implode("\n", $output);
        error_log("Calibre conversion failed: $errorMessage");
        return [
            'success' => false,
            'epub_path' => null,
            'error' => 'Conversion failed: ' . $errorMessage
        ];
    }
}

/**
 * Get the EPUB path for a MOBI file (checks if converted version exists)
 * 
 * @param string $mobiFilePath Path to the MOBI file
 * @return string|null Path to EPUB if exists, null otherwise
 */
function getConvertedEpubPath($mobiFilePath)
{
    $epubPath = preg_replace('/\.mobi$/i', '.epub', $mobiFilePath);

    if (file_exists($epubPath)) {
        return $epubPath;
    }

    return null;
}

/**
 * Ensure an EPUB version exists for a MOBI file (convert if needed)
 * 
 * @param string $mobiFilePath Path to the MOBI file
 * @return array ['success' => bool, 'epub_path' => string|null, 'error' => string|null]
 */
function ensureEpubExists($mobiFilePath)
{
    // Check if EPUB already exists
    $existingEpub = getConvertedEpubPath($mobiFilePath);
    if ($existingEpub) {
        return [
            'success' => true,
            'epub_path' => $existingEpub,
            'error' => null
        ];
    }

    // Convert MOBI to EPUB
    return convertMobiToEpub($mobiFilePath);
}
