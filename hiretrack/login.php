
<?php
session_start();

require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'employer') {
        header('Location: employer-dashboard.php');
    } else {
        header('Location: student-dashboard.php');
    }
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address and password.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, full_name, email, password, role
             FROM users
             WHERE email = :email
             LIMIT 1"
        );

        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'employer') {
                header('Location: employer-dashboard.php');
            } else {
                header('Location: student-dashboard.php');
            }

            exit;
        }

        $error = 'Incorrect email or password.';
    }
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
    <title>Login | HireTrack</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
    <div class="container nav-content">
        <a href="index.php" class="logo">HireTrack.</a>

        <nav class="nav-links">
            <a href="index.php">Home</a>
            <a href="jobs.php">Find Jobs</a>
            <a href="register.php">Create Account</a>
        </nav>
    </div>
</header>

<main class="auth-page">
    <section class="auth-card">
        <span class="eyebrow">WELCOME BACK</span>
        <h1>Log in to HireTrack</h1>

        <p class="auth-subtitle">
            Sign in to continue your career journey.
        </p>

        <?php if ($error !== ''): ?>
            <div class="message message-error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($email) ?>"
                    autocomplete="email"
                    placeholder="you@example.com"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary auth-submit">
                Log In
            </button>
        </form>

        <p class="auth-switch">
            Don't have an account?
            <a href="register.php">Register here</a>
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
