<?php
// Edit transaction page
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit();
}

require_once '../vendor/autoload.php';

use FinanceTracker\Transaction;
use FinanceTracker\Category;
use FinanceTracker\Database;

$message = '';
$error = '';
$user = $_SESSION['user'];

// Get the database user ID
$userId = $user['id'] ?? null;

// Fallback: If somehow we don't have a numeric ID, try to look it up
if (!$userId || !is_numeric($userId)) {
    error_log("Edit Transaction: No valid user ID in session, attempting lookup");
    
    $email = $user['email'] ?? null;
    $googleId = $user['google_id'] ?? null;
    
    if ($email) {
        $sql = "SELECT id FROM users WHERE email = ?";
        $result = Database::query($sql, [$email]);
        $userId = $result[0]['id'] ?? null;
    } elseif ($googleId) {
        $sql = "SELECT id FROM users WHERE google_id = ?";
        $result = Database::query($sql, [$googleId]);
        $userId = $result[0]['id'] ?? null;
    }
    
    if (!$userId) {
        header('Location: /login.php');
        exit();
    }
}

// Get transaction ID from URL
$transactionId = $_GET['id'] ?? null;
if (!$transactionId || !is_numeric($transactionId)) {
    header('Location: /index.php?error=Invalid transaction ID');
    exit();
}

// Get the transaction (ensure it belongs to the user)
$transaction = Transaction::getByIdAndUserId((int)$transactionId, $userId);
if (!$transaction) {
    header('Location: /index.php?error=Transaction not found');
    exit();
}

// Load categories for the user
$categories = Category::getGroupedByType((int)$userId);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        // Handle deletion
        if (Transaction::deleteById((int)$transactionId, $userId)) {
            header('Location: /index.php?message=Transaction deleted successfully');
            exit();
        } else {
            $error = 'Failed to delete transaction. Please try again.';
        }
    } elseif ($action === 'update') {
        // Handle update
        $amount = trim($_POST['amount'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '') ?: null;
        $type = $_POST['type'] ?? 'expense';

        // Validate input
        if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
            $error = 'Please enter a valid amount greater than 0.';
        } elseif (empty($description)) {
            $error = 'Please enter a description.';
        } elseif (!in_array($type, ['expense', 'deposit'])) {
            $error = 'Invalid transaction type.';
        } else {
            try {
                if (Transaction::updateById((int)$transactionId, $userId, (float)$amount, $description, $category, $type)) {
                    header('Location: /index.php?message=Transaction updated successfully');
                    exit();
                } else {
                    $error = 'Failed to update transaction. Please try again.';
                }
            } catch (Exception $e) {
                $error = 'Failed to update transaction: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transaction - Finance Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-edit"></i> Edit Transaction
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="POST" id="editForm">
                            <input type="hidden" name="action" value="update">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="amount" class="form-label">Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="amount" id="amount" step="0.01" min="0.01" 
                                                   class="form-control" value="<?php echo htmlspecialchars($transaction['amount']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="type" class="form-label">Type</label>
                                        <select name="type" id="type" class="form-select" required onchange="updateCategoryOptions()">
                                            <option value="expense" <?php echo $transaction['type'] === 'expense' ? 'selected' : ''; ?>>Expense</option>
                                            <option value="deposit" <?php echo $transaction['type'] === 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" name="description" id="description" class="form-control" 
                                       value="<?php echo htmlspecialchars($transaction['description']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="category" class="form-label">Category (Optional)</label>
                                <select name="category" id="category" class="form-select">
                                    <option value="">Select a category...</option>
                                    <optgroup label="Expense Categories" id="expenseCategories">
                                        <?php foreach ($categories['expense'] as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['name']); ?>" 
                                                    <?php echo ($transaction['category'] === $cat['name']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="Income Categories" id="incomeCategories">
                                        <?php foreach ($categories['income'] as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['name']); ?>" 
                                                    <?php echo ($transaction['category'] === $cat['name']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                                <div class="form-text">
                                    Choose a category to better organize your transactions.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Transaction Date</label>
                                <div class="form-control-plaintext">
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('F j, Y g:i A', strtotime($transaction['date'])); ?>
                                </div>
                                <div class="form-text">
                                    Transaction date cannot be changed. To change the date, delete this transaction and create a new one.
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Transaction
                                </button>
                                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                                    <i class="fas fa-trash"></i> Delete Transaction
                                </button>
                                <a href="/index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </form>

                        <!-- Hidden delete form -->
                        <form method="POST" id="deleteForm" style="display: none;">
                            <input type="hidden" name="action" value="delete">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateCategoryOptions() {
            const type = document.getElementById('type').value;
            const expenseGroup = document.getElementById('expenseCategories');
            const incomeGroup = document.getElementById('incomeCategories');
            
            if (type === 'expense') {
                expenseGroup.style.display = 'block';
                incomeGroup.style.display = 'none';
            } else {
                expenseGroup.style.display = 'none';
                incomeGroup.style.display = 'block';
            }
        }

        function confirmDelete() {
            if (confirm('Are you sure you want to delete this transaction? This action cannot be undone.')) {
                document.getElementById('deleteForm').submit();
            }
        }

        // Initialize category visibility
        document.addEventListener('DOMContentLoaded', function() {
            updateCategoryOptions();
        });
    </script>
</body>
</html>