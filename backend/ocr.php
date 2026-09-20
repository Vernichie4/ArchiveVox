<?php

function getTesseractExecutable(): string {
    $candidates = [
        'D:\\Program Files\\Tesseract-OCR\\tesseract.exe',  // ← Your Tesseract is here
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
        getenv('TESSERACT_PATH') ?: '',
        'tesseract.exe',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }

    $output = [];
    $exitCode = 0;
    exec('where tesseract 2>nul', $output, $exitCode);
    if ($exitCode === 0 && !empty($output[0])) {
        return trim($output[0]);
    }

    throw new RuntimeException('Tesseract executable not found. Install Tesseract or set TESSERACT_PATH.');
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