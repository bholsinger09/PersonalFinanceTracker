<?php
// Dashboard for logged-in users
require_once '../vendor/autoload.php';

use FinanceTracker\Transaction;
use FinanceTracker\User;
use FinanceTracker\Category;

$user = $_SESSION['user'] ?? null;

if (!$user) {
    header('Location: /login.php');
    exit();
}

// Get the database user ID - this should now be reliable from the session
$userId = $user['id'] ?? null;

// Fallback: If somehow we don't have a numeric ID, try to look it up
if (!$userId || !is_numeric($userId)) {
    error_log("Dashboard: No valid user ID in session, attempting lookup for: " . json_encode($user));
    
    $email = $user['email'] ?? null;
    $googleId = $user['google_id'] ?? null;
    
    if ($email) {
        $sql = "SELECT id FROM users WHERE email = ?";
        $result = \FinanceTracker\Database::query($sql, [$email]);
        $userId = $result[0]['id'] ?? null;
    } elseif ($googleId) {
        $sql = "SELECT id FROM users WHERE google_id = ?";
        $result = \FinanceTracker\Database::query($sql, [$googleId]);
        $userId = $result[0]['id'] ?? null;
    }
    
    if (!$userId) {
        error_log("Dashboard: Could not resolve user ID, redirecting to login");
        header('Location: /login.php');
        exit();
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
        $categoryFilter = $_GET['category'] ?? null;
        $transactions = Transaction::getAllWithFilter($userId, $filter, $categoryFilter);
        
        // Load categories for the user
        $categories = Category::getByUserId($userId);

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
    <style>
        /* Mobile-first responsive improvements */
        @media (max-width: 768px) {
            .container { padding: 0 15px; }
            .mt-5 { margin-top: 2rem !important; }
            
            /* Header adjustments */
            .mobile-header { text-align: center; margin-bottom: 1rem; }
            .mobile-header h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
            .mobile-welcome { font-size: 0.9rem; margin-bottom: 1rem; }
            
            /* Balance cards stack on mobile */
            .balance-card { margin-bottom: 1rem; }
            .balance-card .card-body { padding: 1rem; }
            .balance-card h3 { font-size: 1.5rem; }
            
            /* Action buttons stack vertically */
            .mobile-actions .btn { 
                width: 100%; 
                margin-bottom: 0.5rem; 
                font-size: 0.9rem;
            }
            
            /* Filter buttons responsive */
            .mobile-filters .btn { 
                font-size: 0.8rem; 
                padding: 0.375rem 0.5rem;
                margin-bottom: 0.25rem;
            }
            
            /* Transaction table mobile optimization */
            .mobile-table { 
                font-size: 0.85rem; 
                overflow-x: auto;
            }
            .mobile-table th, .mobile-table td { 
                padding: 0.5rem 0.25rem; 
                white-space: nowrap;
            }
            .mobile-table .btn { 
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            /* Category filter on mobile */
            .mobile-category-filter { margin-top: 1rem; }
            
            /* Starting balance form mobile */
            .mobile-balance-form .input-group { margin-bottom: 1rem; }
        }
        
        /* Tablet improvements */
        @media (min-width: 769px) and (max-width: 1024px) {
            .balance-card h3 { font-size: 1.75rem; }
            .btn-group .btn { padding: 0.5rem 1rem; }
        }
        
        /* Hide elements on very small screens */
        @media (max-width: 576px) {
            .hide-mobile { display: none !important; }
            .mobile-only { display: block !important; }
        }
        
        /* Ensure proper spacing */
        .mobile-spacing { margin: 1rem 0; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <!-- Mobile-optimized header -->
        <div class="row d-md-flex d-block">
            <div class="col-md-8 mobile-header">
                <h1><i class="fas fa-wallet"></i> Finance Tracker</h1>
            </div>
            <div class="col-md-4 text-md-end text-center">
                <div class="mobile-welcome">
                    <span class="d-inline-block mb-2">Welcome, <?php echo htmlspecialchars($user['name'] ?? $user['email']); ?></span>
                </div>
                <a href="/logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">Logout</span>
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
                <!-- Balance Overview -->
        <div class="row mobile-spacing">
            <div class="col-md-12">
                <div class="card balance-card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 col-12 mb-3 mb-md-0">
                                <h3 class="card-title">
                                    <i class="fas fa-dollar-sign"></i> Current Balance
                                </h3>
                                <h2 class="text-<?php echo $currentBalance >= 0 ? 'success' : 'danger'; ?>">
                                    $<?php echo number_format($currentBalance, 2); ?>
                                </h2>
                            </div>
                            <div class="col-md-6 col-12">
                                <h5>Starting Balance</h5>
                                <form method="POST" class="mobile-balance-form">
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" name="starting_balance" 
                                               value="<?php echo number_format($startingBalance, 2, '.', ''); ?>" 
                                               class="form-control" placeholder="0.00">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> <span class="d-none d-sm-inline">Update</span>
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
        <div class="row mobile-spacing">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Quick Actions</h5>
                        <div class="btn-group d-none d-md-flex" role="group">
                            <a href="/add_transaction.php?type=expense" class="btn btn-danger">
                                <i class="fas fa-minus-circle"></i> Add Expense
                            </a>
                            <a href="/add_transaction.php?type=deposit" class="btn btn-success">
                                <i class="fas fa-plus-circle"></i> Add Deposit
                            </a>
                            <a href="/reports.php" class="btn btn-info">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>
                        </div>
                        <!-- Mobile stacked buttons -->
                        <div class="d-md-none mobile-actions">
                            <a href="/add_transaction.php?type=expense" class="btn btn-danger">
                                <i class="fas fa-minus-circle"></i> Add Expense
                            </a>
                            <a href="/add_transaction.php?type=deposit" class="btn btn-success">
                                <i class="fas fa-plus-circle"></i> Add Deposit
                            </a>
                            <a href="/reports.php" class="btn btn-info">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mobile-spacing">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filter Transactions</h5>
                        <div class="row">
                            <div class="col-md-6 col-12 mb-3 mb-md-0">
                                <label class="form-label">By Type:</label>
                                <div class="btn-group d-none d-md-flex" role="group">
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
                                <!-- Mobile filter buttons -->
                                <div class="d-md-none mobile-filters">
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
                            <div class="col-md-6 col-12 mobile-category-filter">
                                <label for="categoryFilter" class="form-label">By Category:</label>
                                <select id="categoryFilter" class="form-select" onchange="filterByCategory(this.value)">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['name']); ?>"
                                                <?php echo (isset($_GET['category']) && $_GET['category'] === $category['name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
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
                            <!-- Desktop table -->
                            <div class="table-responsive d-none d-md-block">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th>Category</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($transaction['date'] ?? $transaction['created_at'])); ?></td>
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
                                                <td>
                                                    <a href="/edit_transaction.php?id=<?php echo $transaction['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="Edit Transaction">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Mobile card view -->
                            <div class="d-md-none">
                                <?php foreach ($transactions as $transaction): ?>
                                    <div class="card mb-2">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($transaction['description']); ?></h6>
                                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($transaction['date'] ?? $transaction['created_at'])); ?></small>
                                                </div>
                                                <a href="/edit_transaction.php?id=<?php echo $transaction['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   title="Edit Transaction">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="badge bg-<?php echo $transaction['type'] === 'expense' ? 'danger' : 'success'; ?> me-2">
                                                        <?php echo ucfirst($transaction['type']); ?>
                                                    </span>
                                                    <small class="text-muted"><?php echo htmlspecialchars($transaction['category'] ?? 'Uncategorized'); ?></small>
                                                </div>
                                                <strong class="text-<?php echo $transaction['type'] === 'expense' ? 'danger' : 'success'; ?>">
                                                    <?php echo $transaction['type'] === 'expense' ? '-' : '+'; ?>
                                                    $<?php echo number_format($transaction['amount'], 2); ?>
                                                </strong>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterByCategory(category) {
            const currentUrl = new URL(window.location);
            if (category) {
                currentUrl.searchParams.set('category', category);
            } else {
                currentUrl.searchParams.delete('category');
            }
            window.location = currentUrl.toString();
        }
    </script>
</body>
</html>
