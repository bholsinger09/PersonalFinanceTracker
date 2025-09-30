<?php
// Dashboard for logged-in users
$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Finance Tracker Dashboard</title>
</head>
<body>
    <h2>Welcome, <?php echo htmlspecialchars($user['name'] ?? $user['email']); ?></h2>
    <a href="/logout.php">Logout</a>
    <hr>
    <h3>Your Transactions</h3>
    <!-- Transaction list will go here -->
    <a href="/add_transaction.php">Add Transaction</a>
</body>
</html>
