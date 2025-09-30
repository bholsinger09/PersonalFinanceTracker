<?php

// Login page for OAuth authentication
session_start();

if (isset($_SESSION['user'])) {
    header('Location: /index.php');
    exit();
}

// Simple login page with OAuth button
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Finance Tracker</title>
</head>
<body>
    <h2>Login to Finance Tracker</h2>
    <a href="/oauth.php">Login with Google</a>
</body>
</html>
