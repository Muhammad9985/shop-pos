<?php
require_once 'config/database.php';
require_once 'auth.php';
requirePermission('purchases');

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_POST) {
    if ($_POST['action'] == 'add_purchase') {
        $product_id = $_POST['product_id'];
        $quantity = $_POST['quantity'];
        $unit_price = $_POST['unit_price'];
        $sale_price = $_POST['sale_price'];
        $supplier = $_POST['supplier'];
        
        $query = "INSERT INTO purchases (product_id, quantity, unit_price, sale_price, supplier) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$product_id, $quantity, $unit_price, $sale_price, $supplier]);
        
        // Stock is now calculated from purchases - sales, no need to update manually
        
        header("Location: purchases.php");
        exit;
    }
    
    if ($_POST['action'] == 'edit_purchase') {
        $id = $_POST['id'];
        $product_id = $_POST['product_id'];
        $quantity = $_POST['quantity'];
        $unit_price = $_POST['unit_price'];
        $sale_price = $_POST['sale_price'];
        $supplier = $_POST['supplier'];
        
        $query = "UPDATE purchases SET product_id = ?, quantity = ?, unit_price = ?, sale_price = ?, supplier = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$product_id, $quantity, $unit_price, $sale_price, $supplier, $id]);
        
        header("Location: purchases.php");
        exit;
    }
}

$page_title = 'Purchases Management';
include 'includes/header.php';

// Get filters
$search = $_GET['search'] ?? '';
$supplier_filter = $_GET['supplier'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$product_filter = $_GET['product'] ?? '';

// Fetch products for dropdown
$query = "SELECT id, name, brand FROM products ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build search conditions
$conditions = [];
$params = [];

if ($search) {
    $conditions[] = "(pr.name LIKE ? OR pr.brand LIKE ? OR p.supplier LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($supplier_filter) {
    $conditions[] = "p.supplier LIKE ?";
    $params[] = "%$supplier_filter%";
}

if ($date_from) {
    $conditions[] = "DATE(p.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $conditions[] = "DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

if ($product_filter) {
    $conditions[] = "p.product_id = ?";
    $params[] = $product_filter;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Fetch purchases with filters
$query = "SELECT p.*, pr.name as product_name, pr.brand, DATE(p.created_at) as purchase_date, TIME(p.created_at) as purchase_time 
          FROM purchases p 
          LEFT JOIN products pr ON p.product_id = pr.id 
          $where_clause
          ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique suppliers for filter
$query = "SELECT DISTINCT supplier FROM purchases WHERE supplier IS NOT NULL AND supplier != '' ORDER BY supplier";
$stmt = $db->prepare($query);
$stmt->execute();
$suppliers = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-header">
    <h1 class="page-title">Purchases Management</h1>
    <div class="page-actions">
        <?php if (hasPermission('purchases', 'add')): ?>
        <button class="btn btn-primary" onclick="document.getElementById('addPurchaseModal').style.display='flex'">
            <i class="fas fa-plus"></i> Add Purchase
        </button>
        <?php endif; ?>
        <button class="btn btn-secondary" onclick="toggleFilters()">
            <i class="fas fa-filter"></i> Filters
        </button>
    </div>
</div>

<!-- Search and Filter Section -->
<div class="filter-section" id="filterSection" style="background: white; padding: 20px; border-radius: 8px; box-shadow: var(--box-shadow); margin-bottom: 20px;">
    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
        <div class="form-group" style="margin: 0;">
            <label>Search</label>
            <input type="text" name="search" class="form-control" placeholder="Product, brand, or supplier..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label>Product</label>
            <select name="product" class="form-control">
                <option value="">All Products</option>
                <?php foreach ($products as $product): ?>
                <option value="<?php echo $product['id']; ?>" <?php echo $product_filter == $product['id'] ? 'selected' : ''; ?>>
                    <?php echo $product['name']; ?><?php echo $product['brand'] ? ' - ' . $product['brand'] : ''; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label>Supplier</label>
            <select name="supplier" class="form-control">
                <option value="">All Suppliers</option>
                <?php foreach ($suppliers as $supplier): ?>
                <option value="<?php echo htmlspecialchars($supplier); ?>" <?php echo $supplier_filter == $supplier ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($supplier); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label>From Date</label>
            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
        </div>
        
        <div class="form-group" style="margin: 0;">
            <label>To Date</label>
            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            <a href="purchases.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Clear
            </a>
        </div>
    </form>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Product</th>
                <th>Brand</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Sale Price</th>
                <th>Total Cost</th>
                <th>Supplier</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($purchases)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: var(--gray); padding: 40px;">
                        <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>No purchases found matching your criteria</p>
                        <small>Try adjusting your search filters</small>
                    </td>
                </tr>
            <?php else: ?>
                <?php 
                $total_quantity = 0;
                $total_cost = 0;
                foreach ($purchases as $purchase): 
                    $total_quantity += $purchase['quantity'];
                    $total_cost += $purchase['quantity'] * $purchase['unit_price'];
                ?>
                <tr>
                    <td><?php echo date('M j, Y', strtotime($purchase['purchase_date'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($purchase['product_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($purchase['brand'] ?? 'N/A'); ?></td>
                    <td><span class="badge"><?php echo $purchase['quantity']; ?></span></td>
                    <td>Rs.<?php echo number_format($purchase['unit_price']); ?></td>
                    <td>Rs.<?php echo number_format($purchase['sale_price']); ?></td>
                    <td><strong>Rs.<?php echo number_format($purchase['quantity'] * $purchase['unit_price']); ?></strong></td>
                    <td><?php echo htmlspecialchars($purchase['supplier']); ?></td>
                    <td>
                        <?php if (hasPermission('purchases', 'edit')): ?>
                        <button class="btn btn-sm btn-primary" onclick="editPurchase(<?php echo htmlspecialchars(json_encode($purchase)); ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <!-- Summary Row -->
                <tr style="background: #f8f9fa; font-weight: bold; border-top: 2px solid var(--primary);">
                    <td colspan="3">TOTAL (<?php echo count($purchases); ?> records)</td>
                    <td><span class="badge" style="background: var(--success);"><?php echo $total_quantity; ?></span></td>
                    <td colspan="2"></td>
                    <td><strong>Rs.<?php echo number_format($total_cost); ?></strong></td>
                    <td colspan="2"></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add Purchase Modal -->
<div class="modal" id="addPurchaseModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New Purchase</h3>
            <button class="close-modal" onclick="document.getElementById('addPurchaseModal').style.display='none'">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_purchase">
            
            <div class="form-group">
                <label>Product</label>
                <select name="product_id" class="form-control" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>">
                        <?php echo $product['name']; ?><?php echo $product['brand'] ? ' - ' . $product['brand'] : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" class="form-control" min="1" required>
            </div>
            
            <div class="form-group">
                <label>Unit Price (Rs.)</label>
                <input type="number" name="unit_price" class="form-control" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label>Sale Price (Rs.)</label>
                <input type="number" name="sale_price" class="form-control" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label>Supplier</label>
                <input type="text" name="supplier" class="form-control" required>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-danger" style="flex: 1;" onclick="document.getElementById('addPurchaseModal').style.display='none'">
                    Cancel
                </button>
                <button type="submit" class="btn btn-success" style="flex: 1;">
                    <i class="fas fa-save"></i> Save Purchase
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleFilters() {
    const filterSection = document.getElementById('filterSection');
    if (filterSection.style.display === 'none') {
        filterSection.style.display = 'block';
    } else {
        filterSection.style.display = 'none';
    }
}

// Auto-hide filters if no filters are active
document.addEventListener('DOMContentLoaded', function() {
    const hasFilters = <?php echo json_encode(!empty($search) || !empty($supplier_filter) || !empty($date_from) || !empty($date_to) || !empty($product_filter)); ?>;
    if (!hasFilters) {
        document.getElementById('filterSection').style.display = 'none';
    }
});

function editPurchase(purchase) {
    document.getElementById('edit_purchase_id').value = purchase.id;
    document.getElementById('edit_purchase_product_id').value = purchase.product_id;
    document.getElementById('edit_purchase_quantity').value = purchase.quantity;
    document.getElementById('edit_purchase_unit_price').value = purchase.unit_price;
    document.getElementById('edit_purchase_sale_price').value = purchase.sale_price;
    document.getElementById('edit_purchase_supplier').value = purchase.supplier;
    document.getElementById('editPurchaseModal').style.display = 'flex';
}
</script>

<!-- Edit Purchase Modal -->
<div class="modal" id="editPurchaseModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Purchase</h3>
            <button class="close-modal" onclick="document.getElementById('editPurchaseModal').style.display='none'">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_purchase">
            <input type="hidden" name="id" id="edit_purchase_id">
            
            <div class="form-group">
                <label>Product</label>
                <select name="product_id" id="edit_purchase_product_id" class="form-control" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?php echo $product['id']; ?>">
                        <?php echo $product['name']; ?><?php echo $product['brand'] ? ' - ' . $product['brand'] : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" id="edit_purchase_quantity" class="form-control" min="1" required>
            </div>
            
            <div class="form-group">
                <label>Unit Price (Rs.)</label>
                <input type="number" name="unit_price" id="edit_purchase_unit_price" class="form-control" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label>Sale Price (Rs.)</label>
                <input type="number" name="sale_price" id="edit_purchase_sale_price" class="form-control" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label>Supplier</label>
                <input type="text" name="supplier" id="edit_purchase_supplier" class="form-control" required>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-danger" style="flex: 1;" onclick="document.getElementById('editPurchaseModal').style.display='none'">
                    Cancel
                </button>
                <button type="submit" class="btn btn-success" style="flex: 1;">
                    <i class="fas fa-save"></i> Update Purchase
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.badge {
    background: var(--primary);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.filter-section {
    transition: all 0.3s ease;
}

@media (max-width: 768px) {
    .filter-section form {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>