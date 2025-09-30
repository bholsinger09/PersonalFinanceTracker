<?php

// Add transaction page
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save transaction (for now, just echo)
    $amount = $_POST['amount'] ?? '';
    $desc = $_POST['description'] ?? '';
    echo "<p>Transaction added: $amount - $desc</p>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Transaction</title>
</head>
<body>
    <h2>Add Transaction</h2>
    <form method="post">
        <label>Amount: <input type="number" name="amount" step="0.01" required></label><br>
        <label>Description: <input type="text" name="description" required></label><br>
        <button type="submit">Add</button>
    </form>
    <a href="/index.php">Back to Dashboard</a>
</body>
</html>
