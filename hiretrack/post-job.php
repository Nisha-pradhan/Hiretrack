
<?php
session_start();

require_once __DIR__ . '/config/database.php';

// Only employers can post jobs
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];
$success = "";

// Get the employer's company
$stmt = $pdo->prepare(
    "SELECT id, company_name FROM companies WHERE user_id = ?"
);
$stmt->execute([$userId]);
$company = $stmt->fetch();

if (!$company) {
    exit("Company profile not found. Please complete your company profile.");
}

// Get categories for the dropdown
$stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Process the form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $jobType = $_POST['job_type'] ?? 'Internship';
    $salary = trim($_POST['salary'] ?? '');
    $deadline = $_POST['deadline'] ?? '';

    // Validate required fields
    if ($title === '') {
        $errors[] = "Job title is required.";
    }

    if ($description === '') {
        $errors[] = "Job description is required.";
    }

    if (!in_array($jobType, ['Internship', 'Full-time', 'Part-time'], true)) {
        $errors[] = "Please select a valid job type.";
    }

    // Validate category
    if ($categoryId !== '') {
        $stmt = $pdo->prepare(
            "SELECT id FROM categories WHERE id = ?"
        );
        $stmt->execute([$categoryId]);

        if (!$stmt->fetch()) {
            $errors[] = "Please select a valid category.";
        }
    } else {
        $categoryId = null;
    }

    // Validate deadline
    if ($deadline !== '') {
        $date = DateTime::createFromFormat('!Y-m-d', $deadline);

        if (!$date || $date->format('Y-m-d') !== $deadline) {
            $errors[] = "Please enter a valid deadline.";
        } elseif ($deadline < date('Y-m-d')) {
            $errors[] = "The deadline cannot be in the past.";
        }
    } else {
        $deadline = null;
    }

    // Insert the job if validation passes
    if (empty($errors)) {

        $stmt = $pdo->prepare(
            "INSERT INTO jobs
            (company_id, category_id, title, description,
             location, job_type, salary, deadline, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );

        $stmt->execute([
            $company['id'],
            $categoryId,
            $title,
            $description,
            $location ?: null,
            $jobType,
            $salary ?: null,
            $deadline
        ]);

        $success = "Your job has been submitted for admin approval.";

        // Clear submitted form values
        $_POST = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Post a Job | HireTrack</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header class="post-job-header">
    <a href="index.php" class="logo">HireTrack.</a>
        
    <a href="employer-dashboard.php" class="back-dashboard">
        Back to Dashboard
    </a>
</header>

<main class="post-job-container">

    <div class="post-job-heading">
        <span class="post-job-label">EMPLOYER PORTAL</span>

        <h1>Post a New Job</h1>

        <p>
            Share an opportunity and connect with talented candidates.
        </p>

        <p class="company-name">
            Company: <?= htmlspecialchars($company['company_name']) ?>
        </p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="job-alert job-error">
            <strong>Please correct the following:</strong>

            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="job-alert job-success">
            <?= htmlspecialchars($success) ?>
            <br><br>
            Your listing will appear publicly after admin approval.
            <br><br>
            <a href="employer-dashboard.php">Return to Dashboard</a>
        </div>
    <?php endif; ?>

    <form method="POST" action="post-job.php" class="post-job-form">

        <div class="form-field">
            <label for="title">Job Title *</label>

            <input
                type="text"
                id="title"
                name="title"
                placeholder="e.g. Junior Web Developer Intern"
                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                maxlength="150"
                required
            >
        </div>

        <div class="form-field">
            <label for="category_id">Job Category</label>

            <select id="category_id" name="category_id">
                <option value="">Select a category</option>

                <?php foreach ($categories as $category): ?>
                    <option
                        value="<?= $category['id'] ?>"
                        <?= (string)($_POST['category_id'] ?? '') ===
                            (string)$category['id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field">
            <label for="description">Job Description *</label>

            <textarea
                id="description"
                name="description"
                rows="7"
                placeholder="Describe the responsibilities, qualifications, and skills required..."
                required
            ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">

            <div class="form-field">
                <label for="job_type">Employment Type *</label>

                <select id="job_type" name="job_type" required>
                    <?php
                    $selectedType = $_POST['job_type'] ?? 'Internship';
                    $types = ['Internship', 'Full-time', 'Part-time'];
                    ?>

                    <?php foreach ($types as $type): ?>
                        <option
                            value="<?= $type ?>"
                            <?= $selectedType === $type ? 'selected' : '' ?>
                        >
                            <?= $type ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label for="location">Location</label>

                <input
                    type="text"
                    id="location"
                    name="location"
                    placeholder="e.g. Kathmandu or Remote"
                    value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                    maxlength="150"
                >
            </div>

        </div>

        <div class="form-row">

            <div class="form-field">
                <label for="salary">Salary / Stipend</label>

                <input
                    type="text"
                    id="salary"
                    name="salary"
                    placeholder="e.g. NPR 10,000/month"
                    value="<?= htmlspecialchars($_POST['salary'] ?? '') ?>"
                    maxlength="100"
                >
            </div>

            <div class="form-field">
                <label for="deadline">Application Deadline</label>

                <input
                    type="date"
                    id="deadline"
                    name="deadline"
                    min="<?= date('Y-m-d') ?>"
                    value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>"
                >
            </div>

        </div>

        <button type="submit" class="post-job-button">
            Submit for Approval
        </button>

        <p class="form-note">
            Your job will remain pending until an administrator approves it.
        </p>

    </form>

</main>

</body>
</html>
