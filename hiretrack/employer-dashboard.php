
<?php
session_start();

require_once __DIR__ . '/config/database.php';

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'employer'
) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT company_name
     FROM companies
     WHERE user_id = :user_id
     LIMIT 1"
);

$stmt->execute(['user_id' => $_SESSION['user_id']]);
$company = $stmt->fetch();

function e($value) {
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
    <title>Employer Dashboard | HireTrack</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
    <div class="container nav-content">
        <a href="index.php" class="logo">HireTrack.</a>

        <nav class="nav-links">
            <a href="jobs.php">Find Jobs</a>
            <a href="logout.php" class="btn btn-outline">Logout</a>
        </nav>
    </div>
</header>

<main class="container dashboard-page">
    <section class="dashboard-welcome">
        <span class="eyebrow">EMPLOYER DASHBOARD</span>

        <h1>
            Welcome, <?= e($_SESSION['full_name']) ?>!
        </h1>

        <p>
            Company:
            <strong><?= e($company['company_name'] ?? 'Your Company') ?></strong>
        </p>

        <p>
            Find promising candidates and manage your recruitment
            activities from one place.
        </p>

        <a href="jobs.php" class="btn btn-primary">
            Browse Job Listings
        </a>
    </section>

    <section class="dashboard-grid">
        <article class="dashboard-card">
            <h2>Post a Job</h2>
            <p>Create an internship or job listing for candidates.</p>
            <a href="post-job.php" class="btn-post-job">+ Post a Job</a>
            <span class="tag">Coming next</span>
        </article>

        <article class="dashboard-card">
            <h2>Manage Jobs</h2>
            <p>Review and manage your published job listings.</p>
            <a href="manage-jobs.php" class="btn-post-job">Manage Jobs</a>
            <span class="tag">Coming next</span>
        </article>

        <article class="dashboard-card">
            <h2>Applications</h2>
            <p>Review candidates who apply for your positions.</p>
            <a href="view-applications.php" class="btn-post-job">View Applications</a>
            <span class="tag">Coming next</span>
        </article>
    </section>
</main>

<footer>
    <div class="container">
        &copy; <?= date('Y') ?> HireTrack.
    </div>
</footer>

</body>
</html>
