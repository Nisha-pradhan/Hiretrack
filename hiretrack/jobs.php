
<?php
session_start();

require_once __DIR__ . '/config/database.php';

// Escape output safely
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

// Read search and filter values
$search = trim($_GET['search'] ?? '');
$jobType = $_GET['type'] ?? '';

$allowedTypes = ['Internship', 'Full-time', 'Part-time'];

if (!in_array($jobType, $allowedTypes, true)) {
    $jobType = '';
}

// Handle Save Job submission
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'save_job'
) {
    // Only students can save jobs
    if (
        !isset($_SESSION['user_id']) ||
        ($_SESSION['role'] ?? '') !== 'student'
    ) {
        header('Location: login.php');
        exit;
    }

    $token = $_POST['csrf_token'] ?? '';

    $jobId = filter_var(
        $_POST['job_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    // Validate CSRF token
    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $_SESSION['jobs_message'] =
            'Session validation failed. Please refresh and try again.';
        $_SESSION['jobs_message_type'] = 'error';
    } elseif (!$jobId || $jobId < 1) {
        $_SESSION['jobs_message'] = 'Invalid job selected.';
        $_SESSION['jobs_message_type'] = 'error';
    } else {
        try {
            // Verify that the job is approved and not expired
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM jobs
                 WHERE id = ?
                   AND status = 'approved'
                   AND (deadline IS NULL OR deadline >= CURDATE())
                 LIMIT 1"
            );

            $stmt->execute([$jobId]);

            if (!$stmt->fetch()) {
                $_SESSION['jobs_message'] =
                    'This job is no longer available.';
                $_SESSION['jobs_message_type'] = 'error';
            } else {
                // Save job without creating duplicates
                $stmt = $pdo->prepare(
                    "INSERT IGNORE INTO saved_jobs
                        (student_id, job_id)
                     VALUES (?, ?)"
                );

                $stmt->execute([
                    (int) $_SESSION['user_id'],
                    $jobId
                ]);

                if ($stmt->rowCount() > 0) {
                    $_SESSION['jobs_message'] =
                        'Job saved successfully!';
                    $_SESSION['jobs_message_type'] = 'success';
                } else {
                    $_SESSION['jobs_message'] =
                        'You have already saved this job.';
                    $_SESSION['jobs_message_type'] = 'success';
                }
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());

            $_SESSION['jobs_message'] =
                'Unable to save this job right now. Please try again.';
            $_SESSION['jobs_message_type'] = 'error';
        }
    }

    // Redirect back while preserving search and filters
    $redirect = 'jobs.php';

    $queryParameters = [];

    if ($search !== '') {
        $queryParameters['search'] = $search;
    }

    if ($jobType !== '') {
        $queryParameters['type'] = $jobType;
    }

    // Use the submitted form's return URL parameters
    $returnSearch = trim($_POST['return_search'] ?? '');
    $returnType = $_POST['return_type'] ?? '';

    if (!in_array($returnType, $allowedTypes, true)) {
        $returnType = '';
    }

    $queryParameters = [];

    if ($returnSearch !== '') {
        $queryParameters['search'] = $returnSearch;
    }

    if ($returnType !== '') {
        $queryParameters['type'] = $returnType;
    }

    if ($queryParameters) {
        $redirect .= '?' . http_build_query($queryParameters);
    }

    header('Location: ' . $redirect);
    exit;
}

// Retrieve flash message
$message = $_SESSION['jobs_message'] ?? '';
$messageType = $_SESSION['jobs_message_type'] ?? 'success';

unset(
    $_SESSION['jobs_message'],
    $_SESSION['jobs_message_type']
);

// Build the job listings query
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
        cat.name AS category_name
    FROM jobs j
    INNER JOIN companies c ON j.company_id = c.id
    LEFT JOIN categories cat ON j.category_id = cat.id
    WHERE j.status = 'approved'
      AND (j.deadline IS NULL OR j.deadline >= CURDATE())
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            j.title LIKE :title
            OR j.description LIKE :description
            OR j.location LIKE :location
            OR c.company_name LIKE :company
        )
    ";

    $keyword = '%' . $search . '%';

    $params['title'] = $keyword;
    $params['description'] = $keyword;
    $params['location'] = $keyword;
    $params['company'] = $keyword;
}

if ($jobType !== '') {
    $sql .= " AND j.job_type = :job_type";
    $params['job_type'] = $jobType;
}

$sql .= " ORDER BY j.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$jobs = $stmt->fetchAll();

// Retrieve saved job IDs for the logged-in student
$savedJobIds = [];

if (
    isset($_SESSION['user_id']) &&
    ($_SESSION['role'] ?? '') === 'student'
) {
    $stmt = $pdo->prepare(
        "SELECT job_id
         FROM saved_jobs
         WHERE student_id = ?"
    );

    $stmt->execute([(int) $_SESSION['user_id']]);

    $savedJobIds = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Find Jobs | HireTrack</title>

    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
    <div class="container nav-content">

        <a href="index.php" class="logo">HireTrack.</a>

        <nav class="nav-links">
            <a href="index.php">Home</a>
            <a href="jobs.php">Find Jobs</a>

            <?php if (
                isset($_SESSION['user_id']) &&
                ($_SESSION['role'] ?? '') === 'student'
            ): ?>

                <a href="student-dashboard.php">Dashboard</a>
                <a href="saved-jobs.php">Saved Jobs</a>

            <?php elseif (
                isset($_SESSION['user_id']) &&
                ($_SESSION['role'] ?? '') === 'employer'
            ): ?>

                <a href="employer-dashboard.php">Dashboard</a>

            <?php elseif (
                isset($_SESSION['user_id']) &&
                ($_SESSION['role'] ?? '') === 'admin'
            ): ?>

                <a href="index.php">Home</a>

            <?php else: ?>

                <a href="login.php">Login</a>

                <a href="register.php" class="btn btn-primary">
                    Get Started
                </a>

            <?php endif; ?>

        </nav>
    </div>
</header>

<main class="container jobs-page">

    <section class="jobs-intro">

        <span class="eyebrow">YOUR NEXT OPPORTUNITY</span>

        <h1>Find your perfect role.</h1>

        <p>
            Explore internships and jobs that match your skills
            and career goals.
        </p>

    </section>

    <?php if ($message): ?>

        <div class="profile-message <?= $messageType === 'error'
            ? 'profile-error'
            : 'profile-success' ?>">
            <?= e($message) ?>
        </div>

    <?php endif; ?>

    <section class="jobs-search">

        <form action="jobs.php" method="GET" class="filter-form">

            <input
                type="text"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Search job title, company or keyword"
                aria-label="Search jobs"
            >

            <select name="type" aria-label="Filter by job type">

                <option value="">All job types</option>

                <?php foreach ($allowedTypes as $type): ?>

                    <option
                        value="<?= e($type) ?>"
                        <?= $jobType === $type ? 'selected' : '' ?>
                    >
                        <?= e($type) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button type="submit" class="btn btn-primary">
                Search
            </button>

            <a href="jobs.php" class="btn btn-outline">
                Reset
            </a>

        </form>

    </section>

    <section class="section job-results">

        <div class="section-heading">

            <div>
                <h2>Available opportunities</h2>

                <p>
                    <?= count($jobs) ?>
                    <?= count($jobs) === 1 ? 'job' : 'jobs' ?> found
                </p>
            </div>

            <?php if (
                isset($_SESSION['user_id']) &&
                ($_SESSION['role'] ?? '') === 'student'
            ): ?>

                <a href="saved-jobs.php" class="btn btn-outline">
                    &#9825; My Saved Jobs
                </a>

            <?php endif; ?>

        </div>

        <?php if (count($jobs) > 0): ?>

            <div class="job-grid">

                <?php foreach ($jobs as $job): ?>

                    <?php
                    $isSaved = in_array(
                        (int) $job['id'],
                        $savedJobIds,
                        true
                    );
                    ?>

                    <article class="job-card">

                        <div class="job-icon">
                            <?= e(
                                strtoupper(
                                    substr($job['company_name'], 0, 2)
                                )
                            ) ?>
                        </div>

                        <h3><?= e($job['title']) ?></h3>

                        <p class="company">
                            <?= e($job['company_name']) ?>
                        </p>

                        <div class="tags">

                            <span class="tag">
                                <?= e($job['job_type']) ?>
                            </span>

                            <?php if ($job['category_name']): ?>

                                <span class="tag">
                                    <?= e($job['category_name']) ?>
                                </span>

                            <?php endif; ?>

                        </div>

                        <p class="job-description">
                            <?= e(
                                mb_strimwidth(
                                    $job['description'],
                                    0,
                                    130,
                                    '...'
                                )
                            ) ?>
                        </p>

                        <div class="job-details">

                            <p>
                                <strong>Location:</strong>
                                <?= e(
                                    $job['location'] ?: 'Not specified'
                                ) ?>
                            </p>

                            <p>
                                <strong>Salary:</strong>
                                <?= e(
                                    $job['salary'] ?: 'Not specified'
                                ) ?>
                            </p>

                            <p>
                                <strong>Deadline:</strong>
                                <?= e(
                                    $job['deadline'] ?: 'Not specified'
                                ) ?>
                            </p>

                        </div>

                        <div class="job-bottom">

                            <span>
                                Posted
                                <?= e(
                                    date(
                                        'd M Y',
                                        strtotime($job['created_at'])
                                    )
                                ) ?>
                            </span>

                            <a
                                class="btn btn-primary"
                                href="job-details.php?id=<?= (int) $job['id'] ?>"
                            >
                                View Details
                            </a>

                        </div>

                        <?php if (
                            isset($_SESSION['user_id']) &&
                            ($_SESSION['role'] ?? '') === 'student'
                        ): ?>

                            <form
                                action="jobs.php"
                                method="POST"
                                class="save-job-form"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="save_job"
                                >

                                <input
                                    type="hidden"
                                    name="job_id"
                                    value="<?= (int) $job['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="return_search"
                                    value="<?= e($search) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="return_type"
                                    value="<?= e($jobType) ?>"
                                >

                                <?php if ($isSaved): ?>

                                    <a
                                        href="saved-jobs.php"
                                        class="btn-save-job"
                                    >
                                        &#10003; Saved
                                        — View Saved Jobs
                                    </a>

                                <?php else: ?>

                                    <button
                                        type="submit"
                                        class="btn-save-job"
                                    >
                                        &#9825; Save Job
                                    </button>

                                <?php endif; ?>

                            </form>

                        <?php elseif (!isset($_SESSION['user_id'])): ?>

                            <p class="save-job-login">
                                <a href="login.php">
                                    Log in as a student
                                </a>
                                to save this job.
                            </p>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <h3>No jobs found yet</h3>

                <p>
                    Try a different keyword or job type.
                    Approved job listings will appear here.
                </p>

                <a href="jobs.php" class="btn btn-primary">
                    View All Jobs
                </a>

            </div>

        <?php endif; ?>

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
