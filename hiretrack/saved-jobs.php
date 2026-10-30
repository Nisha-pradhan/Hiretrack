
<?php
session_start();

require_once __DIR__ . '/config/database.php';

// Allow only logged-in students
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'student'
) {
    header('Location: login.php');
    exit;
}

$studentId = (int) $_SESSION['user_id'];

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Remove a saved job
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['remove_saved_job'])) {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {
        http_response_code(403);
        exit('Invalid request. Please go back and try again.');
    }

    $jobId = filter_input(
        INPUT_POST,
        'job_id',
        FILTER_VALIDATE_INT
    );

    if ($jobId && $jobId > 0) {
        $delete = $pdo->prepare(
            'DELETE FROM saved_jobs
             WHERE student_id = :student_id
             AND job_id = :job_id'
        );

        $delete->execute([
            'student_id' => $studentId,
            'job_id' => $jobId
        ]);
    }

    header('Location: saved-jobs.php');
    exit;
}

// Get saved jobs
$sql = "
    SELECT
        j.id,
        j.title,
        j.description,
        j.location,
        j.job_type,
        j.salary,
        j.deadline,
        j.status,
        j.created_at,
        c.company_name,
        c.location AS company_location,
        cat.name AS category_name,
        sj.saved_at
    FROM saved_jobs sj
    INNER JOIN jobs j
        ON sj.job_id = j.id
    LEFT JOIN companies c
        ON j.company_id = c.id
    LEFT JOIN categories cat
        ON j.category_id = cat.id
    WHERE sj.student_id = :student_id
    ORDER BY sj.saved_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['student_id' => $studentId]);
$savedJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalSaved = count($savedJobs);

// Escape output before displaying it
function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Jobs | HireTrack</title>

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Keep the existing navigation structure unchanged -->
<header class="site-header">
    <div class="container navbar">
        <a href="index.php" class="logo">HireTrack.</a>

        <nav>
            <a href="student-dashboard.php">Dashboard</a>
            <a href="saved-jobs.php" class="active">Find Jobs</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>
</header>

<main class="saved-jobs-page">

    <!-- Page heading -->
    <section class="saved-jobs-hero">
        <div class="container">

            <a href="student-dashboard.php" class="saved-jobs-back">
                &larr; Back to Dashboard
            </a>

            <p class="saved-jobs-eyebrow">YOUR CAREER JOURNEY</p>

            <h1>Saved Jobs</h1>

            <p class="saved-jobs-description">
                Keep track of opportunities that interest you
                and come back when you are ready to apply.
            </p>

            <div class="saved-jobs-actions">
                <a href="jobs.php" class="btn btn-primary">
                    Browse More Jobs
                </a>
            </div>

        </div>
    </section>

    <!-- Saved job listings -->
    <section class="container saved-jobs-content">

        <div class="saved-jobs-section-heading">
            <div>
                <h2>Your Saved Opportunities</h2>
                <p>All the jobs you have bookmarked in one place.</p>
            </div>

            <span class="saved-jobs-count">
                <?= $totalSaved ?>
                <?= $totalSaved === 1 ? 'Job' : 'Jobs' ?>
            </span>
        </div>

        <?php if ($totalSaved > 0): ?>

            <div class="saved-jobs-grid">

                <?php foreach ($savedJobs as $job): ?>

                    <article class="saved-job-card">

                        <div class="saved-job-card-top">

                            <div class="saved-job-company-icon">
                                <?= e(
                                    strtoupper(
                                        substr(
                                            trim($job['company_name'] ?: 'H'),
                                            0,
                                            1
                                        )
                                    )
                                ) ?>
                            </div>

                            <div class="saved-job-title-area">
                                <h3><?= e($job['title']) ?></h3>

                                <p class="saved-job-company">
                                    <?= e(
                                        $job['company_name']
                                        ?: 'Company not specified'
                                    ) ?>
                                </p>
                            </div>

                            <span class="saved-job-status">
                                Saved
                            </span>

                        </div>

                        <div class="saved-job-details">

                            <span>
                                <span class="saved-job-detail-icon">&#9906;</span>
                                <?= e(
                                    $job['location']
                                    ?: $job['company_location']
                                    ?: 'Location not specified'
                                ) ?>
                            </span>

                            <span>
                                <span class="saved-job-detail-icon">&#128188;</span>
                                <?= e($job['job_type'] ?: 'Not specified') ?>
                            </span>

                            <?php if (!empty($job['salary'])): ?>
                                <span>
                                    <span class="saved-job-detail-icon">&#8377;</span>
                                    <?= e($job['salary']) ?>
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($job['category_name'])): ?>
                                <span>
                                    <span class="saved-job-detail-icon">&#9670;</span>
                                    <?= e($job['category_name']) ?>
                                </span>
                            <?php endif; ?>

                        </div>

                        <?php if (!empty($job['description'])): ?>
                            <p class="saved-job-description">
                                <?= e(
                                    mb_strimwidth(
                                        trim(strip_tags($job['description'])),
                                        0,
                                        180,
                                        '...'
                                    )
                                ) ?>
                            </p>
                        <?php endif; ?>

                        <div class="saved-job-card-footer">

                            <div class="saved-job-date">
                                <?php if (!empty($job['deadline'])): ?>
                                    <span>Deadline:</span>
                                    <?= e(date(
                                        'M d, Y',
                                        strtotime($job['deadline'])
                                    )) ?>
                                <?php else: ?>
                                    <span>No deadline specified</span>
                                <?php endif; ?>
                            </div>

                            <div class="saved-job-card-actions">

                                <a
                                    href="job-details.php?id=<?= (int) $job['id'] ?>"
                                    class="saved-job-view-btn"
                                >
                                    View Details
                                </a>

                                <form
                                    method="POST"
                                    action="saved-jobs.php"
                                    class="saved-job-remove-form"
                                    onsubmit="return confirm('Remove this job from your saved jobs?');"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION['csrf_token']) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="job_id"
                                        value="<?= (int) $job['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="remove_saved_job"
                                        value="1"
                                        class="saved-job-remove-btn"
                                    >
                                        Remove
                                    </button>
                                </form>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="saved-jobs-empty">

                <div class="saved-jobs-empty-icon">
                    &#128204;
                </div>

                <h3>No Saved Jobs Yet</h3>

                <p>
                    You haven't saved any jobs yet. Browse available
                    opportunities and save the ones you like.
                </p>

                <a href="jobs.php" class="btn btn-primary">
                    Explore Jobs
                </a>

            </div>

        <?php endif; ?>

    </section>

</main>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> HireTrack. All rights reserved.</p>
    </div>
</footer>

</body>
</html>
