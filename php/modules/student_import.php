<?php
// php/modules/student_import.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../auth/config.php';
require_once __DIR__ . '/../auth/session.php';

// Debug function
function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/../../logs/module_debug.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    if ($data !== null) {
        $logMessage .= "\n" . print_r($data, true);
    }
    file_put_contents($logFile, $logMessage . "\n\n", FILE_APPEND);
}

function parseImportFileWithExtension(string $filePath, string $extension): array {
    $extension = strtolower($extension);
    
    if ($extension === 'csv') {
        return parseCsvFile($filePath);
    } elseif ($extension === 'xlsx') {
        return parseXlsxFile($filePath);
    } else {
        throw new RuntimeException('Only .csv and .xlsx files are supported.');
    }
}

function parseImportFile(string $filePath, string $originalName = ''): array {
    if (!empty($originalName)) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    } else {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    }
    
    if ($extension === 'csv') {
        return parseCsvFile($filePath);
    } elseif ($extension === 'xlsx') {
        return parseXlsxFile($filePath);
    } else {
        throw new RuntimeException('Only .csv and .xlsx files are supported. File extension: .' . $extension);
    }
}

function parseCsvFile(string $filePath): array {
    debugLog('parseCsvFile called');
    
    $rows = [];
    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        throw new RuntimeException('Unable to read uploaded file.');
    }
    
    $delimiters = [',', ';', "\t"];
    $delimiter = ',';
    $firstLine = fgets($handle);
    rewind($handle);
    
    foreach ($delimiters as $d) {
        if (strpos($firstLine, $d) !== false) {
            $delimiter = $d;
            break;
        }
    }
    
    debugLog('Delimiter detected: "' . $delimiter . '"');
    
    $rowCount = 0;
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rowCount++;
        $rows[] = $row;
    }
    
    debugLog('Raw rows read: ' . $rowCount);
    
    fclose($handle);
    
    $normalized = normalizeImportRows($rows);
    debugLog('Normalized rows: ' . count($normalized));
    
    return $normalized;
}

function parseXlsxFile(string $filePath): array {
    debugLog('Parsing XLSX file: ' . $filePath);

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive is not available. Cannot read Excel files.');
    }

    $zip = new ZipArchive();

    if ($zip->open($filePath) !== true) {
        throw new RuntimeException('Unable to open Excel file.');
    }

    // ------------------------------------------------------------
    // Read shared strings
    // ------------------------------------------------------------
    $sharedStrings = [];

    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

    if ($sharedStringsXml !== false) {
        $sharedStringsObj = simplexml_load_string($sharedStringsXml);

        if ($sharedStringsObj !== false) {
            foreach ($sharedStringsObj->si as $si) {
                $text = '';

                // Some Excel files store text directly in <t>
                if (isset($si->t)) {
                    $text = (string) $si->t;
                }

                // Other files can split text into multiple <r><t> nodes
                if (isset($si->r)) {
                    $text = '';

                    foreach ($si->r as $run) {
                        if (isset($run->t)) {
                            $text .= (string) $run->t;
                        }
                    }
                }

                $sharedStrings[] = $text;
            }
        }
    }

    debugLog('Shared strings loaded: ' . count($sharedStrings));

    // ------------------------------------------------------------
    // Read worksheet
    // ------------------------------------------------------------
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');

    if ($sheetXml === false) {
        $sheetXml = $zip->getFromName('xl/worksheets/sheet.xml');
    }

    $zip->close();

    if ($sheetXml === false) {
        throw new RuntimeException('Unable to find worksheet data in Excel file.');
    }

    $sheetXmlObj = simplexml_load_string($sheetXml);

    if ($sheetXmlObj === false) {
        throw new RuntimeException('Unable to parse worksheet data.');
    }

    // ------------------------------------------------------------
    // Convert Excel column letters to zero-based array indexes
    // A = 0
    // B = 1
    // C = 2
    // ...
    // AA = 26
    // ------------------------------------------------------------
    $columnToIndex = function (string $column): int {
        $column = strtoupper($column);
        $index = 0;

        for ($i = 0; $i < strlen($column); $i++) {
            $index = ($index * 26) + (ord($column[$i]) - ord('A') + 1);
        }

        return $index - 1;
    };

    $rows = [];
    $rowCount = 0;

    // ------------------------------------------------------------
    // Read each Excel row
    // ------------------------------------------------------------
    foreach ($sheetXmlObj->sheetData->row as $row) {

        $values = [];

        foreach ($row->c as $cell) {

            // Excel cell reference, e.g. A1, B1, C1, D1
            $cellReference = (string) $cell['r'];

            if ($cellReference === '') {
                continue;
            }

            // Extract column letters from the cell reference
            preg_match('/^[A-Z]+/i', $cellReference, $matches);

            if (empty($matches[0])) {
                continue;
            }

            $columnLetters = $matches[0];
            $columnIndex = $columnToIndex($columnLetters);

            // IMPORTANT:
            // Preserve empty cells so that column positions do not shift.
            if (!array_key_exists($columnIndex, $values)) {
                $values[$columnIndex] = '';
            }

            $cellType = (string) ($cell['t'] ?? '');
            $value = '';

            if (isset($cell->v)) {
                $value = (string) $cell->v;
            }

            // Shared string
            if ($cellType === 's') {
                $sharedIndex = (int) $value;

                if (isset($sharedStrings[$sharedIndex])) {
                    $value = $sharedStrings[$sharedIndex];
                } else {
                    $value = '';
                }
            }

            // Inline string
            elseif ($cellType === 'inlineStr') {
                if (isset($cell->is->t)) {
                    $value = (string) $cell->is->t;
                } else {
                    $value = '';
                }
            }

            $values[$columnIndex] = $value;
        }

        // --------------------------------------------------------
        // Fill missing columns so indexes remain consistent.
        // We support:
        // 0 = LRN
        // 1 = First Name
        // 2 = Last Name
        // 3 = Middle Name
        // 4 = Grade Level
        // 5 = Section
        // 6 = Gender
        // 7 = Birthdate
        // --------------------------------------------------------
        if (!empty($values)) {
            $maxIndex = max(array_keys($values));

            for ($i = 0; $i <= $maxIndex; $i++) {
                if (!array_key_exists($i, $values)) {
                    $values[$i] = '';
                }
            }
        }

        // Make sure the array starts at index 0 and is sequential
        ksort($values);
        $values = array_values($values);

        $rows[] = $values;
        $rowCount++;
    }

    debugLog('Excel rows read: ' . $rowCount);

    if ($rowCount === 0) {
        throw new RuntimeException('No data found in Excel file.');
    }

    // ------------------------------------------------------------
    // Normalize rows using the existing header detection logic
    // ------------------------------------------------------------
    $normalized = normalizeImportRows($rows);

    debugLog('Normalized rows: ' . count($normalized));

    return $normalized;
}

function normalizeImportRows(array $rows): array {
    debugLog('normalizeImportRows called with ' . count($rows) . ' rows');
    
    if (!$rows) {
        debugLog('No rows to normalize');
        return [];
    }
    
    $headerCells = array_map('trim', array_values($rows[0]));
    debugLog('Header cells: ' . print_r($headerCells, true));
    
    $isHeaderRow = false;
    $headerMap = [];
    
    $possibleHeaders = [
        'lrn' => ['lrn', 'learner reference number', 'student no', 'student number', 'student id'],
        'first_name' => ['first name', 'firstname', 'fname', 'given name', 'first', 'given', 'first_name'],
        'last_name' => ['last name', 'lastname', 'lname', 'surname', 'last', 'family name', 'last_name'],
        'middle_name' => ['middle name', 'middlename', 'mname', 'middle', 'mothers maiden', 'middle_name'],
        'grade_level' => ['grade', 'grade level', 'level', 'year', 'year level', 'grade_level'],
        'section' => ['section', 'class', 'division', 'group'],
        'gender' => ['gender', 'sex', 'gender'],
        'birthdate' => ['birthdate', 'birth date', 'dob', 'date of birth', 'birthday', 'birthdate']
    ];
    
    foreach ($headerCells as $index => $cell) {
        $cellClean = strtolower(trim(str_replace(['_', '-', ' '], '', $cell)));
        foreach ($possibleHeaders as $field => $keywords) {
            foreach ($keywords as $keyword) {
                $keywordClean = strtolower(trim(str_replace(['_', '-', ' '], '', $keyword)));
                if ($cellClean === $keywordClean || strpos($cellClean, $keywordClean) !== false) {
                    $isHeaderRow = true;
                    $headerMap[$field] = $index;
                    debugLog("Mapped '$field' to column $index ('$cell')");
                    break 2;
                }
            }
        }
    }
    
    foreach ($headerCells as $index => $cell) {

        $cellClean = strtolower(
            trim(
                str_replace(['_', '-', ' '], '', $cell)
            )
        );

        // ------------------------------------------------------------
        // FIRST: Exact header matching
        // ------------------------------------------------------------
        // This is important because broad substring matching can
        // incorrectly identify "middle_name" as "id".
        // ------------------------------------------------------------

        foreach ($possibleHeaders as $field => $keywords) {

            foreach ($keywords as $keyword) {

                $keywordClean = strtolower(
                    trim(
                        str_replace(['_', '-', ' '], '', $keyword)
                    )
                );

                if ($cellClean === $keywordClean) {

                    $isHeaderRow = true;
                    $headerMap[$field] = $index;

                    debugLog(
                        "Exact header mapping: '$field' => column $index ('$cell')"
                    );

                    break 2;
                }
            }
        }
    }
    
    debugLog('isHeaderRow: ' . ($isHeaderRow ? 'YES' : 'NO'));
    debugLog('headerMap: ' . print_r($headerMap, true));
    
    $dataRows = $isHeaderRow ? array_slice($rows, 1) : $rows;
    debugLog('Data rows to process: ' . count($dataRows));
    
    $normalized = [];
    
    foreach ($dataRows as $rowIndex => $row) {
        $cells = array_values($row);
        $cells = array_pad($cells, 8, '');
        
        $normalizedRow = [
            'lrn' => '',
            'first_name' => '',
            'last_name' => '',
            'middle_name' => '',
            'grade_level' => '',
            'section' => '',
            'gender' => '',
            'birthdate' => ''
        ];
        
        if ($isHeaderRow && !empty($headerMap)) {
            foreach ($headerMap as $field => $index) {
                if (isset($cells[$index])) {
                    $normalizedRow[$field] = trim((string) $cells[$index]);
                }
            }
        } else {
            $normalizedRow['lrn'] = trim((string) ($cells[0] ?? ''));
            $normalizedRow['first_name'] = trim((string) ($cells[1] ?? ''));
            $normalizedRow['last_name'] = trim((string) ($cells[2] ?? ''));
            $normalizedRow['middle_name'] = trim((string) ($cells[3] ?? ''));
            $normalizedRow['grade_level'] = trim((string) ($cells[4] ?? ''));
            $normalizedRow['section'] = trim((string) ($cells[5] ?? ''));
            $normalizedRow['gender'] = trim((string) ($cells[6] ?? ''));
            $normalizedRow['birthdate'] = trim((string) ($cells[7] ?? ''));
        }
        
        debugLog("Row " . ($rowIndex + 1) . " normalized: " . print_r($normalizedRow, true));
        
        if (!empty($normalizedRow['first_name']) || !empty($normalizedRow['last_name'])) {
            $normalized[] = $normalizedRow;
        } else {
            debugLog("Row " . ($rowIndex + 1) . " skipped - no name");
        }
    }
    
    debugLog('Normalization complete. Returning ' . count($normalized) . ' rows');
    return $normalized;
}

function previewStudents(array $rows): array {
    debugLog('previewStudents called with ' . count($rows) . ' rows');
    
    $validRows = [];
    $errors = [];
    
    foreach ($rows as $index => $row) {
        debugLog("Previewing row " . ($index + 1) . ": " . print_r($row, true));
        
        $record = [
            'row_number' => $index + 2,
            'lrn' => $row['lrn'] ?? '',
            'first_name' => $row['first_name'] ?? '',
            'last_name' => $row['last_name'] ?? '',
            'middle_name' => $row['middle_name'] ?? '',
            'grade_level' => $row['grade_level'] ?? '',
            'section' => $row['section'] ?? '',
            'gender' => $row['gender'] ?? '',
            'birthdate' => $row['birthdate'] ?? '',
            'status' => 'valid',
            'message' => ''
        ];
        
        if (empty($record['first_name']) && empty($record['last_name'])) {
            $record['status'] = 'error';
            $record['message'] = 'Missing name';
            $errors[] = $record;
            debugLog("Row " . ($index + 2) . " error: Missing name");
        } elseif (empty($record['first_name'])) {
            $record['status'] = 'error';
            $record['message'] = 'Missing first name';
            $errors[] = $record;
            debugLog("Row " . ($index + 2) . " error: Missing first name");
        } elseif (empty($record['last_name'])) {
            $record['status'] = 'error';
            $record['message'] = 'Missing last name';
            $errors[] = $record;
            debugLog("Row " . ($index + 2) . " error: Missing last name");
        } else {
            $validRows[] = $record;
            debugLog("Row " . ($index + 2) . " valid: " . $record['first_name'] . ' ' . $record['last_name']);
        }
    }
    
    $result = [
        'rows' => $validRows,
        'errors' => $errors,
        'summary' => [
            'total' => count($rows),
            'valid' => count($validRows),
            'errors' => count($errors),
        ]
    ];
    
    debugLog('Preview complete. Valid: ' . $result['summary']['valid'] . ', Errors: ' . $result['summary']['errors']);
    return $result;
}

function importStudents(array $rows, int $teacherId): array {
    global $pdo;

    $successfulImports = 0;
    $skippedRecords = 0;
    $errors = [];

    // Get default category_id
    $stmt = $pdo->prepare("SELECT category_id FROM student_category LIMIT 1");
    $stmt->execute();

    $defaultCategory = $stmt->fetch();

    $defaultCategoryId = $defaultCategory
        ? $defaultCategory['category_id']
        : 1;

    foreach ($rows as $row) {

        $lrn = trim((string) ($row['lrn'] ?? ''));
        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName = trim((string) ($row['last_name'] ?? ''));
        $middleName = trim((string) ($row['middle_name'] ?? ''));

        $gradeLevel = trim(
            (string) ($row['grade_level'] ?? 'Grade 2')
        );

        debugLog('NORMALIZED ROW TEST', [
            'lrn' => $lrn,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'grade_level' => $gradeLevel,
            'section' => $section,
            'gender' => $gender,
            'birthdate' => $birthdate
        ]);

        $section = trim(
            (string) ($row['section'] ?? '')
        );

        $gender = trim(
            (string) ($row['gender'] ?? '')
        );

        $birthdate = trim(
            (string) ($row['birthdate'] ?? '')
        );

        debugLog('IMPORT SECTION TEST', [
            'lrn' => $lrn,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'grade_level' => $gradeLevel,
            'section' => $section
        ]);

        // --------------------------------------------------------
        // Required fields
        // --------------------------------------------------------

        if ($firstName === '' || $lastName === '') {
            $skippedRecords++;

            $errors[] =
                'Row missing required fields: '
                . $firstName . ' ' . $lastName;

            continue;
        }

        // --------------------------------------------------------
        // Check if LRN already exists
        // --------------------------------------------------------

        if (!empty($lrn)) {

            $stmt = $pdo->prepare(
                'SELECT student_id
                 FROM student
                 WHERE lrn = :lrn'
            );

            $stmt->execute([
                ':lrn' => $lrn
            ]);

            if ($stmt->fetch()) {
                $skippedRecords++;
                continue;
            }
        }

        // --------------------------------------------------------
        // Get category_id for grade level
        // --------------------------------------------------------

        $categoryId = $defaultCategoryId;

        if (!empty($gradeLevel)) {

            $stmt = $pdo->prepare(
                "SELECT category_id
                 FROM student_category
                 WHERE grade_level = ?"
            );

            $stmt->execute([
                $gradeLevel
            ]);

            $category = $stmt->fetch();

            if ($category) {
                $categoryId = $category['category_id'];
            }
        }

        // Get or create class for Grade Level + Section
        $classId = null;

        if (!empty($section)) {
            $classId = getOrCreateClass(
                $teacherId,
                $gradeLevel,
                $section
            );
        }

        debugLog('CLASS ASSIGNMENT TEST', [
            'lrn' => $lrn,
            'student' => $firstName . ' ' . $lastName,
            'grade_level' => $gradeLevel,
            'section_from_excel' => $section,
            'class_id_returned' => $classId
        ]);

        // --------------------------------------------------------
        // Normalize gender
        // --------------------------------------------------------

        $genderNormalized = null;

        if (!empty($gender)) {

            $g = strtolower(trim($gender));

            if (in_array(
                $g,
                ['male', 'm', 'boy', '1']
            )) {
                $genderNormalized = 'Male';

            } elseif (in_array(
                $g,
                ['female', 'f', 'girl', '2']
            )) {
                $genderNormalized = 'Female';
            }
        }

        // --------------------------------------------------------
        // Normalize birthdate
        // --------------------------------------------------------

        $birthdateNormalized = null;

        if (!empty($birthdate)) {

            // Remove time component if present
            // Example:
            // 2015-09-25 00:00:00
            // becomes:
            // 2015-09-25

            $birthdate = preg_replace(
                '/\s+00:00:00$/',
                '',
                $birthdate
            );

            $formats = [
                'Y-m-d',
                'm/d/Y',
                'm-d-Y',
                'd/m/Y',
                'd-m-Y'
            ];

            foreach ($formats as $format) {

                $date = DateTime::createFromFormat(
                    $format,
                    $birthdate
                );

                if ($date !== false) {

                    $birthdateNormalized =
                        $date->format('Y-m-d');

                    break;
                }
            }
        }

        // --------------------------------------------------------
        // Insert student
        // --------------------------------------------------------

        try {
            $stmt = $pdo->prepare('
                INSERT INTO student (
                    teacher_id,
                    category_id,
                    class_id,
                    lrn,
                    first_name,
                    middle_name,
                    last_name,
                    gender,
                    birthdate,
                    is_active
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ');

            $stmt->execute([
                $teacherId,
                $categoryId,
                $classId,
                $lrn ?: null,
                $firstName,
                $middleName ?: null,
                $lastName,
                $genderNormalized,
                $birthdateNormalized
            ]);

            $successfulImports++;

        } catch (PDOException $e) {
            $skippedRecords++;

            $errors[] =
                'Database error for '
                . $firstName . ' '
                . $lastName
                . ': '
                . $e->getMessage();
        }
    }

    return [
        'success' => true,

        'summary' => [
            'total' => count($rows),
            'imported' => $successfulImports,
            'skipped' => $skippedRecords,
            'errors' => count($errors)
        ],

        'errors' => $errors
    ];
}

function getOrCreateCategory(string $gradeLevel): int {
    global $pdo;
    
    $stmt = $pdo->prepare('SELECT category_id FROM student_category WHERE grade_level = :grade_level LIMIT 1');
    $stmt->execute([':grade_level' => $gradeLevel]);
    $category = $stmt->fetch();
    
    if ($category) {
        return (int) $category['category_id'];
    }
    
    $stmt = $pdo->prepare('INSERT INTO student_category (grade_level, school_year) VALUES (:grade_level, :school_year)');
    $schoolYear = date('Y') . '-' . (date('Y') + 1);
    $stmt->execute([
        ':grade_level' => $gradeLevel,
        ':school_year' => $schoolYear
    ]);
    
    return (int) $pdo->lastInsertId();
}

function getOrCreateClass(int $teacherId, string $gradeLevel, string $section): ?int {
    global $pdo;
    
    if (empty($section)) {
        return null;
    }
    
    $stmt = $pdo->prepare('
        SELECT class_id FROM class 
        WHERE teacher_id = :teacher_id AND grade_level = :grade_level AND section = :section
        LIMIT 1
    ');
    $stmt->execute([
        ':teacher_id' => $teacherId,
        ':grade_level' => $gradeLevel,
        ':section' => $section
    ]);
    $class = $stmt->fetch();
    
    if ($class) {
        return (int) $class['class_id'];
    }
    
    $stmt = $pdo->prepare('
        INSERT INTO class (teacher_id, grade_level, section, school_year) 
        VALUES (:teacher_id, :grade_level, :section, :school_year)
    ');
    $schoolYear = date('Y') . '-' . (date('Y') + 1);
    $stmt->execute([
        ':teacher_id' => $teacherId,
        ':grade_level' => $gradeLevel,
        ':section' => $section,
        ':school_year' => $schoolYear
    ]);
    
    return (int) $pdo->lastInsertId();
}

function normalizeGender(string $value): ?string {
    $value = strtolower(trim($value));
    if (in_array($value, ['male', 'm', 'boy', '1'])) {
        return 'Male';
    }
    if (in_array($value, ['female', 'f', 'girl', '2'])) {
        return 'Female';
    }
    return null;
}

function normalizeDate(string $value): ?string {
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    
    $formats = ['Y-m-d', 'm/d/Y', 'm-d-Y', 'd/m/Y', 'd-m-Y', 'Y/m/d'];
    foreach ($formats as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
    }
    
    return null;
}

// ============================================================
// API ENDPOINT HANDLER - THIS IS IMPORTANT!
// ============================================================

// Check if this file is being accessed directly (not included)

    // Set JSON content type (already set, but ensure it)
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        // Get the action from request
        $action = $_GET['action'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Get teacher_id from session
        $currentUser = currentUser();
        $teacherId = $currentUser['teacher_id'] ?? null;
        
        // For test action, allow without login
        if ($action !== 'test' && !$teacherId) {
            throw new RuntimeException('You must be logged in to import students.');
        }
        
        if ($action === 'preview' && $method === 'POST') {
            // Handle file upload and preview
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = isset($_FILES['file']) ? 'Upload error code: ' . $_FILES['file']['error'] : 'No file uploaded';
                throw new RuntimeException('No file uploaded or upload error occurred. ' . $errorMsg);
            }
            
            $filePath = $_FILES['file']['tmp_name'];
            $originalName = $_FILES['file']['name'];
            
            debugLog('File uploaded: ' . $originalName . ' (' . $_FILES['file']['size'] . ' bytes)');
            
            // Parse the file
            $rows = parseImportFile($filePath, $originalName);
            
            // Preview the data
            $preview = previewStudents($rows);
            
            echo json_encode([
                'success' => true,
                'preview' => $preview,
                'rows' => $rows // Store rows for import
            ]);
            
        } elseif ($action === 'import' && $method === 'POST') {
            // Handle import
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['rows']) || !is_array($input['rows'])) {
                throw new RuntimeException('No rows provided for import.');
            }
            
            $result = importStudents($input['rows'], (int)$teacherId);
            
            echo json_encode($result);
            
        } elseif ($action === 'test') {
            // Test endpoint to check if the file is accessible
            echo json_encode([
                'success' => true,
                'message' => 'Student import API is working',
                'php_version' => PHP_VERSION,
                'zip_loaded' => class_exists('ZipArchive'),
                'session_user_id' => $teacherId,
                'session_status' => session_status(),
                'post_max_size' => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize')
            ]);
            
        } else {
            throw new RuntimeException('Invalid action or method. Use ?action=preview or ?action=import');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
    
    exit;

?>