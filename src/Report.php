<?php

declare(strict_types=1);

namespace FinanceTracker;

/**
 * Reports model for generating analytics and insights
 */
class Report
{
    /**
     * Get monthly summary for a user
     */
    public static function getMonthlySummary(int $userId, ?string $year = null, ?string $month = null): array
    {
        $year = $year ?? date('Y');
        $month = $month ?? date('m');
        
        // Ensure month is zero-padded
        $month = str_pad((string)$month, 2, '0', STR_PAD_LEFT);
        
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate)); // Last day of month
        
        // Get total income and expenses for the month
        $sql = "
            SELECT 
                type,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount
            FROM transactions 
            WHERE user_id = ? 
            AND date >= ? 
            AND date <= ?
            GROUP BY type
        ";
        
        $result = Database::query($sql, [$userId, $startDate, $endDate]);
        
        $summary = [
            'year' => $year,
            'month' => $month,
            'month_name' => date('F', strtotime($startDate)),
            'period' => date('F Y', strtotime($startDate)),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_income' => 0.0,
            'total_expenses' => 0.0,
            'net_change' => 0.0,
            'income_transactions' => 0,
            'expense_transactions' => 0,
            'total_transactions' => 0
        ];
        
        foreach ($result as $row) {
            if ($row['type'] === 'deposit') {
                $summary['total_income'] = (float) $row['total_amount'];
                $summary['income_transactions'] = (int) $row['transaction_count'];
            } else {
                $summary['total_expenses'] = (float) $row['total_amount'];
                $summary['expense_transactions'] = (int) $row['transaction_count'];
            }
        }
        
        $summary['net_change'] = $summary['total_income'] - $summary['total_expenses'];
        $summary['total_transactions'] = $summary['income_transactions'] + $summary['expense_transactions'];
        
        return $summary;
    }

    /**
     * Get spending by category for a specific month
     */
    public static function getMonthlySpendingByCategory(int $userId, ?string $year = null, ?string $month = null): array
    {
        $year = $year ?? date('Y');
        $month = $month ?? str_pad((string)date('m'), 2, '0', STR_PAD_LEFT);
        
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $sql = "
            SELECT 
                category,
                type,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount
            FROM transactions 
            WHERE user_id = ? 
            AND date >= ? 
            AND date <= ?
            AND category IS NOT NULL
            GROUP BY category, type
            ORDER BY total_amount DESC
        ";
        
        return Database::query($sql, [$userId, $startDate, $endDate]);
    }

    /**
     * Get yearly overview with monthly breakdowns
     */
    public static function getYearlyOverview(int $userId, ?string $year = null): array
    {
        $year = $year ?? date('Y');
        
        $sql = "
            SELECT 
                strftime('%m', date) as month,
                type,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount
            FROM transactions 
            WHERE user_id = ? 
            AND strftime('%Y', date) = ?
            GROUP BY strftime('%m', date), type
            ORDER BY month
        ";
        
        $result = Database::query($sql, [$userId, $year]);
        
        // Initialize all months
        $overview = [];
        for ($i = 1; $i <= 12; $i++) {
            $month = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $overview[$month] = [
                'month' => $month,
                'month_name' => date('F', mktime(0, 0, 0, $i, 1, (int)$year)),
                'income' => 0.0,
                'expenses' => 0.0,
                'net' => 0.0,
                'income_count' => 0,
                'expense_count' => 0
            ];
        }
        
        // Fill with actual data
        foreach ($result as $row) {
            $month = $row['month'];
            if ($row['type'] === 'deposit') {
                $overview[$month]['income'] = (float) $row['total_amount'];
                $overview[$month]['income_count'] = (int) $row['transaction_count'];
            } else {
                $overview[$month]['expenses'] = (float) $row['total_amount'];
                $overview[$month]['expense_count'] = (int) $row['transaction_count'];
            }
            $overview[$month]['net'] = $overview[$month]['income'] - $overview[$month]['expenses'];
        }
        
        return [
            'year' => $year,
            'months' => array_values($overview),
            'totals' => [
                'income' => array_sum(array_column($overview, 'income')),
                'expenses' => array_sum(array_column($overview, 'expenses')),
                'net' => array_sum(array_column($overview, 'net')),
                'transactions' => array_sum(array_column($overview, 'income_count')) + array_sum(array_column($overview, 'expense_count'))
            ]
        ];
    }

    /**
     * Get top spending categories for a period
     */
    public static function getTopSpendingCategories(int $userId, int $limit = 10, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "
            SELECT 
                category,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
            FROM transactions 
            WHERE user_id = ? 
            AND type = 'expense'
            AND category IS NOT NULL
        ";
        
        $params = [$userId];
        
        if ($startDate) {
            $sql .= " AND date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " GROUP BY category ORDER BY total_amount DESC LIMIT ?";
        $params[] = $limit;
        
        return Database::query($sql, $params);
    }

    /**
     * Get spending trends (comparing with previous periods)
     */
    public static function getSpendingTrends(int $userId, ?string $year = null, ?string $month = null): array
    {
        $current = self::getMonthlySummary($userId, $year, $month);
        
        // Get previous month
        $currentDate = new \DateTime("$year-$month-01");
        $previousMonth = clone $currentDate;
        $previousMonth->modify('-1 month');
        
        $previous = self::getMonthlySummary(
            $userId, 
            $previousMonth->format('Y'), 
            $previousMonth->format('m')
        );
        
        // Calculate trends
        $trends = [
            'current' => $current,
            'previous' => $previous,
            'trends' => []
        ];
        
        // Income trend
        if ($previous['total_income'] > 0) {
            $trends['trends']['income_change'] = (($current['total_income'] - $previous['total_income']) / $previous['total_income']) * 100;
        } else {
            $trends['trends']['income_change'] = $current['total_income'] > 0 ? 100 : 0;
        }
        
        // Expense trend
        if ($previous['total_expenses'] > 0) {
            $trends['trends']['expense_change'] = (($current['total_expenses'] - $previous['total_expenses']) / $previous['total_expenses']) * 100;
        } else {
            $trends['trends']['expense_change'] = $current['total_expenses'] > 0 ? 100 : 0;
        }
        
        // Net change trend
        $trends['trends']['net_change'] = $current['net_change'] - $previous['net_change'];
        
        return $trends;
    }

    /**
     * Get daily spending for a month (for charts)
     */
    public static function getDailySpending(int $userId, ?string $year = null, ?string $month = null): array
    {
        $year = $year ?? date('Y');
        $month = $month ?? str_pad((string)date('m'), 2, '0', STR_PAD_LEFT);
        
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $sql = "
            SELECT 
                DATE(date) as day,
                type,
                SUM(amount) as total_amount
            FROM transactions 
            WHERE user_id = ? 
            AND date >= ? 
            AND date <= ?
            GROUP BY DATE(date), type
            ORDER BY day
        ";
        
        $result = Database::query($sql, [$userId, $startDate, $endDate]);
        
        // Initialize all days in the month
        $daily = [];
        $currentDate = new \DateTime($startDate);
        $endDateTime = new \DateTime($endDate);
        
        while ($currentDate <= $endDateTime) {
            $day = $currentDate->format('Y-m-d');
            $daily[$day] = [
                'date' => $day,
                'day' => $currentDate->format('j'),
                'income' => 0.0,
                'expenses' => 0.0,
                'net' => 0.0
            ];
            $currentDate->modify('+1 day');
        }
        
        // Fill with actual data
        foreach ($result as $row) {
            $day = $row['day'];
            if (isset($daily[$day])) {
                if ($row['type'] === 'deposit') {
                    $daily[$day]['income'] = (float) $row['total_amount'];
                } else {
                    $daily[$day]['expenses'] = (float) $row['total_amount'];
                }
                $daily[$day]['net'] = $daily[$day]['income'] - $daily[$day]['expenses'];
            }
        }
        
        return array_values($daily);
    }
}