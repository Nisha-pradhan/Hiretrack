
<?php
session_start();

require_once __DIR__ . '/config/database.php';

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Create a CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(404);
    exit('Invalid job ID. Please return to the job listings.');
}

// Fetch the job details
$sql = "
    SELECT
        j.id,
        j.title,
        j.description,
        j.location,
        j.job_type,
        j.salary,
        j.deadline,
        j.created_at,
        c.company_name,
        c.description AS company_description,
        c.website,
        cat.name AS category_name
    FROM jobs j
    INNER JOIN companies c ON j.company_id = c.id
    LEFT JOIN categories cat ON j.category_id = cat.id
    WHERE j.id = :id
      AND j.status = 'approved'
      AND (j.deadline IS NULL OR j.deadline >= CURDATE())
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id]);
$job = $stmt->fetch();

if (!$job) {
    http_response_code(404);
}

$error = '';

// Handle application submission
if (
    $job &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'apply'
) {
    if (
        !isset($_SESSION['user_id']) ||
        ($_SESSION['role'] ?? '') !== 'student'
    ) {
        header('Location: login.php');
        exit;
    }

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = 'Your session validation failed. Please refresh the page and try again.';
    } else {
        $coverLetter = trim($_POST['cover_letter'] ?? '');

        if (strlen($coverLetter) > 20000) {
            $error = 'Your cover letter is too long. Please keep it under 20,000 characters.';
        } else {
            // Check whether this student has already applied
            $stmt = $pdo->prepare(
                "SELECT id, status
                 FROM applications
                 WHERE student_id = ? AND job_id = ?
                 LIMIT 1"
            );

            $stmt->execute([$_SESSION['user_id'], $job['id']]);
            $existingApplication = $stmt->fetch();

            if ($existingApplication) {
                $error = 'You have already applied for this job.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO applications
                            (student_id, job_id, cover_letter, status)
                         VALUES (?, ?, ?, 'pending')"
                    );

                    $stmt->execute([
                        $_SESSION['user_id'],
                        $job['id'],
                        $coverLetter !== '' ? $coverLetter : null
                    ]);

                    $_SESSION['flash_success'] =
                        'Your application has been submitted successfully!';

                    header('Location: job-details.php?id=' . $job['id']);
                    exit;

                } catch (PDOException $exception) {
                    // The unique key also protects against duplicate applications
                    if ($exception->getCode() === '23000') {
                        $error = 'You have already applied for this job.';
                    } else {
                        error_log($exception->getMessage());
                        $error = 'Something went wrong while submitting your application. Please try again.';
                    }
                }
            }
        }
    }
}

// Get any success message after redirect
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

// Check whether the current student has applied
$myApplication = null;

if (
    $job &&
    isset($_SESSION['user_id']) &&
    ($_SESSION['role'] ?? '') === 'student'
) {
    $stmt = $pdo->prepare(
        "SELECT status, applied_at
         FROM applications
         WHERE student_id = ? AND job_id = ?
         LIMIT 1"
    );

    $stmt->execute([$_SESSION['user_id'], $job['id']]);
    $myApplication = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= $job ? e($job['title']) . ' | HireTrack' : 'Job Not Found | HireTrack' ?>
    </title>

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
    <div class="container nav-content">
        <a href="index.php" class="logo">HireTrack.</a>

        <nav class="nav-links">
            <a href="index.php">Home</a>
            <a href="jobs.php">Find Jobs</a>

            <?php if (isset($_SESSION['user_id'])): ?>

                <?php if (($_SESSION['role'] ?? '') === 'student'): ?>
                    <a href="student-dashboard.php">Dashboard</a>
                <?php elseif (($_SESSION['role'] ?? '') === 'employer'): ?>
                    <a href="employer-dashboard.php">Dashboard</a>
                <?php endif; ?>

                <a href="logout.php">Logout</a>

            <?php else: ?>

                <a href="login.php">Login</a>
                <a href="register.php" class="btn btn-primary">Get Started</a>

            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container details-page">

    <?php if ($job): ?>

        <a href="jobs.php" class="back-link">
            &larr; Back to jobs
        </a>

        <section class="details-card">

            <span class="eyebrow">JOB OPPORTUNITY</span>

            <div class="details-heading">
                <div>
                    <h1><?= e($job['title']) ?></h1>

                    <p class="company">
                        <?= e($job['company_name']) ?>
                    </p>
                </div>

                <span class="tag">
                    <?= e($job['job_type']) ?>
                </span>
            </div>

            <div class="details-meta">
                <p>
                    <strong>Location</strong><br>
                    <?= e($job['location'] ?: 'Not specified') ?>
                </p>

                <p>
                    <strong>Salary</strong><br>
                    <?= e($job['salary'] ?: 'Not specified') ?>
                </p>

                <p>
                    <strong>Category</strong><br>
                    <?= e($job['category_name'] ?: 'Uncategorized') ?>
                </p>

                <p>
                    <strong>Application deadline</strong><br>
                    <?= e($job['deadline'] ?: 'Not specified') ?>
                </p>
            </div>

            <div class="details-description">
                <h2>Job Description</h2>
                <p><?= nl2br(e($job['description'])) ?></p>
            </div>

            <div class="details-description">
                <h2>About the Company</h2>

                <p>
                    <?= nl2br(e(
                        $job['company_description']
                        ?: 'No company description provided.'
                    )) ?>
                </p>

                <?php if (!empty($job['website'])): ?>
                    <p>
                        Company website:
                        <a
                            href="<?= e($job['website']) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Visit website
                        </a>
                    </p>
                <?php endif; ?>
            </div>

            <div class="apply-section">

                <h2>Interested in this opportunity?</h2>

                <?php if ($success): ?>
                    <div class="apply-message apply-success">
                        <?= e($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="apply-message apply-error">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (($_SESSION['role'] ?? '') === 'student' && $myApplication): ?>

                    <div class="existing-application">
                        <p>You have already applied for this job.</p>

                        <p>
                            Application status:
                            <span class="job-status status-<?= e($myApplication['status']) ?>">
                                <?= e(ucfirst($myApplication['status'])) ?>
                            </span>
                        </p>

                        <a href="my-applications.php" class="btn btn-primary">
                            View My Applications
                        </a>
                    </div>

                <?php elseif (($_SESSION['role'] ?? '') === 'student'): ?>

                    <p>Submit your application to be considered for this position.</p>

                    <form
                        method="POST"
                        action="job-details.php?id=<?= (int)$job['id'] ?>"
                        class="job-application-form"
                    >
                        <input type="hidden" name="action" value="apply">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['csrf_token']) ?>"
                        >

                        <label for="cover_letter">Cover Letter (Optional)</label>

                        <textarea
                            id="cover_letter"
                            name="cover_letter"
                            rows="6"
                            maxlength="20000"
                            placeholder="Introduce yourself and explain why you're a good fit for this position..."
                        ><?= e($_POST['cover_letter'] ?? '') ?></textarea>

                        <button type="submit" class="btn btn-primary">
                            Submit Application
                        </button>
                    </form>

                <?php elseif (!isset($_SESSION['user_id'])): ?>

                    <p>Sign in to your student account to apply.</p>

                    <a href="login.php" class="btn btn-primary">
                        Login to Apply
                    </a>

                    <p class="apply-register-text">
                        Don't have an account?
                        <a href="register.php">Register as a student</a>
                    </p>

                <?php else: ?>

                    <p>
                        You are logged in as an employer or administrator.
                        Student accounts are required to apply for jobs.
                    </p>

                <?php endif; ?>

            </div>

        </section>

    <?php else: ?>

        <section class="empty-state">
            <h2>Job not found</h2>
            <p>This job may have expired or is no longer available.</p>

            <a href="jobs.php" class="btn btn-primary">
                Browse Jobs
            </a>
        </section>

    <?php endif; ?>

</main>

<footer>
    <div class="container">
        &copy; <?= date('Y') ?> HireTrack.
        Connecting talent with opportunity.
    </div>
</footer>

</body>
</html>
