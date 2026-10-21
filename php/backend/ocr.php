<?php

function getTesseractExecutable(): string {
    // Check if running on Windows or Linux
    $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

    $candidates = [];

    if ($isWindows) {
        // First check project folder (for local installation)
        $projectDir = dirname(__DIR__, 2); // Go up from php/backend to project root
        $candidates = [
            $projectDir . '\\tesseract\\tesseract.exe',
            'D:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            getenv('TESSERACT_PATH') ?: '',
            'tesseract.exe',
        ];
    } else {
        // Linux paths (Railway)
        $candidates = [
            '/usr/bin/tesseract',
            '/usr/local/bin/tesseract',
            getenv('TESSERACT_PATH') ?: '',
            'tesseract',
        ];
    }

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }

    // Try which/where command
    $output = [];
    $exitCode = 0;
    $command = $isWindows ? 'where tesseract 2>nul' : 'which tesseract 2>/dev/null';
    exec($command, $output, $exitCode);
    if ($exitCode === 0 && !empty($output[0])) {
        return trim($output[0]);
    }

    throw new RuntimeException('Tesseract executable not found. Install Tesseract in the project folder (C:\xampp\htdocs\ArchiveVox\tesseract) or set TESSERACT_PATH.');
}

function runOcr(string $imagePath, string $language = 'eng'): string {
    $tesseract = getTesseractExecutable();

    // Validate image exists
    if (!file_exists($imagePath)) {
        throw new RuntimeException('Image file not found: ' . $imagePath);
    }

    // Validate image is readable
    if (!is_readable($imagePath)) {
        throw new RuntimeException('Image file is not readable: ' . $imagePath);
    }

    // Check file size (max 20MB)
    $fileSize = filesize($imagePath);
    if ($fileSize > 20 * 1024 * 1024) {
        throw new RuntimeException('Image file is too large. Maximum size is 20MB.');
    }

    // For Filipino, use eng+fil for better results
    if ($language === 'fil' || $language === 'tgl') {
        $language = 'eng+fil';
    }

    // Build command with error output
    $command = sprintf(
        '"%s" "%s" stdout --psm 6 -l %s 2>&1',
        $tesseract,
        $imagePath,
        $language
    );

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    $text = implode("\n", $output);

    // Check if OCR failed
    if ($exitCode !== 0 || trim($text) === '') {
        // Check if it's a language error
        if (strpos($text, 'Cannot find language') !== false) {
            throw new RuntimeException('Language pack not found. Please install Filipino language pack for Tesseract.');
        }
        throw new RuntimeException(
            'OCR failed. Error code: ' . $exitCode . '. ' .
            'Make sure the image contains clear, readable text.'
        );
    }

    // Clean up the text
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}