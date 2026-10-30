
<?php
require_once __DIR__ . '/config/database.php';

$stmt = $pdo->query(
    "SELECT COUNT(*) AS total FROM categories"
);
$result = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireTrack | Find Your Next Opportunity</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
    <div class="container nav-content">
        <a href="index.php" class="logo">HireTrack.</a>

        <nav class="nav-links">
            <a href="index.php">Home</a>
            <a href="jobs.php">Find Jobs</a>
            <a href="login.php">Login</a>
            <a href="register.php" class="btn btn-primary">Get Started</a>
        </nav>
    </div>
</header>

<main>
    <section class="hero">
        <div class="container">
            <span class="eyebrow">YOUR CAREER STARTS HERE</span>

            <h1>Find the right opportunity to <span>grow.</span></h1>

            <p>
                Discover internships and jobs, showcase your skills, and take the next step toward your dream career.
            </p>

            <form class="search-box" action="jobs.php" method="GET">
                <input
                    type="text"
                    name="search"
                    placeholder="Job title, skill or keyword..."
                    aria-label="Search jobs"
                >

                <button class="btn btn-primary" type="submit">
                    Search Jobs
                </button>
            </form>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-heading">
                <div>
                    <h2>Explore opportunities</h2>
                    <p>Find a role that matches your ambitions.</p>
                </div>

                <a href="jobs.php">View all jobs &rarr;</a>
            </div>

            <div class="job-grid">
                <article class="job-card">
                    <div class="job-icon">WD</div>
                    <h3>Web Development Intern</h3>
                    <p class="company">Sample Tech Company</p>
                    <div class="tags">
                        <span class="tag">Internship</span>
                        <span class="tag">HTML / CSS / PHP</span>
                    </div>
                    <div class="job-bottom">
                        <span>Kathmandu / Remote</span>
                        <strong>Explore</strong>
                    </div>
                </article>

                <article class="job-card">
                    <div class="job-icon">UI</div>
                    <h3>UI/UX Design Intern</h3>
                    <p class="company">Creative Studio</p>
                    <div class="tags">
                        <span class="tag">Internship</span>
                        <span class="tag">Figma</span>
                    </div>
                    <div class="job-bottom">
                        <span>Remote</span>
                        <strong>Explore</strong>
                    </div>
                </article>

                <article class="job-card">
                    <div class="job-icon">SD</div>
                    <h3>Junior Software Developer</h3>
                    <p class="company">Digital Solutions</p>
                    <div class="tags">
                        <span class="tag">Full-time</span>
                        <span class="tag">JavaScript</span>
                    </div>
                    <div class="job-bottom">
                        <span>Kathmandu</span>
                        <strong>Explore</strong>
                    </div>
                </article>
            </div>

            <div style="margin-top: 55px;">
                <div class="cta">
                    <h2>Ready to take the next step?</h2>
                    <p>
                        Create your account and start exploring career opportunities.
                    </p>
                    <a href="register.php" class="btn">Create Free Account</a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer>
    <div class="container">
        &copy; <?= date('Y') ?> HireTrack. Built to connect talent with opportunity.
        <br>
        <?= (int) $result['total'] ?> career categories available.
    </div>
</footer>

</body>
</html>
