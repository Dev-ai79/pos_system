<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    error_log("reports.php: No user_id, redirecting to index.php");
    header('Location: index.php');
    exit;
}

// Determine report period
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';
$period = in_array($period, ['daily', 'weekly', 'monthly', 'yearly']) ? $period : 'daily';

// Initialize variables
$report_data = [];
$total_sales = 0;
$total_profit = 0;
$total_buying_price = 0;
$total_selling_price = 0;
$avg_sale = 0;
$no_data = false;
$period_label = '';
$most_sold = '';

try {
    // Build main query
    $query = "SELECT s.transaction_id, s.product_id, s.quantity, s.total, s.timestamp, s.selling_price, p.name, p.buying_price
              FROM sales s
              JOIN products p ON s.product_id = p.id";
    
    // Build most sold query
    $most_sold_query = "SELECT p.name, SUM(s.quantity) as total_quantity
                        FROM sales s
                        JOIN products p ON s.product_id = p.id";
    
    if ($period === 'daily') {
        $query .= " WHERE DATE(s.timestamp) = CURDATE()";
        $most_sold_query .= " WHERE DATE(s.timestamp) = CURDATE()";
        $start = date('Y-m-d 00:00:00');
        $end = date('Y-m-d 23:59:59');
        $period_label = "Daily Report - " . date('Y-m-d') . " (00:00:00 to 23:59:59)";
        error_log("reports.php: Daily report range - $start to $end");
    } elseif ($period === 'weekly') {
        $query .= " WHERE WEEK(s.timestamp, 1) = WEEK(CURDATE(), 1) AND YEAR(s.timestamp) = YEAR(CURDATE())";
        $most_sold_query .= " WHERE WEEK(s.timestamp, 1) = WEEK(CURDATE(), 1) AND YEAR(s.timestamp) = YEAR(CURDATE())";
        $start = date('Y-m-d', strtotime('monday this week'));
        $end = date('Y-m-d', strtotime('sunday this week'));
        $period_label = "Weekly Report - $start to $end (Monday 00:00:00 to Sunday 23:59:59)";
        error_log("reports.php: Weekly report range - $start 00:00:00 to $end 23:59:59");
    } elseif ($period === 'monthly') {
        $query .= " WHERE MONTH(s.timestamp) = MONTH(CURDATE()) AND YEAR(s.timestamp) = YEAR(CURDATE())";
        $most_sold_query .= " WHERE MONTH(s.timestamp) = MONTH(CURDATE()) AND YEAR(s.timestamp) = YEAR(CURDATE())";
        $start = date('Y-m-01');
        $end = date('Y-m-t');
        $period_label = "Monthly Report - " . date('F Y') . " ($start 00:00:00 to $end 23:59:59)";
        error_log("reports.php: Monthly report range - $start 00:00:00 to $end 23:59:59");
    } else { // yearly
        $query .= " WHERE YEAR(s.timestamp) = YEAR(CURDATE())";
        $most_sold_query .= " WHERE YEAR(s.timestamp) = YEAR(CURDATE())";
        $start = date('Y-01-01');
        $end = date('Y-12-31');
        $period_label = "Yearly Report - " . date('Y') . " ($start 00:00:00 to $end 23:59:59)";
        error_log("reports.php: Yearly report range - $start 00:00:00 to $end 23:59:59");
    }

    $query .= " ORDER BY s.timestamp DESC";
    $most_sold_query .= " GROUP BY p.id, p.name ORDER BY total_quantity DESC, p.name ASC LIMIT 1";
    
    // Fetch report data
    $stmt = $pdo->query($query);
    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch most sold product
    $stmt = $pdo->query($most_sold_query);
    $most_sold_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $most_sold = $most_sold_data ? htmlspecialchars($most_sold_data['name']) . " ({$most_sold_data['total_quantity']} units)" : 'None';

    if (empty($report_data)) {
        $no_data = true;
    } else {
        // Calculate totals
        $transaction_ids = [];
        foreach ($report_data as $row) {
            $total_sales += $row['total'];
            $profit = ($row['selling_price'] - $row['buying_price']) * $row['quantity'];
            if ($row['selling_price'] < $row['buying_price']) {
                error_log("reports.php: Negative profit for product {$row['name']} (ID: {$row['product_id']})");
            }
            $total_profit += $profit;
            $total_buying_price += $row['buying_price'] * $row['quantity'];
            $total_selling_price += $row['selling_price'] * $row['quantity'];
            $transaction_ids[$row['transaction_id']] = true;
        }
        $avg_sale = count($transaction_ids) > 0 ? $total_sales / count($transaction_ids) : 0;
    }

    error_log("reports.php: Fetched " . count($report_data) . " rows for $period report");
} catch (PDOException $e) {
    error_log("reports.php: Query error - " . $e->getMessage());
    $_SESSION['error'] = "Failed to fetch report data. Check database.";
    $no_data = true;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reports - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .report-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        .report-buttons button {
            padding: 10px 20px;
            background-color: #2a2f43;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
            border-radius: 5px;
        }
        .report-buttons button:hover, .report-buttons button.active {
            background-color: #3e4563;
        }
        .report-buttons button.active {
            font-weight: bold;
        }
        .report-content {
            padding: 10px;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .report-table th, .report-table td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        .report-table th {
            background-color: #2a2f43;
            color: white;
            font-weight: bold;
        }
        .report-table td {
            background-color: #fff;
        }
        .report-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .report-table .right-align {
            text-align: right;
        }
        .report-table tfoot td {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .summary {
            margin-top: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .summary p {
            margin: 5px 0;
            font-size: 16px;
        }
        .summary strong {
            color: #2a2f43;
        }
        .no-data {
            text-align: center;
            padding: 20px;
            border: 2px solid #2a2f43;
            border-radius: 5px;
            background-color: #fff;
            color: #333;
            font-size: 18px;
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            .report-buttons {
                flex-wrap: wrap;
                justify-content: flex-start;
            }
            .report-buttons button {
                margin: 5px;
            }
            .report-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Reports</h1>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
            <?php endif; ?>
            <div class="report-buttons">
                <button onclick="window.location.href='reports.php?period=daily'" <?php echo $period === 'daily' ? 'class="active"' : ''; ?>>Daily</button>
                <button onclick="window.location.href='reports.php?period=weekly'" <?php echo $period === 'weekly' ? 'class="active"' : ''; ?>>Weekly</button>
                <button onclick="window.location.href='reports.php?period=monthly'" <?php echo $period === 'monthly' ? 'class="active"' : ''; ?>>Monthly</button>
                <button onclick="window.location.href='reports.php?period=yearly'" <?php echo $period === 'yearly' ? 'class="active"' : ''; ?>>Yearly</button>
            </div>
            <div class="report-content">
                <h2><?php echo htmlspecialchars($period_label); ?></h2>
                <?php if ($no_data): ?>
                    <div class="no-data">No sales data available for this period.</div>
                <?php else: ?>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Buying Price (Ksh)</th>
                                <th>Selling Price (Ksh)</th>
                                <th>Profits (Ksh)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td class="right-align"><?php echo $row['quantity']; ?></td>
                                    <td class="right-align"><?php echo number_format($row['buying_price'], 2); ?></td>
                                    <td class="right-align"><?php echo number_format($row['selling_price'], 2); ?></td>
                                    <td class="right-align"><?php echo number_format(($row['selling_price'] - $row['buying_price']) * $row['quantity'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td><strong>Totals</strong></td>
                                <td></td>
                                <td class="right-align"><strong><?php echo number_format($total_buying_price, 2); ?></strong></td>
                                <td class="right-align"><strong><?php echo number_format($total_selling_price, 2); ?></strong></td>
                                <td class="right-align"><strong><?php echo number_format($total_profit, 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                    <div class="summary">
                        <p><strong>Total Sales:</strong> Ksh <?php echo number_format($total_sales, 2); ?></p>
                        <p><strong>Total Profits:</strong> Ksh <?php echo number_format($total_profit, 2); ?></p>
                        <p><strong>Average Sale:</strong> Ksh <?php echo number_format($avg_sale, 2); ?></p>
                        <p><strong>Most Sold Product:</strong> <?php echo $most_sold; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>