<?php
// reports.php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$is_admin_or_manager = in_array($user_role, ['admin', 'manager']);

// Check if user_id column exists in sales table
$user_id_exists = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM sales LIKE 'user_id'");
    $user_id_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Handle AJAX requests
if (isset($_GET['ajax']) && isset($_GET['period'])) {
    $period = $_GET['period'];
    $start_date = $end_date = null;

    switch ($period) {
        case 'daily':
            $start_date = date('Y-m-d 00:00:00');
            $end_date = date('Y-m-d 23:59:59');
            break;
        case 'weekly':
            $start_date = date('Y-m-d 00:00:00', strtotime('monday this week'));
            $end_date = date('Y-m-d 23:59:59', strtotime('sunday this week'));
            break;
        case 'monthly':
            $start_date = date('Y-m-01 00:00:00');
            $end_date = date('Y-m-t 23:59:59');
            break;
        case 'yearly':
            $start_date = date('Y-01-01 00:00:00');
            $end_date = date('Y-12-31 23:59:59');
            break;
        default:
            $period = 'daily';
            $start_date = date('Y-m-d 00:00:00');
            $end_date = date('Y-m-d 23:59:59');
    }

    $category_exists = false;
    if ($is_admin_or_manager) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'category'");
            if ($stmt->rowCount() > 0) {
                $category_exists = true;
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
        }
    }

    // Sales query
    $sales_query = "
        SELECT s.id, s.product_id, s.quantity, s.selling_price, s.total, s.timestamp, p.name, p.cost_price" . ($category_exists ? ", p.category" : "") . "
        FROM sales s
        JOIN products p ON s.product_id = p.id
        WHERE s.timestamp BETWEEN ? AND ?" . ($is_admin_or_manager || !$user_id_exists ? "" : " AND s.user_id = ?") . "
        ORDER BY s.timestamp DESC";
    $params = [$start_date, $end_date];
    if (!$is_admin_or_manager && $user_id_exists) {
        $params[] = $user_id;
    }
    $stmt = $pdo->prepare($sales_query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();

    $total_sales = array_sum(array_column($sales, 'total'));
    $total_profit = 0;
    foreach ($sales as $sale) {
        $total_profit += ($sale['selling_price'] - $sale['cost_price']) * $sale['quantity'];
    }

    // Inventory, trends, and category data only for admins/managers
    $inventory = $trends = $category_sales = [];
    if ($is_admin_or_manager) {
        $inventory_query = "
            SELECT p.id, p.name, p.cost_price, p.selling_price, COALESCE(i.quantity, 0) AS quantity
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id";
        $stmt = $pdo->prepare($inventory_query);
        $stmt->execute();
        $inventory = $stmt->fetchAll();

        $trend_query = "
            SELECT DATE_FORMAT(s.timestamp, '%Y-%m-%d') AS date, SUM(s.total) AS daily_total
            FROM sales s
            WHERE s.timestamp BETWEEN ? AND ?
            GROUP BY DATE(s.timestamp)
            ORDER BY s.timestamp";
        $stmt = $pdo->prepare($trend_query);
        $stmt->execute([$start_date, $end_date]);
        $trends_result = $stmt->fetchAll();
        foreach ($trends_result as $trend) {
            $trends[$trend['date']] = $trend['daily_total'];
        }

        if ($category_exists) {
            $category_query = "
                SELECT p.category, SUM(s.total) AS total, SUM(s.quantity) AS quantity
                FROM sales s
                JOIN products p ON s.product_id = p.id
                WHERE s.timestamp BETWEEN ? AND ?
                GROUP BY p.category";
            $stmt = $pdo->prepare($category_query);
            $stmt->execute([$start_date, $end_date]);
            $category_sales = $stmt->fetchAll();
        }
    }

    // Output JSON for AJAX
    ob_start();
    ?>
    <h2><?php echo ucfirst($period); ?> Report</h2>
    <h3>Sales Overview</h3>
    <p>Total Sales: Ksh <?php echo number_format($total_sales, 2); ?></p>
    <?php if ($is_admin_or_manager): ?>
        <p>Total Profit: Ksh <?php echo number_format($total_profit, 2); ?></p>
    <?php endif; ?>
    <h3>Sales Details</h3>
    <div class="table-responsive">
        <table id="sales-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <?php if ($category_exists && $is_admin_or_manager): ?>
                        <th>Category</th>
                    <?php endif; ?>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sales)): ?>
                    <tr><td colspan="<?php echo $category_exists && $is_admin_or_manager ? 7 : 6; ?>">No sales found for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><?php echo $sale['id']; ?></td>
                            <td><?php echo htmlspecialchars($sale['name']); ?></td>
                            <?php if ($category_exists && $is_admin_or_manager): ?>
                                <td><?php echo htmlspecialchars($sale['category'] ?? 'N/A'); ?></td>
                            <?php endif; ?>
                            <td><?php echo $sale['quantity']; ?></td>
                            <td>Ksh <?php echo number_format($sale['selling_price'], 2); ?></td>
                            <td>Ksh <?php echo number_format($sale['total'], 2); ?></td>
                            <td><?php echo $sale['timestamp']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($is_admin_or_manager): ?>
        <h3>Inventory Status</h3>
        <div class="table-responsive">
            <table id="inventory-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Cost Price</th>
                        <th>Selling Price</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventory)): ?>
                        <tr><td colspan="4">No inventory data available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td>Ksh <?php echo number_format($item['cost_price'], 2); ?></td>
                                <td>Ksh <?php echo number_format($item['selling_price'], 2); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <h3>Sales Trends</h3>
        <div class="table-responsive">
            <table id="trends-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trends)): ?>
                        <tr><td colspan="2">No sales trends available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($trends as $date => $total): ?>
                            <tr>
                                <td><?php echo $date; ?></td>
                                <td>Ksh <?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($category_exists && !empty($category_sales)): ?>
            <h3>Sales by Category</h3>
            <div class="table-responsive">
                <table id="category-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Quantity Sold</th>
                            <th>Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category_sales as $cat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cat['category'] ?? 'N/A'); ?></td>
                                <td><?php echo $cat['quantity']; ?></td>
                                <td>Ksh <?php echo number_format($cat['total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php
    $html = ob_get_clean();
    echo json_encode(['html' => $html, 'period' => $period]);
    exit;
}

// Initial page load
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';
$start_date = $end_date = null;

switch ($period) {
    case 'daily':
        $start_date = date('Y-m-d 00:00:00');
        $end_date = date('Y-m-d 23:59:59');
        break;
    case 'weekly':
        $start_date = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $end_date = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        break;
    case 'monthly':
        $start_date = date('Y-m-01 00:00:00');
        $end_date = date('Y-m-t 23:59:59');
        break;
    case 'yearly':
        $start_date = date('Y-01-01 00:00:00');
        $end_date = date('Y-12-31 23:59:59');
        break;
    default:
        $period = 'daily';
        $start_date = date('Y-m-d 00:00:00');
        $end_date = date('Y-m-d 23:59:59');
}

$category_exists = false;
if ($is_admin_or_manager) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'category'");
        if ($stmt->rowCount() > 0) {
            $category_exists = true;
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

// Sales query
$sales_query = "
    SELECT s.id, s.product_id, s.quantity, s.selling_price, s.total, s.timestamp, p.name, p.cost_price" . ($category_exists ? ", p.category" : "") . "
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE s.timestamp BETWEEN ? AND ?" . ($is_admin_or_manager || !$user_id_exists ? "" : " AND s.user_id = ?") . "
    ORDER BY s.timestamp DESC";
$params = [$start_date, $end_date];
if (!$is_admin_or_manager && $user_id_exists) {
    $params[] = $user_id;
}
$stmt = $pdo->prepare($sales_query);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$total_sales = array_sum(array_column($sales, 'total'));
$total_profit = 0;
foreach ($sales as $sale) {
    $total_profit += ($sale['selling_price'] - $sale['cost_price']) * $sale['quantity'];
}

// Inventory, trends, and category data only for admins/managers
$inventory = $trends = $category_sales = [];
if ($is_admin_or_manager) {
    $inventory_query = "
        SELECT p.id, p.name, p.cost_price, p.selling_price, COALESCE(i.quantity, 0) AS quantity
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id";
    $stmt = $pdo->prepare($inventory_query);
    $stmt->execute();
    $inventory = $stmt->fetchAll();

    $trend_query = "
        SELECT DATE_FORMAT(s.timestamp, '%Y-%m-%d') AS date, SUM(s.total) AS daily_total
        FROM sales s
        WHERE s.timestamp BETWEEN ? AND ?
        GROUP BY DATE(s.timestamp)
        ORDER BY s.timestamp";
    $stmt = $pdo->prepare($trend_query);
    $stmt->execute([$start_date, $end_date]);
    $trends_result = $stmt->fetchAll();
    foreach ($trends_result as $trend) {
        $trends[$trend['date']] = $trend['daily_total'];
    }

    if ($category_exists) {
        $category_query = "
            SELECT p.category, SUM(s.total) AS total, SUM(s.quantity) AS quantity
            FROM sales s
            JOIN products p ON s.product_id = p.id
            WHERE s.timestamp BETWEEN ? AND ?
            GROUP BY p.category";
        $stmt = $pdo->prepare($category_query);
        $stmt->execute([$start_date, $end_date]);
        $category_sales = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - POS System</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="top-bar">
        <span>Welcome, <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</span>
        <a href="logout.php" class="orange-btn">Logout</a>
    </div>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Reports</h1>
            <div class="tab-bar">
                <a href="reports.php?period=daily" class="tab <?php echo $period === 'daily' ? 'active' : ''; ?>" data-period="daily">Daily</a>
                <a href="reports.php?period=weekly" class="tab <?php echo $period === 'weekly' ? 'active' : ''; ?>" data-period="weekly">Weekly</a>
                <a href="reports.php?period=monthly" class="tab <?php echo $period === 'monthly' ? 'active' : ''; ?>" data-period="monthly">Monthly</a>
                <a href="reports.php?period=yearly" class="tab <?php echo $period === 'yearly' ? 'active' : ''; ?>" data-period="yearly">Yearly</a>
            </div>
            <div id="report-content">
                <h2><?php echo ucfirst($period); ?> Report</h2>
                <h3>Sales Overview</h3>
                <p>Total Sales: Ksh <?php echo number_format($total_sales, 2); ?></p>
                <?php if ($is_admin_or_manager): ?>
                    <p>Total Profit: Ksh <?php echo number_format($total_profit, 2); ?></p>
                <?php endif; ?>
                <h3>Sales Details</h3>
                <div class="table-responsive">
                    <table id="sales-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product</th>
                                <?php if ($category_exists && $is_admin_or_manager): ?>
                                    <th>Category</th>
                                <?php endif; ?>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales)): ?>
                                <tr><td colspan="<?php echo $category_exists && $is_admin_or_manager ? 7 : 6; ?>">No sales found for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td><?php echo $sale['id']; ?></td>
                                        <td><?php echo htmlspecialchars($sale['name']); ?></td>
                                        <?php if ($category_exists && $is_admin_or_manager): ?>
                                            <td><?php echo htmlspecialchars($sale['category'] ?? 'N/A'); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo $sale['quantity']; ?></td>
                                        <td>Ksh <?php echo number_format($sale['selling_price'], 2); ?></td>
                                        <td>Ksh <?php echo number_format($sale['total'], 2); ?></td>
                                        <td><?php echo $sale['timestamp']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($is_admin_or_manager): ?>
                    <h3>Inventory Status</h3>
                    <div class="table-responsive">
                        <table id="inventory-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Cost Price</th>
                                    <th>Selling Price</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($inventory)): ?>
                                    <tr><td colspan="4">No inventory data available.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($inventory as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td>Ksh <?php echo number_format($item['cost_price'], 2); ?></td>
                                            <td>Ksh <?php echo number_format($item['selling_price'], 2); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <h3>Sales Trends</h3>
                    <div class="table-responsive">
                        <table id="trends-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Total Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($trends)): ?>
                                    <tr><td colspan="2">No sales trends available.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($trends as $date => $total): ?>
                                        <tr>
                                            <td><?php echo $date; ?></td>
                                            <td>Ksh <?php echo number_format($total, 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($category_exists && !empty($category_sales)): ?>
                        <h3>Sales by Category</h3>
                        <div class="table-responsive">
                            <table id="category-table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Quantity Sold</th>
                                        <th>Total Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($category_sales as $cat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cat['category'] ?? 'N/A'); ?></td>
                                            <td><?php echo $cat['quantity']; ?></td>
                                            <td>Ksh <?php echo number_format($cat['total'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            $('.tab').click(function(e) {
                e.preventDefault();
                const period = $(this).data('period');
                
                // Update active tab
                $('.tab').removeClass('active');
                $(this).addClass('active');
                
                // Update URL without reloading
                history.pushState(null, '', '?period=' + period);
                
                // Fetch report data via AJAX
                $.ajax({
                    url: 'reports.php',
                    type: 'GET',
                    data: { ajax: 1, period: period },
                    dataType: 'json',
                    success: function(response) {
                        $('#report-content').html(response.html);
                    },
                    error: function() {
                        $('#report-content').html('<p class="error">Failed to load report. Please try again.</p>');
                    }
                });
            });
        });
    </script>
</body>
</html>