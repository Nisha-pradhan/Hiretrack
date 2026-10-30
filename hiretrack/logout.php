
<?php
session_start();

// Log out only after confirmation
if (isset($_POST['confirm_logout']) && $_POST['confirm_logout'] === 'yes') {

    $_SESSION = [];

    // Remove the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header("Location: index.php");
    exit;
}

// If the user cancels, return to the previous page
if (isset($_POST['confirm_logout']) && $_POST['confirm_logout'] === 'no') {
    $role = $_SESSION['role'] ?? '';

    if ($role === 'employer') {
        header("Location: employer-dashboard.php");
    } else {
        header("Location: student-dashboard.php");
    }

    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Logout | HireTrack</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <div class="logout-confirmation">
        <div class="logout-icon">↗</div>

        <h1>Leaving so soon?</h1>

        <p>Are you sure you want to log out of your HireTrack account?</p>

        <form method="POST" action="logout.php">
            <button type="submit" name="confirm_logout" value="yes"
                    class="logout-yes">
                Yes
            </button>

            <button type="submit" name="confirm_logout" value="no"
                    class="logout-no">
                No
            </button>
        </form>
    </div>

</body>
</html>
