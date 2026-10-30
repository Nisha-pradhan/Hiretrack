
<?php
session_start();

require_once __DIR__ . '/config/database.php';

// Only employers can access this page
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'employer'
) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Create a CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

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

// Update an application's status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {

        $applicationId = filter_var(
            $_POST['application_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        $newStatus = $_POST['status'] ?? '';

        $allowedStatuses = [
            'pending',
            'shortlisted',
            'accepted',
            'rejected'
        ];

        if (
            !$applicationId ||
            !in_array($newStatus, $allowedStatuses, true)
        ) {
            $error = "Please select a valid application status.";
        } else {

            // Update only applications for this employer's jobs
            $stmt = $pdo->prepare(
                "UPDATE applications a
                 INNER JOIN jobs j ON a.job_id = j.id
                 SET a.status = ?
                 WHERE a.id = ?
                 AND j.company_id = ?
                 AND j.status = 'approved'"
            );

            $stmt->execute([
                $newStatus,
                $applicationId,
                $companyId
            ]);

            if ($stmt->rowCount() > 0) {
                $message = "Application status updated successfully.";
            } else {
                // Check whether this application belongs to the employer
                $stmt = $pdo->prepare(
                    "SELECT a.status
                     FROM applications a
                     INNER JOIN jobs j ON a.job_id = j.id
                     WHERE a.id = ?
                     AND j.company_id = ?"
                );
                $stmt->execute([$applicationId, $companyId]);
                $application = $stmt->fetch();

                if (!$application) {
                    $error = "Application not found for your company.";
                } elseif ($application['status'] === $newStatus) {
                    $message = "The application already has that status.";
                } else {
                    $error = "This application cannot be updated. Check whether its job is approved.";
                }
            }
        }
    }
}

// Retrieve applications for this employer's jobs
$stmt = $pdo->prepare(
    "SELECT
        a.id AS application_id,
        a.cover_letter,
        a.status AS application_status,
        a.applied_at,
        u.full_name AS student_name,
        u.email AS student_email,
        j.id AS job_id,
        j.title AS job_title
     FROM applications a
     INNER JOIN users u ON a.student_id = u.id
     INNER JOIN jobs j ON a.job_id = j.id
     WHERE j.company_id = ?
     ORDER BY a.applied_at DESC"
);

$stmt->execute([$companyId]);
$applications = $stmt->fetchAll();

// Safely display database content
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
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

    <title>Applications | HireTrack</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header class="manage-jobs-header">
    <a href="index.php" class="logo">HireTrack</a>

    <a href="employer-dashboard.php" class="manage-back-link">
        Back to Dashboard
    </a>
</header>

<main class="applications-container">

    <div class="applications-heading">
        <p class="applications-eyebrow">EMPLOYER PORTAL</p>

        <h1>Applications</h1>

        <p>
            Review candidates who applied to
            <?= e($company['company_name']) ?>'s job listings.
        </p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="manage-alert manage-success">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="manage-alert manage-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="applications-count">
        Total Applications: <strong><?= count($applications) ?></strong>
    </div>

    <?php if (empty($applications)): ?>

        <section class="applications-empty">
            <h2>No applications yet</h2>

            <p>
                When students apply for your job listings,
                their applications will appear here.
            </p>

            <a href="manage-jobs.php" class="btn-post-job">
                Manage Your Jobs
            </a>
        </section>

    <?php else: ?>

        <section class="applications-list">

            <?php foreach ($applications as $application): ?>

                <article class="application-card">

                    <div class="application-top">

                        <div>
                            <h2><?= e($application['student_name']) ?></h2>

                            <p class="applicant-email">
                                <?= e($application['student_email']) ?>
                            </p>
                        </div>

                        <span class="job-status status-<?= e($application['application_status']) ?>">
                            <?= e(ucfirst($application['application_status'])) ?>
                        </span>

                    </div>

                    <div class="application-job-info">
                        <span>
                            <strong>Job:</strong>
                            <?= e($application['job_title']) ?>
                        </span>

                        <span>
                            <strong>Applied:</strong>
                            <?= e(date(
                                'M j, Y',
                                strtotime($application['applied_at'])
                            )) ?>
                        </span>
                    </div>

                    <div class="application-cover-letter">
                        <h3>Cover Letter</h3>

                        <?php if (!empty($application['cover_letter'])): ?>
                            <p><?= nl2br(e($application['cover_letter'])) ?></p>
                        <?php else: ?>
                            <p class="no-cover-letter">
                                No cover letter was provided.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="application-actions">

                        <form
                            method="POST"
                            action="view-applications.php"
                            class="application-status-form"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($_SESSION['csrf_token']) ?>"
                            >

                            <input
                                type="hidden"
                                name="application_id"
                                value="<?= (int)$application['application_id'] ?>"
                            >

                            <label for="status-<?= (int)$application['application_id'] ?>">
                                Update Status
                            </label>

                            <select
                                id="status-<?= (int)$application['application_id'] ?>"
                                name="status"
                                required
                            >
                                <?php
                                $statuses = [
                                    'pending' => 'Pending',
                                    'shortlisted' => 'Shortlisted',
                                    'accepted' => 'Accepted',
                                    'rejected' => 'Rejected'
                                ];
                                ?>

                                <?php foreach ($statuses as $value => $label): ?>
                                    <option
                                        value="<?= e($value) ?>"
                                        <?= $application['application_status'] === $value
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button
                                type="submit"
                                class="application-update-button"
                            >
                                Update Status
                            </button>
                        </form>

                    </div>

                </article>

            <?php endforeach; ?>

        </section>

    <?php endif; ?>

</main>

</body>
</html>
