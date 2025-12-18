<?php
require_once 'config/database.php';
require_once 'auth.php';
requirePermission('dashboard');
$page_title = 'Business Dashboard';
include 'includes/header.php';

$database = new Database();
$db = $database->getConnection();

$today = date('Y-m-d');
$this_month = date('Y-m-01');
$last_month = date('Y-m-01', strtotime('-1 month'));

// Today's stats
$query = "SELECT COUNT(*) as total_sales, SUM(price) as total_amount, COUNT(DISTINCT transaction_id) as transactions FROM sales WHERE DATE(created_at) = ?";
$stmt = $db->prepare($query);
$stmt->execute([$today]);
$today_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// This month stats
$query = "SELECT COUNT(*) as total_sales, SUM(price) as total_amount FROM sales WHERE DATE(created_at) >= ?";
$stmt = $db->prepare($query);
$stmt->execute([$this_month]);
$month_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Last month for comparison
$query = "SELECT SUM(price) as total_amount FROM sales WHERE DATE(created_at) >= ? AND DATE(created_at) < ?";
$stmt = $db->prepare($query);
$stmt->execute([$last_month, $this_month]);
$last_month_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Low stock products
$query = "SELECT p.name, p.brand, 
          COALESCE((SELECT SUM(quantity) FROM purchases WHERE product_id = p.id), 0) - 
          COALESCE((SELECT COUNT(*) FROM sales WHERE product_id = p.id), 0) as current_stock
          FROM products p 
          HAVING current_stock <= 5 AND current_stock >= 0
          ORDER BY current_stock ASC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute();
$low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top products this month
$query = "SELECT p.name, p.brand, COUNT(*) as sold_count, SUM(s.price) as revenue
          FROM sales s 
          LEFT JOIN products p ON s.product_id = p.id 
          WHERE DATE(s.created_at) >= ?
          GROUP BY p.id ORDER BY sold_count DESC LIMIT 5";
$stmt = $db->prepare($query);
$stmt->execute([$this_month]);
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Daily sales for chart (last 7 days)
$query = "SELECT DATE(created_at) as date, SUM(price) as daily_total
          FROM sales 
          WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
          GROUP BY DATE(created_at) ORDER BY date";
$stmt = $db->prepare($query);
$stmt->execute();
$daily_chart = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Category performance
$query = "SELECT c.name, COUNT(s.id) as sales_count, SUM(s.price) as revenue
          FROM sales s 
          LEFT JOIN products p ON s.product_id = p.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE DATE(s.created_at) >= ?
          GROUP BY c.id ORDER BY revenue DESC";
$stmt = $db->prepare($query);
$stmt->execute([$this_month]);
$category_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent transactions
$query = "SELECT transaction_id, created_at, COUNT(*) as items, SUM(price) as total
          FROM sales 
          WHERE DATE(created_at) = ?
          GROUP BY transaction_id ORDER BY created_at DESC LIMIT 8";
$stmt = $db->prepare($query);
$stmt->execute([$today]);
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate growth
$growth = 0;
if ($last_month_stats['total_amount'] > 0) {
    $growth = (($month_stats['total_amount'] - $last_month_stats['total_amount']) / $last_month_stats['total_amount']) * 100;
}
?>

<div class="page-header">
    <h1 class="page-title">Business Dashboard</h1>
    <div class="page-actions">
        <div class="date-display">
            <i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?>
        </div>
        <button class="btn btn-primary" onclick="window.location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

<!-- KPI Cards -->
<div class="dashboard-cards">
    <div class="card gradient-card">
        <div class="card-icon"><i class="fas fa-chart-line"></i></div>
        <div class="card-content">
            <div class="card-title">Today's Revenue</div>
            <div class="card-value">Rs.<?php echo number_format($today_stats['total_amount'] ?? 0); ?></div>
            <div class="card-subtitle">
                <i class="fas fa-shopping-bag"></i> <?php echo $today_stats['total_sales'] ?? 0; ?> items • <?php echo $today_stats['transactions'] ?? 0; ?> transactions
            </div>
        </div>
    </div>
    
    <div class="card gradient-card">
        <div class="card-icon"><i class="fas fa-calendar-month"></i></div>
        <div class="card-content">
            <div class="card-title">Monthly Revenue</div>
            <div class="card-value">Rs.<?php echo number_format($month_stats['total_amount'] ?? 0); ?></div>
            <div class="card-subtitle">
                <i class="fas fa-<?php echo $growth >= 0 ? 'arrow-up' : 'arrow-down'; ?>" style="color: <?php echo $growth >= 0 ? 'var(--success)' : 'var(--secondary)'; ?>"></i>
                <?php echo number_format(abs($growth), 1); ?>% vs last month
            </div>
        </div>
    </div>
    
    <div class="card gradient-card">
        <div class="card-icon"><i class="fas fa-boxes"></i></div>
        <div class="card-content">
            <div class="card-title">Low Stock Alert</div>
            <div class="card-value"><?php echo count($low_stock); ?></div>
            <div class="card-subtitle">
                <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i> Products need restocking
            </div>
        </div>
    </div>
    
    <div class="card gradient-card">
        <div class="card-icon"><i class="fas fa-trophy"></i></div>
        <div class="card-content">
            <div class="card-title">Top Product</div>
            <div class="card-value"><?php echo $top_products[0]['sold_count'] ?? 0; ?></div>
            <div class="card-subtitle">
                <i class="fas fa-star"></i> <?php echo htmlspecialchars($top_products[0]['name'] ?? 'N/A'); ?>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Analytics -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 2rem;">
    <div class="table-container">
        <h3 style="padding: 15px 20px 0;"><i class="fas fa-chart-area"></i> Sales Trend (Last 7 Days)</h3>
        <div style="padding: 20px;">
            <canvas id="salesChart" width="400" height="200"></canvas>
        </div>
    </div>
    
    <div class="table-container">
        <h3 style="padding: 15px 20px 0;"><i class="fas fa-chart-pie"></i> Category Performance</h3>
        <div style="padding: 20px;">
            <canvas id="categoryChart" width="300" height="200"></canvas>
        </div>
    </div>
</div>

<!-- Bottom Section -->
<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
    <!-- Recent Transactions -->
    <div class="table-container">
        <h3 style="padding: 15px 20px 0;"><i class="fas fa-receipt"></i> Recent Transactions</h3>
        <div style="padding: 15px 20px;">
            <?php if (empty($recent_transactions)): ?>
                <div style="text-align: center; color: var(--gray); padding: 20px;">
                    <i class="fas fa-receipt" style="font-size: 2rem; opacity: 0.3; margin-bottom: 10px;"></i>
                    <p>No transactions today</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_transactions as $txn): ?>
                <div class="transaction-item">
                    <div class="transaction-info">
                        <strong><?php echo htmlspecialchars($txn['transaction_id']); ?></strong>
                        <small><?php echo date('h:i A', strtotime($txn['created_at'])); ?></small>
                    </div>
                    <div class="transaction-details">
                        <span class="badge"><?php echo $txn['items']; ?> items</span>
                        <strong>Rs.<?php echo number_format($txn['total']); ?></strong>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Top Products -->
    <div class="table-container">
        <h3 style="padding: 15px 20px 0;"><i class="fas fa-fire"></i> Top Products</h3>
        <div style="padding: 15px 20px;">
            <?php foreach ($top_products as $index => $product): ?>
            <div class="top-product-item">
                <div class="product-rank">#<?php echo $index + 1; ?></div>
                <div class="product-info">
                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                    <small><?php echo htmlspecialchars($product['brand'] ?? 'No Brand'); ?></small>
                </div>
                <div class="product-stats">
                    <span class="badge"><?php echo $product['sold_count']; ?> sold</span>
                    <small>Rs.<?php echo number_format($product['revenue']); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Low Stock Alert -->
    <div class="table-container">
        <h3 style="padding: 15px 20px 0;"><i class="fas fa-exclamation-triangle"></i> Low Stock Alert</h3>
        <div style="padding: 15px 20px;">
            <?php if (empty($low_stock)): ?>
                <div style="text-align: center; color: var(--success); padding: 20px;">
                    <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <p>All products well stocked!</p>
                </div>
            <?php else: ?>
                <?php foreach ($low_stock as $product): ?>
                <div class="stock-alert-item">
                    <div class="stock-info">
                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                        <small><?php echo htmlspecialchars($product['brand'] ?? 'No Brand'); ?></small>
                    </div>
                    <div class="stock-level">
                        <span class="badge" style="background: <?php echo $product['current_stock'] == 0 ? 'var(--secondary)' : 'var(--warning)'; ?>">
                            <?php echo $product['current_stock']; ?> left
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Sales Trend Chart
const dailyData = <?php echo json_encode($daily_chart); ?>;
const last7Days = [];
const salesValues = [];

for (let i = 6; i >= 0; i--) {
    const date = new Date();
    date.setDate(date.getDate() - i);
    const dateStr = date.toISOString().split('T')[0];
    last7Days.push(date.toLocaleDateString('en-US', { weekday: 'short' }));
    
    const found = dailyData.find(d => d.date === dateStr);
    salesValues.push(found ? parseInt(found.daily_total) : 0);
}

const salesCtx = document.getElementById('salesChart').getContext('2d');
new Chart(salesCtx, {
    type: 'line',
    data: {
        labels: last7Days,
        datasets: [{
            label: 'Daily Sales (Rs.)',
            data: salesValues,
            borderColor: '#2d4059',
            backgroundColor: 'rgba(45, 64, 89, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#2d4059',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
            x: { grid: { display: false } }
        }
    }
});

// Category Performance Chart
const categoryData = <?php echo json_encode($category_performance); ?>;
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: categoryData.map(c => c.name),
        datasets: [{
            data: categoryData.map(c => parseInt(c.revenue)),
            backgroundColor: ['#2d4059', '#ea5455', '#28a745', '#ff9e00', '#6c757d'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true } }
        }
    }
});
</script>

<style>
.gradient-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
}

.gradient-card:nth-child(2) {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.gradient-card:nth-child(3) {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.gradient-card:nth-child(4) {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.card {
    display: flex;
    align-items: center;
    padding: 1.5rem;
    gap: 1rem;
}

.card-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}

.card-content {
    flex: 1;
}

.card-title {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 5px;
}

.card-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.card-subtitle {
    font-size: 0.8rem;
    opacity: 0.8;
}

.transaction-item, .top-product-item, .stock-alert-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.transaction-item:last-child, .top-product-item:last-child, .stock-alert-item:last-child {
    border-bottom: none;
}

.product-rank {
    width: 30px;
    height: 30px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9rem;
}

.badge {
    background: var(--primary);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}
</style>

<?php include 'includes/footer.php'; ?>