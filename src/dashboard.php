<?php
// Dashboard for logged-in users
$user = $_SESSION['user'] ?? null;

require_once '../vendor/autoload.php';

$transactions = [];
$totalBalance = 0.0;

$transactions = [];
$totalBalance = 0.0;

if ($user) {
    $userId = $user['id'] ?? $user['email'];
    try {
        $transactions = \FinanceTracker\Transaction::findByUserId($userId);
        $totalBalance = (float) array_reduce($transactions, function ($sum, $transaction) {
            return $sum + $transaction->getAmount();
        }, 0.0);
    } catch (Exception $e) {
        $error = 'Failed to load transactions.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Finance Tracker Dashboard</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .positive { color: green; }
        .negative { color: red; }
        .balance { font-size: 1.2em; font-weight: bold; margin: 20px 0; }
    </style>
</head>
<body>
    <h2>Welcome, <?php echo htmlspecialchars($user['name'] ?? $user['email']); ?></h2>
    <a href="/logout.php">Logout</a>
    <hr>

    <div class="balance">
        Current Balance: <span class="<?php echo $totalBalance >= 0 ? 'positive' : 'negative'; ?>">
            $<?php echo number_format($totalBalance, 2); ?>
        </span>
    </div>

    <h3>Your Transactions</h3>

    <?php if (empty($transactions)) : ?>
        <p>No transactions yet. <a href="/add_transaction.php">Add your first transaction</a></p>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $transaction) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($transaction->getFormattedCreatedAt()); ?></td>
                        <td><?php echo htmlspecialchars($transaction->getDescription()); ?></td>
                        <td class="<?php echo $transaction->getAmount() >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo htmlspecialchars($transaction->getFormattedAmount()); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <br>
    <a href="/add_transaction.php">Add Transaction</a>
</body>
</html>
