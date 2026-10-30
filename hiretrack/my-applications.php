
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

// Fetch this student's applications
$sql = "
    SELECT
        a.id AS application_id,
        a.status AS application_status,
        a.applied_at,
        j.id AS job_id,
        j.title AS job_title,
        j.location,
        j.job_type,
        j.status AS job_status,
        j.deadline,
        c.company_name
    FROM applications a
    INNER JOIN jobs j ON a.job_id = j.id
    INNER JOIN companies c ON j.company_id = c.id
    WHERE a.student_id = :student_id
    ORDER BY a.applied_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'student_id' => $_SESSION['user_id']
]);

$applications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Applications | HireTrack</title>

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

<main class="container my-applications-page">

    <a href="student-dashboard.php" class="back-link">
        &larr; Back to Dashboard
    </a>

    <section class="applications-heading">
        <span class="eyebrow">CAREER PROGRESS</span>

        <h1>My Applications</h1>

        <p>
            Keep track of the jobs you've applied for and check
            the latest status of each application.
        </p>

        <div class="applications-count">
            Total Applications:
            <strong><?= count($applications) ?></strong>
        </div>
    </section>

    <?php if (count($applications) > 0): ?>

        <section class="applications-list">

            <?php foreach ($applications as $application): ?>

                <article class="application-card">

                    <div class="application-card-top">

                        <div class="application-job-info">
                            <h2>
                                <?= e($application['job_title']) ?>
                            </h2>

                            <p class="application-company">
                                <?= e($application['company_name']) ?>
                            </p>
                        </div>

                        <span class="job-status status-<?= e($application['application_status']) ?>">
                            <?= e(ucfirst($application['application_status'])) ?>
                        </span>

                    </div>

                    <div class="application-meta">

                        <p>
                            <strong>Job Type</strong>
                            <span><?= e($application['job_type']) ?></span>
                        </p>

                        <p>
                            <strong>Location</strong>
                            <span>
                                <?= e($application['location'] ?: 'Not specified') ?>
                            </span>
                        </p>

                        <p>
                            <strong>Applied On</strong>
                            <span>
                                <?= e(date(
                                    'M d, Y',
                                    strtotime($application['applied_at'])
                                )) ?>
                            </span>
                        </p>

                        <p>
                            <strong>Deadline</strong>
                            <span>
                                <?= e($application['deadline'] ?: 'Not specified') ?>
                            </span>
                        </p>

                    </div>

                    <div class="application-card-footer">

                        <span class="application-id">
                            Application #<?= (int)$application['application_id'] ?>
                        </span>

                        <?php if (
                            $application['job_status'] === 'approved' &&
                            (
                                empty($application['deadline']) ||
                                $application['deadline'] >= date('Y-m-d')
                            )
                        ): ?>

                            <a
                                href="job-details.php?id=<?= (int)$application['job_id'] ?>"
                                class="btn btn-primary"
                            >
                                View Job
                            </a>

                        <?php else: ?>

                            <span class="job-unavailable">
                                Listing no longer available
                            </span>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        </section>

    <?php else: ?>

        <section class="applications-empty">

            <h2>No applications yet</h2>

            <p>
                You haven't applied for any jobs yet. Explore available
                opportunities and take the next step in your career.
            </p>

            <a href="jobs.php" class="btn btn-primary">
                Explore Jobs
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
