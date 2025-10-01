<?php
// Dashboard for logged-in users
require_once '../vendor/autoload.php';

use FinanceTracker\Transaction;

$user = $_SESSION['user'] ?? null;

if (!$user) {
    header('Location: /login.php');
    exit();
}

$userId = $user['id'] ?? null;

// If we don't have a database user ID, try to get it from the database
if (!$userId || !is_numeric($userId)) {
    // Try to find user ID from database using email or google_id
    $email = $user['email'] ?? null;
    $googleId = $user['google_id'] ?? $user['id'] ?? null;
    
    if ($email) {
        $sql = "SELECT id FROM users WHERE email = ?";
        $result = \FinanceTracker\Database::query($sql, [$email]);
        $userId = $result[0]['id'] ?? null;
    } elseif ($googleId) {
        $sql = "SELECT id FROM users WHERE google_id = ?";
        $result = \FinanceTracker\Database::query($sql, [$googleId]);
        $userId = $result[0]['id'] ?? null;
    }
}

$currentBalance = 0.0;
$startingBalance = 0.0;
$transactions = [];
$error = null;

if ($userId) {
    try {
        // Handle starting balance update
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['starting_balance'])) {
            $amount = (float) $_POST['starting_balance'];
            if ($amount >= 0) {
                Transaction::setStartingBalance($userId, $amount);
                $currentBalance = Transaction::getCurrentBalance($userId);
                $startingBalance = $amount;
                $message = "Starting balance updated successfully!";
            }
        }

        // Get balance information
        $currentBalance = Transaction::getCurrentBalance($userId);
        $startingBalance = Transaction::getStartingBalance($userId);

        // Handle filter
        $filter = $_GET['filter'] ?? null;
        $transactions = Transaction::getAllWithFilter($userId, $filter);

    } catch (Exception $e) {
        $error = 'Failed to load dashboard data: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Tracker Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8">
                <h1><i class="fas fa-wallet"></i> Finance Tracker</h1>
            </div>
            <div class="col-md-4 text-end">
                <span class="me-3">Welcome, <?php echo htmlspecialchars($user['name'] ?? $user['email']); ?></span>
                <a href="/logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-success mt-3"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger mt-3"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Balance Display -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h3 class="card-title">
                                    <i class="fas fa-dollar-sign"></i> Current Balance
                                </h3>
                                <h2 class="text-<?php echo $currentBalance >= 0 ? 'success' : 'danger'; ?>">
                                    $<?php echo number_format($currentBalance, 2); ?>
                                </h2>
                            </div>
                            <div class="col-md-6">
                                <h5>Starting Balance</h5>
                                <form method="POST" class="d-inline">
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" name="starting_balance" 
                                               value="<?php echo number_format($startingBalance, 2, '.', ''); ?>" 
                                               class="form-control" placeholder="0.00">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Quick Actions</h5>
                        <div class="btn-group" role="group">
                            <a href="/add_transaction.php?type=expense" class="btn btn-danger">
                                <i class="fas fa-minus-circle"></i> Add Expense
                            </a>
                            <a href="/add_transaction.php?type=deposit" class="btn btn-success">
                                <i class="fas fa-plus-circle"></i> Add Deposit
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filter Transactions</h5>
                        <div class="btn-group" role="group">
                            <a href="?" class="btn btn-outline-primary <?php echo !$filter ? 'active' : ''; ?>">
                                <i class="fas fa-list"></i> All
                            </a>
                            <a href="?filter=expense" class="btn btn-outline-danger <?php echo $filter === 'expense' ? 'active' : ''; ?>">
                                <i class="fas fa-minus-circle"></i> Expenses
                            </a>
                            <a href="?filter=deposit" class="btn btn-outline-success <?php echo $filter === 'deposit' ? 'active' : ''; ?>">
                                <i class="fas fa-plus-circle"></i> Deposits
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions List -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Recent Transactions
                            <a href="/add_transaction.php" class="btn btn-primary btn-sm float-end">
                                <i class="fas fa-plus"></i> Add Transaction
                            </a>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transactions)): ?>
                            <p class="text-muted">No transactions found. <a href="/add_transaction.php">Add your first transaction</a>.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th>Category</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($transaction['date'])); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['category'] ?? 'Uncategorized'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $transaction['type'] === 'expense' ? 'danger' : 'success'; ?>">
                                                        <?php echo ucfirst($transaction['type']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-<?php echo $transaction['type'] === 'expense' ? 'danger' : 'success'; ?>">
                                                    <?php echo $transaction['type'] === 'expense' ? '-' : '+'; ?>
                                                    $<?php echo number_format($transaction['amount'], 2); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
