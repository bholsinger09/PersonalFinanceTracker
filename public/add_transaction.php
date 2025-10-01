<?php

// Add transaction page
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit();
}

require_once '../vendor/autoload.php';

use FinanceTracker\Transaction;
use FinanceTracker\Database;

$message = '';
$error = '';
$user = $_SESSION['user'];

// Get user ID
$userId = $user['id'] ?? null;
if (!$userId) {
    // Try to find user ID from database using email
    $sql = "SELECT id FROM users WHERE email = ?";
    $result = Database::query($sql, [$user['email']]);
    $userId = $result[0]['id'] ?? null;
}

// Get transaction type from URL parameter
$type = $_GET['type'] ?? 'expense'; // Default to expense

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = trim($_POST['amount'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $transactionType = $_POST['type'] ?? 'expense';

    // Validate input
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $error = 'Please enter a valid amount greater than 0.';
    } elseif (empty($description)) {
        $error = 'Please enter a description.';
    } elseif (!in_array($transactionType, ['expense', 'deposit'])) {
        $error = 'Invalid transaction type.';
    } else {
        try {
            // Save transaction to database
            if (Transaction::create($userId, (float) $amount, $description, $category, $transactionType)) {
                $message = ucfirst($transactionType) . ' added successfully!';
                // Clear form
                $amount = '';
                $description = '';
                $category = '';
            } else {
                $error = 'Failed to save transaction. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Failed to save transaction: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add <?php echo ucfirst($type); ?> - Finance Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-plus"></i> Add <?php echo ucfirst($type); ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="type" class="form-label">Transaction Type</label>
                                <select name="type" id="type" class="form-select" required>
                                    <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>
                                        <i class="fas fa-minus-circle"></i> Expense
                                    </option>
                                    <option value="deposit" <?php echo $type === 'deposit' ? 'selected' : ''; ?>>
                                        <i class="fas fa-plus-circle"></i> Deposit
                                    </option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0.01" name="amount" id="amount" 
                                           class="form-control" value="<?php echo htmlspecialchars($amount ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" name="description" id="description" class="form-control" 
                                       value="<?php echo htmlspecialchars($description ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="category" class="form-label">Category (Optional)</label>
                                <input type="text" name="category" id="category" class="form-control" 
                                       value="<?php echo htmlspecialchars($category ?? ''); ?>"
                                       placeholder="e.g., Food, Transportation, Entertainment, Salary">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-<?php echo $type === 'expense' ? 'danger' : 'success'; ?>">
                                    <i class="fas fa-save"></i> Add <?php echo ucfirst($type); ?>
                                </button>
                                <a href="/index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update form styling based on transaction type
        document.getElementById('type').addEventListener('change', function() {
            const type = this.value;
            const submitBtn = document.querySelector('button[type="submit"]');
            const cardHeader = document.querySelector('.card-header h4');
            
            if (type === 'expense') {
                submitBtn.className = 'btn btn-danger';
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Add Expense';
                cardHeader.innerHTML = '<i class="fas fa-minus-circle"></i> Add Expense';
            } else {
                submitBtn.className = 'btn btn-success';
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Add Deposit';
                cardHeader.innerHTML = '<i class="fas fa-plus-circle"></i> Add Deposit';
            }
        });
    </script>
</body>
</html>
