<?php
require_once 'config/database.php';
require_once 'auth.php';
requirePermission('products');

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_POST) {
    if ($_POST['action'] == 'add_product' && hasPermission('products', 'add')) {
        $name = $_POST['name'];
        $category_id = $_POST['category_id'];
        $image = '';
        
        // Handle image upload
        if ($_FILES['image']['name']) {
            $target_dir = "uploads/products/";
            $image = time() . '_' . basename($_FILES['image']['name']);
            $target_file = $target_dir . $image;
            move_uploaded_file($_FILES['image']['tmp_name'], $target_file);
        }
        
        $brand = $_POST['brand'];
        
        $query = "INSERT INTO products (name, brand, category_id, image) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$name, $brand, $category_id, $image]);
        
        header("Location: products.php");
        exit;
    }
    
    if ($_POST['action'] == 'edit_product' && hasPermission('products', 'edit')) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $category_id = $_POST['category_id'];
        
        // Get current image
        $query = "SELECT image FROM products WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        $image = $current['image'];
        
        // Handle new image upload
        if ($_FILES['image']['name']) {
            $target_dir = "uploads/products/";
            $image = time() . '_' . basename($_FILES['image']['name']);
            $target_file = $target_dir . $image;
            move_uploaded_file($_FILES['image']['tmp_name'], $target_file);
        }
        
        $brand = $_POST['brand'];
        
        $query = "UPDATE products SET name = ?, brand = ?, category_id = ?, image = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$name, $brand, $category_id, $image, $id]);
        
        header("Location: products.php");
        exit;
    }
    
    if ($_POST['action'] == 'delete_product' && hasPermission('products', 'delete')) {
        $id = $_POST['id'];
        
        // Check if product has stock
        $query = "SELECT COALESCE((SELECT SUM(quantity) FROM purchases WHERE product_id = ?), 0) - 
                  COALESCE((SELECT COUNT(*) FROM sales WHERE product_id = ?), 0) as current_stock";
        $stmt = $db->prepare($query);
        $stmt->execute([$id, $id]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stock['current_stock'] > 0) {
            header("Location: products.php?error=has_stock");
            exit;
        }
        
        $query = "DELETE FROM products WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        
        header("Location: products.php");
        exit;
    }
}

$page_title = 'Products Management';
include 'includes/header.php';

// Get filters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$view_mode = $_GET['view'] ?? 'cards';

// Fetch categories
$query = "SELECT * FROM categories ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build search conditions
$conditions = [];
$params = [];

if ($search) {
    $conditions[] = "(p.name LIKE ? OR p.brand LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Fetch products with filters
$query = "SELECT p.*, c.name as category_name,
          COALESCE((SELECT SUM(quantity) FROM purchases WHERE product_id = p.id), 0) - 
          COALESCE((SELECT COUNT(*) FROM sales WHERE product_id = p.id), 0) as current_stock,
          (SELECT sale_price FROM purchases WHERE product_id = p.id ORDER BY created_at DESC LIMIT 1) as latest_price
          FROM products p LEFT JOIN categories c ON p.category_id = c.id 
          $where_clause
          ORDER BY p.name";
$stmt = $db->prepare($query);
$stmt->execute($params);
$all_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Apply stock filter
$products = [];
foreach ($all_products as $product) {
    if ($stock_filter == 'low' && $product['current_stock'] > 5) continue;
    if ($stock_filter == 'out' && $product['current_stock'] > 0) continue;
    if ($stock_filter == 'available' && $product['current_stock'] <= 0) continue;
    $products[] = $product;
}
?>

<div class="page-header">
    <h1 class="page-title">Products Inventory</h1>
    <div class="page-actions">
        <div class="view-toggle">
            <button class="btn <?php echo $view_mode == 'cards' ? 'btn-primary' : 'btn-secondary'; ?>" onclick="toggleView('cards')">
                <i class="fas fa-th-large"></i>
            </button>
            <button class="btn <?php echo $view_mode == 'table' ? 'btn-primary' : 'btn-secondary'; ?>" onclick="toggleView('table')">
                <i class="fas fa-list"></i>
            </button>
        </div>
        <?php if (hasPermission('products', 'add')): ?>
        <button class="btn btn-primary" onclick="document.getElementById('addProductModal').style.display='flex'">
            <i class="fas fa-plus"></i> Add Product
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Search and Filter Section -->
<div class="filter-section">
    <form method="GET" class="filter-form">
        <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="realTimeSearch" name="search" placeholder="Search products or brands..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        
        <select name="category" class="filter-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category): ?>
            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                <?php echo $category['name']; ?>
            </option>
            <?php endforeach; ?>
        </select>
        
        <select name="stock" class="filter-select">
            <option value="">All Stock Levels</option>
            <option value="available" <?php echo $stock_filter == 'available' ? 'selected' : ''; ?>>In Stock</option>
            <option value="low" <?php echo $stock_filter == 'low' ? 'selected' : ''; ?>>Low Stock (≤5)</option>
            <option value="out" <?php echo $stock_filter == 'out' ? 'selected' : ''; ?>>Out of Stock</option>
        </select>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i> Filter
        </button>
        
        <a href="products.php?view=<?php echo $view_mode; ?>" class="btn btn-secondary">
            <i class="fas fa-times"></i> Clear
        </a>
    </form>
</div>

<!-- Products Display -->
<div class="products-container">
    <?php if ($view_mode == 'cards'): ?>
    <!-- Card View -->
    <div class="products-grid">
        <?php if (empty($products)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h3>No products found</h3>
            <p>Try adjusting your search criteria or add new products</p>
        </div>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
            <div class="product-card <?php echo $product['current_stock'] <= 0 ? 'out-of-stock' : ($product['current_stock'] <= 5 ? 'low-stock' : ''); ?>">
                <div class="product-image">
                    <?php if ($product['image'] && file_exists('uploads/products/'.$product['image'])): ?>
                        <img src="uploads/products/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>">
                    <?php else: ?>
                        <div class="no-image">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                    <div class="stock-badge <?php echo $product['current_stock'] <= 0 ? 'out' : ($product['current_stock'] <= 5 ? 'low' : 'good'); ?>">
                        <?php echo $product['current_stock']; ?>
                    </div>
                </div>
                
                <div class="product-info">
                    <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <?php if ($product['brand']): ?>
                    <p class="product-brand"><?php echo htmlspecialchars($product['brand']); ?></p>
                    <?php endif; ?>
                    <p class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                    <?php if ($product['latest_price']): ?>
                    <p class="product-price">Rs.<?php echo number_format($product['latest_price']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="product-actions">
                    <?php if (hasPermission('products', 'edit')): ?>
                    <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                        <i class="fas fa-edit"></i>
                    </button>
                    <?php endif; ?>
                    <?php if (hasPermission('products', 'delete')): ?>
                    <button class="btn btn-sm btn-danger" <?php echo $product['current_stock'] > 0 ? 'disabled title="Cannot delete product with stock"' : ''; ?> onclick="<?php echo $product['current_stock'] == 0 ? 'deleteProduct(' . $product['id'] . ')' : ''; ?>">
                        <i class="fas fa-trash"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php else: ?>
    <!-- Table View -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Product Details</th>
                    <th>Category</th>
                    <th>Stock Status</th>
                    <th>Latest Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                <tr>
                    <td colspan="6" class="empty-state-table">
                        <i class="fas fa-box-open"></i>
                        <p>No products found matching your criteria</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                    <tr class="<?php echo $product['current_stock'] <= 0 ? 'out-of-stock-row' : ($product['current_stock'] <= 5 ? 'low-stock-row' : ''); ?>">
                        <td>
                            <div class="table-product-image">
                                <?php if ($product['image'] && file_exists('uploads/products/'.$product['image'])): ?>
                                    <img src="uploads/products/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>">
                                <?php else: ?>
                                    <div class="no-image-small">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="product-details">
                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                <?php if ($product['brand']): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($product['brand']); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                        <td>
                            <span class="stock-badge-table <?php echo $product['current_stock'] <= 0 ? 'out' : ($product['current_stock'] <= 5 ? 'low' : 'good'); ?>">
                                <?php echo $product['current_stock']; ?> in stock
                            </span>
                        </td>
                        <td>
                            <?php if ($product['latest_price']): ?>
                            <strong>Rs.<?php echo number_format($product['latest_price']); ?></strong>
                            <?php else: ?>
                            <span class="text-muted">No price set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if (hasPermission('products', 'edit')): ?>
                                <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php endif; ?>
                                <?php if (hasPermission('products', 'delete')): ?>
                                <button class="btn btn-sm btn-danger" <?php echo $product['current_stock'] > 0 ? 'disabled title="Cannot delete product with stock"' : ''; ?> onclick="<?php echo $product['current_stock'] == 0 ? 'deleteProduct(' . $product['id'] . ')' : ''; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Add Product Modal -->
<div class="modal" id="addProductModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New Product</h3>
            <button class="close-modal" onclick="document.getElementById('addProductModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_product">
            
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Brand</label>
                <input type="text" name="brand" class="form-control">
            </div>
            
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-control" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            

            

            
            <div class="form-group">
                <label>Product Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-danger" style="flex: 1;" onclick="document.getElementById('addProductModal').style.display='none'">
                    Cancel
                </button>
                <button type="submit" class="btn btn-success" style="flex: 1;">
                    <i class="fas fa-save"></i> Save Product
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal" id="editProductModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Product</h3>
            <button class="close-modal" onclick="document.getElementById('editProductModal').style.display='none'">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_product">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Brand</label>
                <input type="text" name="brand" id="edit_brand" class="form-control">
            </div>
            
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" id="edit_category_id" class="form-control" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            

            

            
            <div class="form-group">
                <label>Product Image</label>
                <input type="file" name="image" class="form-control" accept="image/*">
                <small style="color: var(--gray);">Leave empty to keep current image</small>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-danger" style="flex: 1;" onclick="document.getElementById('editProductModal').style.display='none'">
                    Cancel
                </button>
                <button type="submit" class="btn btn-success" style="flex: 1;">
                    <i class="fas fa-save"></i> Update Product
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Product Modal -->
<div class="modal" id="deleteProductModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Delete Product</h3>
            <button class="close-modal" onclick="document.getElementById('deleteProductModal').style.display='none'">&times;</button>
        </div>
        <div style="padding: 20px; text-align: center;">
            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--warning); margin-bottom: 15px;"></i>
            <h3 style="margin-bottom: 10px;">Are you sure?</h3>
            <p style="color: var(--gray); margin-bottom: 20px;">Do you want to delete this product? This action cannot be undone.</p>
            <input type="hidden" id="deleteProductId">
        </div>
        <div style="display: flex; gap: 10px; margin-top: 20px; padding: 0 25px 25px;">
            <button type="button" class="btn btn-danger" style="flex: 1;" onclick="document.getElementById('deleteProductModal').style.display='none'">
                Cancel
            </button>
            <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="confirmDeleteProduct()">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
function editProduct(product) {
    document.getElementById('edit_id').value = product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_brand').value = product.brand || '';
    document.getElementById('edit_category_id').value = product.category_id;
    document.getElementById('editProductModal').style.display = 'flex';
}

function deleteProduct(id) {
    document.getElementById('deleteProductId').value = id;
    document.getElementById('deleteProductModal').style.display = 'flex';
}

function confirmDeleteProduct() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete_product">
        <input type="hidden" name="id" value="${document.getElementById('deleteProductId').value}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function toggleView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location.href = url.toString();
}

// Real-time search and filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('realTimeSearch');
    const selects = document.querySelectorAll('.filter-select');
    
    // Real-time search
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterProducts();
        });
    }
    
    // Auto-submit form on filter change
    selects.forEach(select => {
        select.addEventListener('change', function() {
            filterProducts();
        });
    });
});

function filterProducts() {
    const searchTerm = document.getElementById('realTimeSearch').value.toLowerCase();
    const categoryFilter = document.querySelector('select[name="category"]').value;
    const stockFilter = document.querySelector('select[name="stock"]').value;
    
    const productCards = document.querySelectorAll('.product-card');
    const tableRows = document.querySelectorAll('tbody tr:not(.empty-state-row)');
    
    let visibleCount = 0;
    
    // Filter cards
    productCards.forEach(card => {
        const productName = card.querySelector('.product-name')?.textContent.toLowerCase() || '';
        const productBrand = card.querySelector('.product-brand')?.textContent.toLowerCase() || '';
        const productCategory = card.querySelector('.product-category')?.textContent || '';
        const stockBadge = card.querySelector('.stock-badge')?.textContent || '0';
        const stockCount = parseInt(stockBadge);
        
        let show = true;
        
        // Search filter
        if (searchTerm && !productName.includes(searchTerm) && !productBrand.includes(searchTerm)) {
            show = false;
        }
        
        // Category filter
        if (categoryFilter) {
            const categoryName = document.querySelector(`option[value="${categoryFilter}"]`)?.textContent;
            if (categoryName && !productCategory.includes(categoryName)) {
                show = false;
            }
        }
        
        // Stock filter
        if (stockFilter === 'available' && stockCount <= 0) show = false;
        if (stockFilter === 'low' && stockCount > 5) show = false;
        if (stockFilter === 'out' && stockCount > 0) show = false;
        
        card.style.display = show ? 'block' : 'none';
        if (show) visibleCount++;
    });
    
    // Filter table rows
    tableRows.forEach(row => {
        const productDetails = row.querySelector('.product-details');
        if (!productDetails) return;
        
        const productName = productDetails.querySelector('strong')?.textContent.toLowerCase() || '';
        const productBrand = productDetails.querySelector('small')?.textContent.toLowerCase() || '';
        const categoryCell = row.cells[2]?.textContent || '';
        const stockBadge = row.querySelector('.stock-badge-table')?.textContent || '0';
        const stockCount = parseInt(stockBadge);
        
        let show = true;
        
        // Search filter
        if (searchTerm && !productName.includes(searchTerm) && !productBrand.includes(searchTerm)) {
            show = false;
        }
        
        // Category filter
        if (categoryFilter) {
            const categoryName = document.querySelector(`option[value="${categoryFilter}"]`)?.textContent;
            if (categoryName && !categoryCell.includes(categoryName)) {
                show = false;
            }
        }
        
        // Stock filter
        if (stockFilter === 'available' && stockCount <= 0) show = false;
        if (stockFilter === 'low' && stockCount > 5) show = false;
        if (stockFilter === 'out' && stockCount > 0) show = false;
        
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });
    
    // Show/hide empty state
    const emptyStates = document.querySelectorAll('.empty-state, .empty-state-table');
    emptyStates.forEach(state => {
        state.style.display = visibleCount === 0 ? 'block' : 'none';
    });
}
</script>

<style>
.filter-section {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: var(--box-shadow);
    margin-bottom: 20px;
}

.filter-form {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
    flex: 1;
    min-width: 250px;
}

.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray);
}

.search-box input {
    width: 100%;
    padding: 10px 10px 10px 40px;
    border: 1px solid var(--light-gray);
    border-radius: 8px;
    font-size: 1rem;
}

.filter-select {
    padding: 10px 15px;
    border: 1px solid var(--light-gray);
    border-radius: 8px;
    background: white;
    min-width: 150px;
}

.view-toggle {
    display: flex;
    gap: 5px;
    margin-right: 10px;
}

.view-toggle .btn {
    padding: 8px 12px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 15px;
}

.product-card {
    background: white;
    border-radius: 12px;
    box-shadow: var(--box-shadow);
    overflow: hidden;
    transition: var(--transition);
    position: relative;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.product-card.low-stock {
    border-left: 4px solid var(--warning);
}

.product-card.out-of-stock {
    border-left: 4px solid var(--secondary);
    opacity: 0.7;
}

.product-image {
    position: relative;
    height: 140px;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.product-image img {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
}

.no-image {
    font-size: 3rem;
    color: var(--light-gray);
}

.stock-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    color: white;
}

.stock-badge.good { background: var(--success); }
.stock-badge.low { background: var(--warning); }
.stock-badge.out { background: var(--secondary); }

.product-info {
    padding: 12px;
}

.product-name {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 4px;
    color: var(--dark);
    line-height: 1.2;
}

.product-brand {
    color: var(--primary);
    font-size: 0.8rem;
    margin-bottom: 3px;
}

.product-category {
    color: var(--gray);
    font-size: 0.75rem;
    margin-bottom: 6px;
}

.product-price {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--success);
}

.product-actions {
    padding: 10px 12px;
    border-top: 1px solid var(--light-gray);
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 0.8rem;
}

.table-product-image {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
}

.table-product-image img {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
}

.no-image-small {
    color: var(--light-gray);
    font-size: 1.5rem;
}

.product-details strong {
    display: block;
    margin-bottom: 3px;
}

.text-muted {
    color: var(--gray);
    font-size: 0.85rem;
}

.stock-badge-table {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    color: white;
}

.stock-badge-table.good { background: var(--success); }
.stock-badge-table.low { background: var(--warning); }
.stock-badge-table.out { background: var(--secondary); }

.action-buttons {
    display: flex;
    gap: 5px;
}

.low-stock-row {
    background: rgba(255, 158, 0, 0.1);
}

.out-of-stock-row {
    background: rgba(234, 84, 85, 0.1);
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: var(--gray);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: var(--dark);
}

.empty-state-table {
    text-align: center;
    padding: 40px;
    color: var(--gray);
}

.empty-state-table i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.products-container {
    height: calc(100vh - 280px);
    overflow-y: auto;
    padding-right: 5px;
}

.btn-danger:disabled {
    background: #bdc3c7 !important;
    cursor: not-allowed;
    opacity: 0.6;
}

.table-container tbody {
    display: block;
    height: calc(100vh - 350px);
    overflow-y: auto;
}

.table-container thead,
.table-container tbody tr {
    display: table;
    width: 100%;
    table-layout: fixed;
}

@media (max-width: 768px) {
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-box {
        min-width: auto;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .products-container {
        height: calc(100vh - 350px);
    }
}
</style>

<?php include 'includes/footer.php'; ?>