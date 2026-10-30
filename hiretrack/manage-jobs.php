
<?php
session_start();

require_once __DIR__ . '/config/database.php';

// Only logged-in employers can access this page
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'employer'
) {
    header("Location: login.php");
    exit;
}

// Create a CSRF token for secure form submissions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = $_SESSION['user_id'];
$message = "";
$error = "";

// Find the employer's company
$stmt = $pdo->prepare(
    "SELECT id, company_name
     FROM companies
     WHERE user_id = ?"
);
$stmt->execute([$userId]);
$company = $stmt->fetch();

if (!$company) {
    exit("Company profile not found.");
}

$companyId = $company['id'];

// Handle closing a job
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {

        $jobId = filter_var(
            $_POST['job_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        if (!$jobId || ($_POST['action'] ?? '') !== 'close') {
            $error = "Invalid job action.";
        } else {

            // Only close a job belonging to this employer
            // Pending or approved jobs can be closed
            $stmt = $pdo->prepare(
                "UPDATE jobs
                 SET status = 'closed'
                 WHERE id = ?
                 AND company_id = ?
                 AND status IN ('pending', 'approved')"
            );

            $stmt->execute([$jobId, $companyId]);

            if ($stmt->rowCount() > 0) {
                $message = "Job listing closed successfully.";
            } else {
                $error = "This job could not be closed. It may already be closed or rejected.";
            }
        }
    }
}

// Get all jobs belonging to this employer
$stmt = $pdo->prepare(
    "SELECT
        j.id,
        j.title,
        j.location,
        j.job_type,
        j.salary,
        j.deadline,
        j.status,
        j.created_at,
        c.name AS category_name
     FROM jobs j
     LEFT JOIN categories c ON j.category_id = c.id
     WHERE j.company_id = ?
     ORDER BY j.created_at DESC"
);

$stmt->execute([$companyId]);
$jobs = $stmt->fetchAll();

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Jobs | HireTrack</title>

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="manage-jobs-header">
    <a href="index.php" class="logo">HireTrack</a>

    <a href="employer-dashboard.php" class="manage-back-link">
        Back to Dashboard
    </a>
</header>

<main class="manage-jobs-container">

    <div class="manage-jobs-heading">
        <div>
            <p class="manage-eyebrow">EMPLOYER PORTAL</p>
            <h1>Manage Your Jobs</h1>
            <p>
                View and manage the opportunities posted by
                <?= e($company['company_name']) ?>.
            </p>
        </div>

        <a href="post-job.php" class="btn-post-job">
            + Post a Job
        </a>
    </div>

    <?php if ($message): ?>
        <div class="manage-alert manage-success">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="manage-alert manage-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="manage-jobs-summary">
        <div class="manage-summary-card">
            <span>Total Listings</span>
            <strong><?= count($jobs) ?></strong>
        </div>

        <div class="manage-summary-card">
            <span>Pending Approval</span>
            <strong>
                <?= count(array_filter($jobs, fn($job) =>
                    $job['status'] === 'pending'
                )) ?>
            </strong>
        </div>

        <div class="manage-summary-card">
            <span>Approved</span>
            <strong>
                <?= count(array_filter($jobs, fn($job) =>
                    $job['status'] === 'approved'
                )) ?>
            </strong>
        </div>
    </div>

    <section class="manage-jobs-list">

        <h2>Your Listings</h2>

        <?php if (empty($jobs)): ?>

            <div class="manage-empty">
                <h3>No jobs posted yet</h3>

                <p>
                    Your job listings will appear here after you
                    submit your first opportunity.
                </p>

                <a href="post-job.php" class="btn-post-job">
                    Create Your First Listing
                </a>
            </div>

        <?php else: ?>

            <?php foreach ($jobs as $job): ?>

                <article class="manage-job-card">

                    <div class="manage-job-main">

                        <div>
                            <h3><?= e($job['title']) ?></h3>

                            <p class="manage-job-company">
                                <?= e($company['company_name']) ?>
                            </p>
                        </div>

                        <span class="job-status status-<?= e($job['status']) ?>">
                            <?= e(ucfirst($job['status'])) ?>
                        </span>

                    </div>

                    <div class="manage-job-details">

                        <span>
                            <?= e($job['job_type']) ?>
                        </span>

                        <span>
                            <?= e($job['location'] ?: 'Location not specified') ?>
                        </span>

                        <span>
                            <?= e($job['category_name'] ?: 'Uncategorized') ?>
                        </span>

                        <span>
                            Salary: <?= e($job['salary'] ?: 'Not specified') ?>
                        </span>

                        <span>
                            Deadline:
                            <?= $job['deadline']
                                ? e(date('M j, Y', strtotime($job['deadline'])))
                                : 'Not specified' ?>
                        </span>

                        <span>
                            Posted:
                            <?= e(date('M j, Y', strtotime($job['created_at']))) ?>
                        </span>

                    </div>

                    <div class="manage-job-actions">

                        <a
                            href="job-details.php?id=<?= (int)$job['id'] ?>"
                            class="manage-view-link"
                        >
                            View Listing
                        </a>

                        <?php if (in_array(
                            $job['status'],
                            ['pending', 'approved'],
                            true
                        )): ?>

                            <form
                                method="POST"
                                action="manage-jobs.php"
                                onsubmit="return confirm('Are you sure you want to close this job listing?');"
                            >
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="job_id"
                                    value="<?= (int)$job['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="close"
                                >

                                <button
                                    type="submit"
                                    class="manage-close-button"
                                >
                                    Close Listing
                                </button>
                            </form>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
