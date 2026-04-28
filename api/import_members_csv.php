<?php
require_once __DIR__ . '/../includes/api_bootstrap.php';

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// Check file upload
if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $errCodes = [
        UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
        UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
        UPLOAD_ERR_PARTIAL => 'Upload incomplete',
        UPLOAD_ERR_NO_FILE => 'No file selected',
    ];
    $code = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    exit(json_encode([
        'success' => false,
        'message' => $errCodes[$code] ?? 'Upload failed'
    ]));
}

$file = $_FILES['csv_file']['tmp_name'];
$maxSize = 2 * 1024 * 1024; // 2MB
if ($_FILES['csv_file']['size'] > $maxSize) {
    exit(json_encode(['success' => false, 'message' => 'File too large (max 2MB)']));
}

// Only CSV/TXT
$ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'txt'])) {
    exit(json_encode(['success' => false, 'message' => 'Only .csv and .txt files allowed']));
}

$handle = fopen($file, 'r');
if (!$handle) {
    exit(json_encode(['success' => false, 'message' => 'Cannot read file']));
}

$userId = (int)$_SESSION['user_id'];
$db = db();

// Load existing member names (case-insensitive check)
$existing = $db->prepare("SELECT LOWER(name) as lname FROM members WHERE user_id=?");
$existing->execute([$userId]);
$existingNames = [];
while ($row = $existing->fetch()) {
    $existingNames[$row['lname']] = true;
}

$insertStmt = $db->prepare("INSERT INTO members (user_id, name, phone, email) VALUES (?,?,?,?)");

$rowNumber = 0;
$imported = 0;
$skipped = 0;
$errors = [];

while (($data = fgetcsv($handle)) !== false) {
    $rowNumber++;

    // Skip empty rows
    if (empty(array_filter($data))) continue;

    // Auto-detect header row
    $firstVal = strtolower(trim($data[0] ?? ''));
    if ($rowNumber === 1 && in_array($firstVal, ['name', 'member', '名前'])) {
        continue;
    }

    $name  = trim($data[0] ?? '');
    $phone = trim($data[1] ?? '');
    $email = trim($data[2] ?? '');

    // Name required
    if ($name === '') {
        $errors[] = "Row $rowNumber: Name is empty — skipped";
        $skipped++;
        continue;
    }

    // Check for duplicate name (case insensitive)
    $nameLower = strtolower($name);
    if (isset($existingNames[$nameLower])) {
        $errors[] = "Row $rowNumber: \"$name\" already exists — skipped";
        $skipped++;
        continue;
    }

    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = ''; // Clear invalid email silently
    }

    // Insert member
    try {
        $insertStmt->execute([$userId, $name, $phone, $email]);
        $newId = (int)$db->lastInsertId();
        $existingNames[$nameLower] = true;
        $imported++;

        // Log each import
        logActivity($db, $userId, 'member.add', 'member', $newId, "Imported via CSV: $name");
    } catch (Exception $e) {
        $errors[] = "Row $rowNumber: \"$name\" failed — " . $e->getMessage();
        $skipped++;
    }
}

fclose($handle);

// Log the overall import action
logActivity($db, $userId, 'member.import', 'system', 0, "CSV import: {$imported} imported, {$skipped} skipped");

echo json_encode([
    'success' => $imported > 0,
    'imported' => $imported,
    'skipped' => $skipped,
    'errors' => $errors,
    'message' => $imported > 0
        ? "{$imported} member(s) imported successfully" . ($skipped > 0 ? ", {$skipped} skipped" : '')
        : "No members were imported"
]);
