
<?php
session_start();

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'student'
) {
    http_response_code(403);
    exit('Access denied. Please log in with your student account.');
}

require_once __DIR__ . '/config/database.php';

$stmt = $pdo->prepare(
    "SELECT resume_path
     FROM student_profiles
     WHERE user_id = ?
     LIMIT 1"
);

$stmt->execute([(int) $_SESSION['user_id']]);
$profile = $stmt->fetch();

if (!$profile || empty($profile['resume_path'])) {
    http_response_code(404);
    exit('No resume has been uploaded.');
}

$relativePath = $profile['resume_path'];

// Allow only files stored in the resume directory
if (
    strpos($relativePath, 'uploads/resumes/') !== 0 ||
    basename($relativePath) !==
        substr($relativePath, strlen('uploads/resumes/')) ||
    strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'pdf'
) {
    http_response_code(400);
    exit('Invalid resume file path.');
}

$filePath = __DIR__ . '/' . $relativePath;

if (!is_file($filePath)) {
    http_response_code(404);
    exit('Resume file not found.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: attachment; filename="my-resume.pdf"');
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
