
<?php
require_once __DIR__ . '/config/database.php';

$error = '';
$success = '';

$fullName = '';
$email = '';
$role = 'student';
$companyName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $companyName = trim($_POST['company_name'] ?? '');

    if ($fullName === '' || strlen($fullName) > 100) {
        $error = 'Please enter a name of up to 100 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 150) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Your password must contain at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'The passwords do not match.';
    } elseif (!in_array($role, ['student', 'employer'], true)) {
        $error = 'Please select a valid account type.';
    } elseif ($role === 'employer'
        && ($companyName === '' || strlen($companyName) > 150)) {
        $error = 'Please enter a company name of up to 150 characters.';
    } else {
        try {
            $pdo->beginTransaction();

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare(
                "INSERT INTO users
                    (full_name, email, password, role)
                 VALUES
                    (:full_name, :email, :password, :role)"
            );

            $stmt->execute([
                'full_name' => $fullName,
                'email' => $email,
                'password' => $hashedPassword,
                'role' => $role
            ]);

            $userId = (int) $pdo->lastInsertId();

            if ($role === 'student') {
                $stmt = $pdo->prepare(
                    "INSERT INTO student_profiles (user_id)
                     VALUES (:user_id)"
                );

                $stmt->execute(['user_id' => $userId]);
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO companies (user_id, company_name)
                     VALUES (:user_id, :company_name)"
                );

                $stmt->execute([
                    'user_id' => $userId,
                    'company_name' => $companyName
                ]);
            }

            $pdo->commit();

            $success = 'Your account was created successfully! You can now log in.';

            $fullName = '';
            $email = '';
            $role = 'student';
            $companyName = '';
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e->getCode() === '23000') {
                $error = 'This email address may already be registered. Try another email.';
            } else {
                error_log($e->getMessage());
                $error = 'We could not create your account. Please try again.';
            }
        }
    }
}

function e($value) {
    return htmlspecialchars(
        (string) $value,
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

    <title>Create Account | HireTrack</title>

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
        </nav>
    </div>
</header>

<main class="auth-page">
    <section class="auth-card">

        <span class="eyebrow">JOIN HIRETRACK</span>

        <h1>Create your account</h1>

        <p class="auth-subtitle">
            Start exploring opportunities and growing your career.
        </p>

        <?php if ($error !== ''): ?>
            <div class="message message-error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="message message-success" role="status">
                <?= e($success) ?>
                <p>
                    <a href="login.php">Continue to login &rarr;</a>
                </p>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">

            <div class="form-group">
                <label for="full_name">Full name</label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    maxlength="100"
                    value="<?= e($fullName) ?>"
                    placeholder="Enter your full name"
                    autocomplete="name"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    value="<?= e($email) ?>"
                    placeholder="you@example.com"
                    autocomplete="email"
                    required
                >
            </div>

            <div class="form-group">
                <label for="role">Account type</label>

                <select id="role" name="role" required>
                    <option value="student"
                        <?= $role === 'student' ? 'selected' : '' ?>>
                        Student / Job Seeker
                    </option>

                    <option value="employer"
                        <?= $role === 'employer' ? 'selected' : '' ?>>
                        Employer
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label for="company_name">
                    Company name
                    <span class="optional-label">
                        (required for employers)
                    </span>
                </label>

                <input
                    type="text"
                    id="company_name"
                    name="company_name"
                    maxlength="150"
                    value="<?= e($companyName) ?>"
                    placeholder="Enter your company name"
                    autocomplete="organization"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    placeholder="At least 8 characters"
                    autocomplete="new-password"
                    required
                >
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm password</label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    placeholder="Enter your password again"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary auth-submit">
                Create Account
            </button>

        </form>

        <p class="auth-switch">
            Already have an account?
            <a href="login.php">Log in</a>
        </p>

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
