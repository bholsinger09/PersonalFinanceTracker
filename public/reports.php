<?php
// Reports page for analytics and insights
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit();
}

require_once '../vendor/autoload.php';

use FinanceTracker\Report;
use FinanceTracker\Category;
use FinanceTracker\Database;

$user = $_SESSION['user'];

// Get the database user ID
$userId = $user['id'] ?? null;

// Fallback: If somehow we don't have a numeric ID, try to look it up
if (!$userId || !is_numeric($userId)) {
    error_log("Reports: No valid user ID in session, attempting lookup");
    
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

$error = '';

try {
    // Get selected year and month from URL parameters
    $selectedYear = $_GET['year'] ?? date('Y');
    $selectedMonth = $_GET['month'] ?? date('m');
    
    // Generate reports
    $monthlySummary = Report::getMonthlySummary($userId, $selectedYear, $selectedMonth);
    $monthlyCategories = Report::getMonthlySpendingByCategory($userId, $selectedYear, $selectedMonth);
    $yearlyOverview = Report::getYearlyOverview($userId, $selectedYear);
    $topCategories = Report::getTopSpendingCategories($userId, 5);
    $trends = Report::getSpendingTrends($userId, $selectedYear, $selectedMonth);
    $dailySpending = Report::getDailySpending($userId, $selectedYear, $selectedMonth);
    
} catch (Exception $e) {
    $error = 'Failed to load reports: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Finance Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Mobile optimizations for reports */
        @media (max-width: 768px) {
            .container { padding: 0 1rem; }
            .mt-5 { margin-top: 2rem !important; }
            
            /* Header adjustments */
            .mobile-header { text-align: center; margin-bottom: 1rem; }
            .mobile-header h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
            
            /* Period selector responsive */
            .period-selector .row > div { margin-bottom: 1rem; }
            .period-selector .btn { width: 100%; }
            
            /* Summary cards stack */
            .summary-card { margin-bottom: 1rem; }
            .summary-card .card-body { padding: 1rem; }
            .summary-card h4 { font-size: 1.25rem; }
            
            /* Chart containers mobile */
            .chart-container { 
                position: relative; 
                height: 300px !important; 
                margin-bottom: 2rem;
            }
            
            /* Stats grid responsive */
            .stats-grid .col-md-3 { margin-bottom: 1rem; }
            
            /* Hide excessive details on small screens */
            .hide-mobile { display: none !important; }
            
            /* Button groups stack */
            .btn-group-mobile .btn { 
                width: 100%; 
                margin-bottom: 0.5rem; 
            }
        }
        
        /* Chart responsive behavior */
        .chart-container {
            position: relative;
            height: 400px;
        }
        
        @media (max-width: 576px) {
            .chart-container { height: 250px !important; }
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <!-- Header -->
        <div class="row">
            <div class="col-md-8 mobile-header">
                <h1><i class="fas fa-chart-bar"></i> Financial Reports</h1>
            </div>
            <div class="col-md-4 text-md-end text-center">
                <div class="btn-group-mobile d-md-none">
                    <a href="/index.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="/logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
                <div class="d-none d-md-block">
                    <a href="/index.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="/logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger mt-3"><?php echo $error; ?></div>
        <?php else: ?>

        <!-- Month/Year Selector -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body period-selector">
                        <h5 class="card-title">Select Period</h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-4 col-12">
                                <label for="year" class="form-label">Year</label>
                                <select name="year" id="year" class="form-select">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $selectedYear ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 col-12">
                                <label for="month" class="form-label">Month</label>
                                <select name="month" id="month" class="form-select">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" 
                                                <?php echo $m == (int)$selectedMonth ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-4 col-12">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block w-100">
                                    <i class="fas fa-search"></i> Update Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Summary -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-calendar"></i> 
                            <?php echo $monthlySummary['period']; ?> Summary
                        </h5>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h4>$<?php echo number_format($monthlySummary['total_income'], 2); ?></h4>
                                        <p class="mb-0">Total Income</p>
                                        <small><?php echo $monthlySummary['income_transactions']; ?> transactions</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body">
                                        <h4>$<?php echo number_format($monthlySummary['total_expenses'], 2); ?></h4>
                                        <p class="mb-0">Total Expenses</p>
                                        <small><?php echo $monthlySummary['expense_transactions']; ?> transactions</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-<?php echo $monthlySummary['net_change'] >= 0 ? 'success' : 'warning'; ?> text-white">
                                    <div class="card-body">
                                        <h4><?php echo $monthlySummary['net_change'] >= 0 ? '+' : ''; ?>$<?php echo number_format($monthlySummary['net_change'], 2); ?></h4>
                                        <p class="mb-0">Net Change</p>
                                        <small><?php echo $monthlySummary['total_transactions']; ?> total transactions</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h4><?php echo $monthlySummary['total_expenses'] > 0 ? number_format(($monthlySummary['total_income'] / $monthlySummary['total_expenses']) * 100, 1) : 0; ?>%</h4>
                                        <p class="mb-0">Income Ratio</p>
                                        <small>Income vs Expenses</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trends -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-trending-up"></i> 
                            Trends (vs <?php echo $trends['previous']['period']; ?>)
                        </h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-arrow-<?php echo $trends['trends']['income_change'] >= 0 ? 'up text-success' : 'down text-danger'; ?>"></i>
                                    </div>
                                    <div>
                                        <strong>Income: <?php echo $trends['trends']['income_change'] >= 0 ? '+' : ''; ?><?php echo number_format($trends['trends']['income_change'], 1); ?>%</strong><br>
                                        <small class="text-muted">
                                            $<?php echo number_format($trends['current']['total_income'], 2); ?> 
                                            vs $<?php echo number_format($trends['previous']['total_income'], 2); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-arrow-<?php echo $trends['trends']['expense_change'] >= 0 ? 'up text-danger' : 'down text-success'; ?>"></i>
                                    </div>
                                    <div>
                                        <strong>Expenses: <?php echo $trends['trends']['expense_change'] >= 0 ? '+' : ''; ?><?php echo number_format($trends['trends']['expense_change'], 1); ?>%</strong><br>
                                        <small class="text-muted">
                                            $<?php echo number_format($trends['current']['total_expenses'], 2); ?> 
                                            vs $<?php echo number_format($trends['previous']['total_expenses'], 2); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-<?php echo $trends['trends']['net_change'] >= 0 ? 'plus text-success' : 'minus text-danger'; ?>"></i>
                                    </div>
                                    <div>
                                        <strong>Net: <?php echo $trends['trends']['net_change'] >= 0 ? '+' : ''; ?>$<?php echo number_format($trends['trends']['net_change'], 2); ?></strong><br>
                                        <small class="text-muted">Month-over-month change</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mt-4">
            <!-- Daily Spending Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-chart-line"></i> 
                            Daily Spending - <?php echo $monthlySummary['month_name']; ?>
                        </h5>
                        <canvas id="dailyChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Category Breakdown -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-chart-pie"></i> 
                            Spending by Category
                        </h5>
                        <canvas id="categoryChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Details -->
        <?php if (!empty($monthlyCategories)): ?>
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-list"></i> 
                            Category Breakdown - <?php echo $monthlySummary['period']; ?>
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Transactions</th>
                                        <th>Total Amount</th>
                                        <th>Avg per Transaction</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthlyCategories as $category): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($category['category']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $category['type'] === 'expense' ? 'danger' : 'success'; ?>">
                                                    <?php echo ucfirst($category['type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $category['transaction_count']; ?></td>
                                            <td class="text-<?php echo $category['type'] === 'expense' ? 'danger' : 'success'; ?>">
                                                $<?php echo number_format($category['total_amount'], 2); ?>
                                            </td>
                                            <td>
                                                $<?php echo number_format($category['total_amount'] / $category['transaction_count'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Daily Spending Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyData = <?php echo json_encode($dailySpending ?? []); ?>;
        
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyData.map(d => d.day),
                datasets: [{
                    label: 'Income',
                    data: dailyData.map(d => d.income),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Expenses',
                    data: dailyData.map(d => d.expenses),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(0);
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': $' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryData = <?php echo json_encode($monthlyCategories ?? []); ?>;
        const expenseCategories = categoryData.filter(c => c.type === 'expense');
        
        if (expenseCategories.length > 0) {
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: expenseCategories.map(c => c.category),
                    datasets: [{
                        data: expenseCategories.map(c => c.total_amount),
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                            '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': $' + context.parsed.toFixed(2) + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>