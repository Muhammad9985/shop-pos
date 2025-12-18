<?php
require_once 'config/database.php';
require_once 'auth.php';
requirePermission('daily-sales');
$page_title = 'Sales Analytics Dashboard';
include 'includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Get filters
$selected_date = $_GET['date'] ?? date('Y-m-d');
$date_range = $_GET['range'] ?? 'today';
$category_filter = $_GET['category'] ?? 'all';

// Calculate date range
switch($date_range) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        break;
    case 'month':
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-d');
        break;
    case 'year':
        $start_date = date('Y-01-01');
        $end_date = date('Y-m-d');
        break;
    default:
        $start_date = $end_date = $selected_date;
}

// Build category filter
$category_condition = $category_filter != 'all' ? 'AND c.id = ?' : '';
$params = $category_filter != 'all' ? [$start_date, $end_date, $category_filter] : [$start_date, $end_date];

// Get sales summary
$query = "SELECT COUNT(*) as total_items, SUM(s.price) as total_amount, COUNT(DISTINCT s.transaction_id) as total_transactions,
          AVG(s.price) as avg_price, MAX(s.price) as max_price, MIN(s.price) as min_price
          FROM sales s 
          LEFT JOIN products p ON s.product_id = p.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE DATE(s.created_at) BETWEEN ? AND ? $category_condition";
$stmt = $db->prepare($query);
$stmt->execute($params);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Get hourly sales for chart
$query = "SELECT HOUR(s.created_at) as hour, COUNT(*) as sales_count, SUM(s.price) as hourly_total
          FROM sales s 
          LEFT JOIN products p ON s.product_id = p.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE DATE(s.created_at) BETWEEN ? AND ? $category_condition
          GROUP BY HOUR(s.created_at) ORDER BY hour";
$stmt = $db->prepare($query);
$stmt->execute($params);
$hourly_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top products
$query = "SELECT p.name, p.brand, c.name as category, COUNT(*) as sold_count, SUM(s.price) as total_revenue
          FROM sales s 
          LEFT JOIN products p ON s.product_id = p.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE DATE(s.created_at) BETWEEN ? AND ? $category_condition
          GROUP BY p.id ORDER BY sold_count DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute($params);
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category sales
$query = "SELECT c.name as category_name, COUNT(s.id) as item_count, SUM(s.price) as category_total
          FROM sales s 
          LEFT JOIN products p ON s.product_id = p.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE DATE(s.created_at) BETWEEN ? AND ? $category_condition
          GROUP BY c.id, c.name ORDER BY category_total DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$category_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for filter
$query = "SELECT * FROM categories ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent transactions
$query = "SELECT s.transaction_id, s.created_at, COUNT(*) as items, SUM(s.price) as total
          FROM sales s 
          LEFT JOIN products p ON s.product_id = p.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE DATE(s.created_at) BETWEEN ? AND ? $category_condition
          GROUP BY s.transaction_id ORDER BY s.created_at DESC LIMIT 20";
$stmt = $db->prepare($query);
$stmt->execute($params);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title">Sales Analytics Dashboard</h1>
    <div class="page-actions">
        <select id="dateRange" class="btn" style="background: white; border: 1px solid var(--light-gray); margin-right: 10px;" onchange="updateFilters()">
            <option value="today" <?php echo $date_range == 'today' ? 'selected' : ''; ?>>Today</option>
            <option value="week" <?php echo $date_range == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
            <option value="month" <?php echo $date_range == 'month' ? 'selected' : ''; ?>>This Month</option>
            <option value="year" <?php echo $date_range == 'year' ? 'selected' : ''; ?>>This Year</option>
        </select>
        <select id="categoryFilter" class="btn" style="background: white; border: 1px solid var(--light-gray); margin-right: 10px;" onchange="updateFilters()">
            <option value="all" <?php echo $category_filter == 'all' ? 'selected' : ''; ?>>All Categories</option>
            <?php foreach($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" id="customDate" class="btn" value="<?php echo $selected_date; ?>" 
               style="background: white; border: 1px solid var(--light-gray); margin-right: 10px;"
               onchange="updateFilters()">
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="dashboard-cards">
    <div class="card">
        <div class="card-title">Total Revenue</div>
        <div class="card-value">Rs.<?php echo number_format($summary['total_amount'] ?? 0); ?></div>
        <div style="font-size: 0.9rem; color: var(--success);">
            <i class="fas fa-chart-line"></i> <?php echo $summary['total_items'] ?? 0; ?> items sold
        </div>
    </div>
    <div class="card">
        <div class="card-title">Transactions</div>
        <div class="card-value"><?php echo $summary['total_transactions'] ?? 0; ?></div>
        <div style="font-size: 0.9rem; color: var(--primary);">
            <i class="fas fa-calculator"></i> Avg: Rs.<?php echo number_format(($summary['total_amount'] ?? 0) / max(1, $summary['total_transactions'] ?? 1)); ?>
        </div>
    </div>
    <div class="card">
        <div class="card-title">Price Range</div>
        <div class="card-value">Rs.<?php echo number_format($summary['max_price'] ?? 0); ?></div>
        <div style="font-size: 0.9rem; color: var(--warning);">
            <i class="fas fa-arrow-down"></i> Min: Rs.<?php echo number_format($summary['min_price'] ?? 0); ?>
        </div>
    </div>
    <div class="card">
        <div class="card-title">Top Category</div>
        <div class="card-value"><?php echo $category_sales[0]['category_name'] ?? 'N/A'; ?></div>
        <div style="font-size: 0.9rem; color: var(--success);">
            <i class="fas fa-trophy"></i> Rs.<?php echo number_format($category_sales[0]['category_total'] ?? 0); ?>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 2rem;">
    <div class="table-container">
        <h3 style="padding: 15px 20px 0;">Hourly Sales Trend</h3>
        <div style="padding: 20px;">
            <canvas id="hourlyChart" width="400" height="200"></canvas>
        </div>
    </div>
    <div class="table-container">
        <h3 style="padding: 15px 20px 0;">Category Distribution</h3>
        <div style="padding: 20px;">
            <canvas id="categoryChart" width="300" height="200"></canvas>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 2rem;">
    <div class="table-container">
        <h3 style="padding: 15px 20px 0;">Top Selling Products</h3>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Brand</th>
                    <th>Sold</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_products as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td><?php echo htmlspecialchars($product['brand'] ?? 'N/A'); ?></td>
                    <td><span class="badge"><?php echo $product['sold_count']; ?></span></td>
                    <td>Rs.<?php echo number_format($product['total_revenue']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="table-container">
        <h3 style="padding: 15px 20px 0;">Recent Transactions</h3>
        <table>
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Time</th>
                    <th>Items</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_transactions as $txn): ?>
                <tr>
                    <td><?php echo htmlspecialchars($txn['transaction_id']); ?></td>
                    <td><?php echo date('H:i', strtotime($txn['created_at'])); ?></td>
                    <td><span class="badge"><?php echo $txn['items']; ?></span></td>
                    <td>Rs.<?php echo number_format($txn['total']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($category_sales)): ?>
<div class="table-container">
    <h3 style="padding: 15px 20px 0;">Category Performance</h3>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Items Sold</th>
                <th>Revenue</th>
                <th>Avg Price</th>
                <th>Market Share</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($category_sales as $category): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($category['category_name']); ?></strong></td>
                <td><span class="badge"><?php echo $category['item_count']; ?></span></td>
                <td>Rs.<?php echo number_format($category['category_total']); ?></td>
                <td>Rs.<?php echo number_format($category['category_total'] / max(1, $category['item_count'])); ?></td>
                <td>
                    <?php 
                    $percentage = ($summary['total_amount'] > 0) ? 
                                  ($category['category_total'] / $summary['total_amount'] * 100) : 0;
                    echo number_format($percentage, 1); 
                    ?>%
                    <div class="progress-bar" style="width: 100px; height: 6px; background: #eee; border-radius: 3px; margin-top: 5px;">
                        <div style="width: <?php echo $percentage; ?>%; height: 100%; background: var(--primary); border-radius: 3px;"></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Hourly Sales Chart
const hourlyData = <?php echo json_encode($hourly_sales); ?>;
const hours = Array.from({length: 24}, (_, i) => i);
const salesData = hours.map(hour => {
    const found = hourlyData.find(d => parseInt(d.hour) === hour);
    return found ? parseInt(found.hourly_total) : 0;
});

const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
new Chart(hourlyCtx, {
    type: 'line',
    data: {
        labels: hours.map(h => h + ':00'),
        datasets: [{
            label: 'Sales (Rs.)',
            data: salesData,
            borderColor: '#2d4059',
            backgroundColor: 'rgba(45, 64, 89, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true },
            x: { display: true }
        }
    }
});

// Category Pie Chart
const categoryData = <?php echo json_encode($category_sales); ?>;
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: categoryData.map(c => c.category_name),
        datasets: [{
            data: categoryData.map(c => parseInt(c.category_total)),
            backgroundColor: ['#2d4059', '#ea5455', '#28a745', '#ff9e00', '#6c757d']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

function updateFilters() {
    const range = document.getElementById('dateRange').value;
    const category = document.getElementById('categoryFilter').value;
    const date = document.getElementById('customDate').value;
    
    let url = 'daily-sales.php?';
    if (range !== 'today') url += 'range=' + range + '&';
    if (category !== 'all') url += 'category=' + category + '&';
    if (range === 'today') url += 'date=' + date;
    
    window.location.href = url;
}
</script>

<style>
.badge {
    background: var(--primary);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.progress-bar {
    display: inline-block;
}

@media print {
    .page-actions, .chart-container { display: none; }
}
</style>

<?php include 'includes/footer.php'; ?>