<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Set timezone
date_default_timezone_set('Africa/Nairobi');

// Determine report period
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';
$start_date = $end_date = null;

switch ($period) {
    case 'daily':
        $start_date = date('Y-m-d 00:00:00');
        $end_date = date('Y-m-d 23:59:59');
        $title = 'Daily Report - ' . date('Y-m-d');
        break;
    case 'weekly':
        $start_date = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $end_date = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        $title = 'Weekly Report - ' . date('Y-m-d', strtotime($start_date)) . ' to ' . date('Y-m-d', strtotime($end_date));
        break;
    case 'monthly':
        $start_date = date('Y-m-d 00:00:00', strtotime('first day of this month'));
        $end_date = date('Y-m-d 23:59:59', strtotime('last day of this month'));
        $title = 'Monthly Report - ' . date('Y-m');
        break;
    case 'yearly':
        $start_date = date('Y-01-01 00:00:00');
        $end_date = date('Y-12-31 23:59:59');
        $title = 'Yearly Report - ' . date('Y');
        break;
    default:
        $period = 'daily';
        $start_date = date('Y-m-d 00:00:00');
        $end_date = date('Y-m-d 23:59:59');
        $title = 'Daily Report - ' . date('Y-m-d');
}

// Check if 'category' column exists in products table
$category_exists = false;
$stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'category'");
if ($stmt->rowCount() > 0) {
    $category_exists = true;
}

// Fetch Sales Data
$sales_query = "
    SELECT s.*, p.name, p.cost_price" . ($category_exists ? ", p.category" : "") . "
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE s.timestamp BETWEEN ? AND ?
    ORDER BY s.timestamp ASC";
$stmt = $pdo->prepare($sales_query);
$stmt->execute([$start_date, $end_date]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Sales Metrics
$total_sales = 0;
$total_profit = 0;
$products_sold = [];
$category_sales = [];
foreach ($sales as $sale) {
    $total_sales += $sale['total'];
    $profit = ($sale['selling_price'] - $sale['cost_price']) * $sale['quantity'];
    $total_profit += $profit;
    
    $products_sold[] = [
        'name' => $sale['name'],
        'quantity' => $sale['quantity'],
        'selling_price' => $sale['selling_price'],
        'cost_price' => $sale['cost_price'],
        'total' => $sale['total'],
        'profit' => $profit
    ];
    
    if ($category_exists) {
        $category_sales[$sale['category']] = ($category_sales[$sale['category']] ?? 0) + $sale['total'];
    }
}
$avg_sale = count($sales) > 0 ? $total_sales / count($sales) : 0;

// Most Sold Product
$product_counts = [];
foreach ($sales as $sale) {
    $product_counts[$sale['product_id']] = ($product_counts[$sale['product_id']] ?? 0) + $sale['quantity'];
}
$most_sold = null;
if (!empty($product_counts)) {
    $most_sold_id = array_keys($product_counts, max($product_counts))[0];
    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
    $stmt->execute([$most_sold_id]);
    $most_sold = $stmt->fetch();
    $most_sold['quantity'] = $product_counts[$most_sold_id];
}

// Inventory Report
$stmt = $pdo->prepare("
    SELECT p.name, p.cost_price, i.quantity" . ($category_exists ? ", p.category" : "") . "
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
");
$stmt->execute();
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_stock_value = 0;
$low_stock = [];
foreach ($inventory as $item) {
    $stock_value = $item['quantity'] !== null ? $item['quantity'] * $item['cost_price'] : 0;
    $total_stock_value += $stock_value;
    if ($item['quantity'] !== null && $item['quantity'] < 10) {
        $low_stock[] = $item;
    }
}

// Profit and Loss Report
$total_cogs = array_sum(array_map(fn($sale) => $sale['cost_price'] * $sale['quantity'], $sales));
$net_profit = $total_sales - $total_cogs;

// Sales Trends (Last 5 Periods)
$trends = [];
for ($i = 4; $i >= 0; $i--) {
    if ($period === 'daily') {
        $trend_start = date('Y-m-d 00:00:00', strtotime("-$i days"));
        $trend_end = date('Y-m-d 23:59:59', strtotime("-$i days"));
        $label = date('Y-m-d', strtotime("-$i days"));
    } elseif ($period === 'weekly') {
        $trend_start = date('Y-m-d 00:00:00', strtotime("monday -$i weeks"));
        $trend_end = date('Y-m-d 23:59:59', strtotime("sunday -$i weeks"));
        $label = "Week " . date('W', strtotime("-$i weeks"));
    } elseif ($period === 'monthly') {
        $trend_start = date('Y-m-d 00:00:00', strtotime("first day of -$i months"));
        $trend_end = date('Y-m-d 23:59:59', strtotime("last day of -$i months"));
        $label = date('Y-m', strtotime("-$i months"));
    } else { // yearly
        $trend_start = date('Y-01-01 00:00:00', strtotime("-$i years"));
        $trend_end = date('Y-12-31 23:59:59', strtotime("-$i years"));
        $label = date('Y', strtotime("-$i years"));
    }
    $stmt = $pdo->prepare("SELECT SUM(total) as total FROM sales WHERE timestamp BETWEEN ? AND ?");
    $stmt->execute([$trend_start, $trend_end]);
    $trends[$label] = $stmt->fetch()['total'] ?? 0;
}
// Simple Forecast (Average of last 5 periods)
$forecast = count($trends) > 0 ? array_sum($trends) / count($trends) : 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .number-align { text-align: right; }
        .summary-box { margin-bottom: 20px; }
        .summary-box p { margin: 5px 0; }
        .summary-box p strong { display: inline-block; width: 200px; }
        .summary-box p span.number-align { float: right; }
    </style>
</head>
<body>
    <div class="top-bar">
        <span>Welcome, <?php echo $_SESSION['username']; ?></span>
        <a href="logout.php" class="orange-btn">Logout</a>
    </div>
    <div class="container">
        <div class="sidebar">
            <h3>Menu</h3>
            <a href="dashboard.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
            <a href="sell.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'sell.php' ? 'active' : ''; ?>">Sell Products</a>
            <a href="inventory.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : ''; ?>">Manage Inventory</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="users.php" class="orange-btn <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">Manage Users</a>
            <?php endif; ?>
            <h3>Reports</h3>
            <a href="reports.php?period=daily" class="orange-btn <?php echo $period === 'daily' ? 'active' : ''; ?>">Daily Report</a>
            <a href="reports.php?period=weekly" class="orange-btn <?php echo $period === 'weekly' ? 'active' : ''; ?>">Weekly Report</a>
            <a href="reports.php?period=monthly" class="orange-btn <?php echo $period === 'monthly' ? 'active' : ''; ?>">Monthly Report</a>
            <a href="reports.php?period=yearly" class="orange-btn <?php echo $period === 'yearly' ? 'active' : ''; ?>">Yearly Report</a>
        </div>
        <div class="main-content">
            <h1><?php echo $title; ?></h1>

            <!-- Sales Report -->
            <div class="summary-box">
                <h3>Sales Report</h3>
                <p><strong>Total Sales:</strong> <span class="number-align">Ksh <?php echo number_format($total_sales, 2); ?></span></p>
                <p><strong>Average Sale Value:</strong> <span class="number-align">Ksh <?php echo number_format($avg_sale, 2); ?></span></p>
                <p><strong>Total Profit:</strong> <span class="number-align">Ksh <?php echo number_format($total_profit, 2); ?></span></p>
                <?php if ($category_exists && !empty($category_sales)): ?>
                    <h4>Sales by Category</h4>
                    <table>
                        <tr><th>Category</th><th>Total Sales (Ksh)</th></tr>
                        <?php foreach ($category_sales as $cat => $total): ?>
                            <tr>
                                <td><?php echo $cat; ?></td>
                                <td class="number-align"><?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Products Sold -->
            <div class="summary-box">
                <h3>Products Sold</h3>
                <?php if (empty($products_sold)): ?>
                    <p class="error">No sales recorded for this period.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Cost Price (Ksh)</th>
                            <th>Selling Price (Ksh)</th>
                            <th>Total (Ksh)</th>
                            <th>Profit (Ksh)</th>
                        </tr>
                        <?php foreach ($products_sold as $item): ?>
                            <tr>
                                <td><?php echo $item['name']; ?></td>
                                <td class="number-align"><?php echo number_format($item['quantity'], 0); ?></td>
                                <td class="number-align"><?php echo number_format($item['cost_price'], 2); ?></td>
                                <td class="number-align"><?php echo number_format($item['selling_price'], 2); ?></td>
                                <td class="number-align"><?php echo number_format($item['total'], 2); ?></td>
                                <td class="number-align"><?php echo number_format($item['profit'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
                <p><strong>Most Sold Product:</strong> <span class="number-align"><?php echo $most_sold ? "{$most_sold['name']} ({$most_sold['quantity']} units)" : 'N/A'; ?></span></p>
            </div>

            <!-- Inventory Report -->
            <div class="summary-box">
                <h3>Inventory Report</h3>
                <p><strong>Total Stock Value:</strong> <span class="number-align">Ksh <?php echo number_format($total_stock_value, 2); ?></span></p>
                <h4>Low Stock Alerts</h4>
                <?php if (empty($low_stock)): ?>
                    <p>No low stock items.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th>Product</th>
                            <?php if ($category_exists) echo "<th>Category</th>"; ?>
                            <th>Quantity</th>
                        </tr>
                        <?php foreach ($low_stock as $item): ?>
                            <tr>
                                <td><?php echo $item['name']; ?></td>
                                <?php if ($category_exists) echo "<td>{$item['category']}</td>"; ?>
                                <td class="number-align"><?php echo number_format($item['quantity'], 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Profit and Loss Report -->
            <div class="summary-box">
                <h3>Profit and Loss Report</h3>
                <p><strong>Revenue:</strong> <span class="number-align">Ksh <?php echo number_format($total_sales, 2); ?></span></p>
                <p><strong>Cost of Goods Sold (COGS):</strong> <span class="number-align">Ksh <?php echo number_format($total_cogs, 2); ?></span></p>
                <p><strong>Net Profit:</strong> <span class="number-align">Ksh <?php echo number_format($net_profit, 2); ?></span></p>
            </div>

            <!-- Sales Trends and Forecasting -->
            <div class="summary-box">
                <h3>Sales Trends (Last 5 <?php echo ucfirst($period === 'daily' ? 'Days' : ($period === 'weekly' ? 'Weeks' : ($period === 'monthly' ? 'Months' : 'Years'))); ?>)</h3>
                <table>
                    <tr><th>Period</th><th>Sales (Ksh)</th></tr>
                    <?php foreach ($trends as $label => $total): ?>
                        <tr>
                            <td><?php echo $label; ?></td>
                            <td class="number-align"><?php echo number_format($total, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p><strong>Next <?php echo ucfirst($period); ?> Forecast:</strong> <span class="number-align">Ksh <?php echo number_format($forecast, 2); ?></span></p>
            </div>
        </div>
    </div>
</body>
</html>