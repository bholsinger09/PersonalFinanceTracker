<?php

// Add transaction page
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit();
}

require_once '../vendor/autoload.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = trim($_POST['amount'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validate input
    if (empty($amount) || !is_numeric($amount)) {
        $error = 'Please enter a valid amount.';
    } elseif (empty($description)) {
        $error = 'Please enter a description.';
    } else {
        try {
            // Save transaction to database
            $userId = $_SESSION['user']['id'] ?? $_SESSION['user']['email'];
            $transaction = \FinanceTracker\Transaction::create($userId, (float) $amount, $description);
            $message = 'Transaction added successfully!';
        } catch (Exception $e) {
            $error = 'Failed to save transaction. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Transaction</title>
    <style>
        .message { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>Add Transaction</h2>

    <?php if ($message) : ?>
        <p class="message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if ($error) : ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Amount: <input type="number" name="amount" step="0.01" required></label><br>
        <label>Description: <input type="text" name="description" required></label><br>
        <button type="submit">Add</button>
    </form>
    <a href="/index.php">Back to Dashboard</a>
</body>
</html>
