
<?php
session_start();

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'student'
) {
    header('Location: login.php');
    exit;
}

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
    <title>Student Dashboard | HireTrack</title>
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
        <span class="eyebrow">STUDENT DASHBOARD</span>

        <h1>
            Welcome, <?= e($_SESSION['full_name'] ?? 'Student') ?>!
        </h1>

        <p>
            Your career journey starts here. Explore opportunities
            and get ready to apply.
        </p>

        <a href="jobs.php" class="btn btn-primary">
            Explore Jobs
        </a>
    </section>

    <section class="dashboard-grid">
    
    <article class="dashboard-card">
       <h2>My Profile</h2>
       <p>Build your profile and showcase your skills.</p>
    <a href="student-profile.php" class="btn btn-primary">Manage My Profile</a>
    </article>


        <article class="dashboard-card">
            <h2>Saved Jobs</h2>
            <p>Keep track of opportunities you want to revisit.</p>
             <a href="saved-jobs.php" class="btn btn-primary">View Saved Jobs</a>
           
        </article>

        <article class="dashboard-card">
            <h2>My Applications</h2>
            <p>Track the progress of your job applications.</p>

            <a href="my-applications.php" class="btn btn-primary">
                View My Applications
            </a>
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
