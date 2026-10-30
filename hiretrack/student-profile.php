
<?php
session_start();

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'student'
) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = (int) $_SESSION['user_id'];
$errors = [];

$success = $_SESSION['profile_success'] ?? '';
unset($_SESSION['profile_success']);

// Fetch existing profile
$stmt = $pdo->prepare(
    "SELECT id, education, skills, phone, resume_path
     FROM student_profiles
     WHERE user_id = ?
     LIMIT 1"
);
$stmt->execute([$userId]);

$profile = $stmt->fetch() ?: [
    'id' => null,
    'education' => '',
    'skills' => '',
    'phone' => '',
    'resume_path' => null
];

$education = $profile['education'] ?? '';
$skills = $profile['skills'] ?? '';
$phone = $profile['phone'] ?? '';

// Handle profile submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $errors[] = 'Session validation failed. Refresh the page and try again.';
    } else {
        $education = trim($_POST['education'] ?? '');
        $skills = trim($_POST['skills'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Validate profile fields
        if (strlen($education) > 150) {
            $errors[] = 'Education must not exceed 150 characters.';
        }

        if (strlen($phone) > 20) {
            $errors[] = 'Phone number must not exceed 20 characters.';
        }

        if (strlen($skills) > 20000) {
            $errors[] = 'Skills information is too long.';
        }

        $newResumePath = null;
        $newResumeAbsolutePath = null;

        // Validate optional resume upload
        if (
            isset($_FILES['resume']) &&
            $_FILES['resume']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            $file = $_FILES['resume'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Resume upload failed. Please select the file again.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Your resume must be 5 MB or smaller.';
            } elseif (
                strtolower(
                    pathinfo($file['name'], PATHINFO_EXTENSION)
                ) !== 'pdf'
            ) {
                $errors[] = 'Only PDF resumes are allowed.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($file['tmp_name']);

                if ($mimeType !== 'application/pdf') {
                    $errors[] = 'The uploaded file must be a valid PDF.';
                }
            }

            // Save the uploaded PDF
            if (!$errors) {
                $uploadDirectory = __DIR__ . '/uploads/resumes';

                if (
                    !is_dir($uploadDirectory) &&
                    !mkdir($uploadDirectory, 0750, true) &&
                    !is_dir($uploadDirectory)
                ) {
                    $errors[] = 'Unable to prepare resume storage. Please check folder permissions.';
                } else {
                    $filename = bin2hex(random_bytes(16)) . '.pdf';

                    $newResumePath = 'uploads/resumes/' . $filename;
                    $newResumeAbsolutePath =
                        $uploadDirectory . '/' . $filename;

                    if (
                        !move_uploaded_file(
                            $file['tmp_name'],
                            $newResumeAbsolutePath
                        )
                    ) {
                        $errors[] = 'Unable to save the resume. Please try again.';

                        $newResumePath = null;
                        $newResumeAbsolutePath = null;
                    }
                }
            }
        }

        // Save profile if there are no validation errors
        if (!$errors) {
            $resumePath = $newResumePath ?? $profile['resume_path'];

            try {
                if (!empty($profile['id'])) {
                    // Update existing profile
                    $stmt = $pdo->prepare(
                        "UPDATE student_profiles
                         SET education = ?,
                             skills = ?,
                             phone = ?,
                             resume_path = ?
                         WHERE user_id = ?"
                    );

                    $stmt->execute([
                        $education,
                        $skills,
                        $phone,
                        $resumePath,
                        $userId
                    ]);
                } else {
                    // Create a new profile
                    $stmt = $pdo->prepare(
                        "INSERT INTO student_profiles
                            (user_id, education, skills, phone, resume_path)
                         VALUES (?, ?, ?, ?, ?)"
                    );

                    $stmt->execute([
                        $userId,
                        $education,
                        $skills,
                        $phone,
                        $resumePath
                    ]);
                }

                // Delete the old resume only after saving successfully
                if (
                    $newResumePath &&
                    !empty($profile['resume_path']) &&
                    strpos(
                        $profile['resume_path'],
                        'uploads/resumes/'
                    ) === 0 &&
                    basename($profile['resume_path']) ===
                        substr(
                            $profile['resume_path'],
                            strlen('uploads/resumes/')
                        )
                ) {
                    $oldFile = __DIR__ . '/' . $profile['resume_path'];

                    if (is_file($oldFile)) {
                        @unlink($oldFile);
                    }
                }

                // Set success message and redirect
                $_SESSION['profile_success'] =
                    'Your profile has been saved successfully!';

                header('Location: student-profile.php');
                exit;

            } catch (PDOException $exception) {
                // Remove newly uploaded file if database saving fails
                if (
                    $newResumeAbsolutePath &&
                    is_file($newResumeAbsolutePath)
                ) {
                    @unlink($newResumeAbsolutePath);
                }

                error_log($exception->getMessage());

                $errors[] =
                    'Unable to save your profile right now. Please try again.';
            }
        } elseif (
            $newResumeAbsolutePath &&
            is_file($newResumeAbsolutePath)
        ) {
            // Remove uploaded file if validation fails
            @unlink($newResumeAbsolutePath);
        }
    }
}

// Refresh profile data
$stmt = $pdo->prepare(
    "SELECT id, education, skills, phone, resume_path
     FROM student_profiles
     WHERE user_id = ?
     LIMIT 1"
);
$stmt->execute([$userId]);

$savedProfile = $stmt->fetch();

if ($savedProfile) {
    $profile = $savedProfile;

    // Keep submitted values if validation fails
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$errors) {
        $education = $profile['education'] ?? '';
        $skills = $profile['skills'] ?? '';
        $phone = $profile['phone'] ?? '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | HireTrack</title>

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
    <div class="container nav-content">
        <a href="index.php" class="logo">HireTrack.</a>

        <nav class="nav-links">
            <a href="student-dashboard.php">Dashboard</a>
            <a href="jobs.php">Find Jobs</a>
            <a href="logout.php" class="btn btn-outline">Logout</a>
        </nav>
    </div>
</header>

<main class="container profile-page">

    <a href="student-dashboard.php" class="back-link">
        &larr; Back to Dashboard
    </a>

    <section class="profile-heading">
        <span class="eyebrow">CAREER PROFILE</span>

        <h1>My Profile</h1>

        <p>
            Share your education, skills, and contact information
            to help employers learn more about you.
        </p>
    </section>

    <?php if ($success): ?>
        <div class="profile-message profile-success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="profile-message profile-error">
            <strong>Please correct the following:</strong>

            <ul>
                <?php foreach ($errors as $errorMessage): ?>
                    <li><?= e($errorMessage) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="profile-form-card">

        <form
            action="student-profile.php"
            method="POST"
            enctype="multipart/form-data"
            class="student-profile-form"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <div class="profile-field">
                <label for="full_name">Full Name</label>

                <input
                    type="text"
                    id="full_name"
                    value="<?= e($_SESSION['full_name'] ?? '') ?>"
                    disabled
                >

                <small>
                    Your name comes from your registered account.
                </small>
            </div>

            <div class="profile-field">
                <label for="education">Education</label>

                <input
                    type="text"
                    id="education"
                    name="education"
                    maxlength="150"
                    value="<?= e($education) ?>"
                    placeholder="e.g. BCA, 4th Semester"
                >
            </div>

            <div class="profile-field">
                <label for="phone">Phone Number</label>

                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    maxlength="20"
                    value="<?= e($phone) ?>"
                    placeholder="Enter your contact number"
                >
            </div>

            <div class="profile-field">
                <label for="skills">Skills</label>

                <textarea
                    id="skills"
                    name="skills"
                    rows="5"
                    maxlength="20000"
                    placeholder="e.g. HTML, CSS, JavaScript, PHP, MySQL"
                ><?= e($skills) ?></textarea>

                <small>
                    Separate your skills with commas for readability.
                </small>
            </div>

            <div class="profile-field">
                <label for="resume">Upload Resume (PDF only)</label>

                <input
                    type="hidden"
                    name="MAX_FILE_SIZE"
                    value="5242880"
                >

                <input
                    type="file"
                    id="resume"
                    name="resume"
                    accept=".pdf,application/pdf"
                >

                <small>Maximum file size: 5 MB.</small>

                <?php if (!empty($profile['resume_path'])): ?>
                    <p class="resume-current">
                        <strong>Resume uploaded:</strong>

                        <a href="download-resume.php">
                            View or download resume
                        </a>
                    </p>

                    <small>
                        Uploading a new PDF will replace your existing resume.
                    </small>
                <?php else: ?>
                    <small>
                        You have not uploaded a resume yet.
                    </small>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">
                Save Profile
            </button>

        </form>

    </section>

</main>

<footer>
    <div class="container">
        &copy; <?= date('Y') ?> HireTrack.
        Connecting talent with opportunity.
    </div>
</footer>

</body>
</html>
