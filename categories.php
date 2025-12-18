<?php
require_once 'config/database.php';
require_once 'auth.php';
requirePermission('categories');

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_POST) {
    if ($_POST['action'] == 'add_category') {
        $name = $_POST['name'];
        $slug = strtolower(str_replace(' ', '-', $name));
        
        $query = "INSERT INTO categories (name, slug) VALUES (?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$name, $slug]);
        
        header("Location: categories.php");
        exit;
    }
    
    if ($_POST['action'] == 'edit_category') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $slug = strtolower(str_replace(' ', '-', $name));
        
        $query = "UPDATE categories SET name = ?, slug = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$name, $slug, $id]);
        
        header("Location: categories.php");
        exit;
    }
    
    if ($_POST['action'] == 'delete_category') {
        $id = $_POST['id'];
        $query = "DELETE FROM categories WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        
        header("Location: categories.php");
        exit;
    }
}

$page_title = 'Categories Management';
include 'includes/header.php';

// Fetch categories with product count
$query = "SELECT c.*, COUNT(p.id) as product_count 
          FROM categories c 
          LEFT JOIN products p ON c.id = p.category_id 
          GROUP BY c.id 
          ORDER BY c.name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.categories-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.categories-title {
    font-size: 2rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
    min-width: 300px;
}

.search-box input {
    width: 100%;
    padding: 12px 45px 12px 15px;
    border: 2px solid #e1e8ed;
    border-radius: 25px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.search-box input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.search-box i {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #7f8c8d;
}

.add-btn {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 25px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}

.add-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(46, 204, 113, 0.3);
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

.category-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 1px solid #f1f3f4;
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.category-name {
    font-size: 1.3rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.category-slug {
    font-size: 0.9rem;
    color: #7f8c8d;
    background: #ecf0f1;
    padding: 4px 12px;
    border-radius: 15px;
    margin-top: 5px;
    display: inline-block;
}

.category-stats {
    display: flex;
    justify-content: space-between;
    margin: 15px 0;
}

.stat-item {
    text-align: center;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #3498db;
    display: block;
}

.stat-label {
    font-size: 0.8rem;
    color: #7f8c8d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.category-date {
    font-size: 0.85rem;
    color: #95a5a6;
    margin-bottom: 15px;
}

.category-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.action-btn {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.edit-btn {
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
}

.edit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(243, 156, 18, 0.3);
}

.delete-btn {
    background: linear-gradient(135deg, #e74c3c, #c0392b);
    color: white;
}

.delete-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
}

.delete-btn:disabled {
    background: #bdc3c7;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #7f8c8d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    color: #bdc3c7;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 15px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 25px 25px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #7f8c8d;
    padding: 5px;
}

.modal form {
    padding: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e1e8ed;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
}

.modal-actions {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

.modal-btn {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.cancel-btn {
    background: #ecf0f1;
    color: #2c3e50;
}

.save-btn {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    color: white;
}

@media (max-width: 768px) {
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .search-box {
        min-width: 250px;
    }
    
    .categories-header {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="categories-header">
    <h1 class="categories-title">Categories Management</h1>
    <div class="header-actions">
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search categories...">
            <i class="fas fa-search"></i>
        </div>
        <?php if (hasPermission('categories', 'add')): ?>
        <button class="add-btn" onclick="document.getElementById('addCategoryModal').style.display='flex'">
            <i class="fas fa-plus"></i> Add Category
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="categories-grid" id="categoriesGrid">
    <?php foreach ($categories as $category): ?>
    <div class="category-card" data-name="<?php echo strtolower($category['name']); ?>" data-slug="<?php echo strtolower($category['slug']); ?>">
        <div class="category-header">
            <div>
                <h3 class="category-name"><?php echo htmlspecialchars($category['name']); ?></h3>
                <span class="category-slug"><?php echo htmlspecialchars($category['slug']); ?></span>
            </div>
        </div>
        
        <div class="category-stats">
            <div class="stat-item">
                <span class="stat-value"><?php echo $category['product_count']; ?></span>
                <span class="stat-label">Products</span>
            </div>
        </div>
        
        <div class="category-date">
            <i class="fas fa-calendar"></i> Created <?php echo date('M j, Y', strtotime($category['created_at'])); ?>
        </div>
        
        <div class="category-actions">
            <?php if (hasPermission('categories', 'edit')): ?>
            <button class="action-btn edit-btn" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                <i class="fas fa-edit"></i> Edit
            </button>
            <?php endif; ?>
            <?php if (hasPermission('categories', 'delete')): ?>
            <button class="action-btn delete-btn" <?php echo $category['product_count'] > 0 ? 'disabled title="Cannot delete category with products"' : ''; ?> onclick="<?php echo $category['product_count'] == 0 ? 'deleteCategory(' . $category['id'] . ')' : ''; ?>">
                <i class="fas fa-trash"></i> Delete
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="empty-state" id="emptyState" style="display: none;">
    <i class="fas fa-search"></i>
    <h3>No categories found</h3>
    <p>Try adjusting your search terms</p>
</div>

<!-- Add Category Modal -->
<div class="modal" id="addCategoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New Category</h3>
            <button class="close-modal" onclick="document.getElementById('addCategoryModal').style.display='none'">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_category">
            
            <div class="form-group">
                <label>Category Name</label>
                <input type="text" name="name" class="form-control" required placeholder="Enter category name">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel-btn" onclick="document.getElementById('addCategoryModal').style.display='none'">
                    Cancel
                </button>
                <button type="submit" class="modal-btn save-btn">
                    <i class="fas fa-save"></i> Save Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal" id="editCategoryModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Category</h3>
            <button class="close-modal" onclick="document.getElementById('editCategoryModal').style.display='none'">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_category">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="form-group">
                <label>Category Name</label>
                <input type="text" name="name" id="edit_name" class="form-control" required placeholder="Enter category name">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="modal-btn cancel-btn" onclick="document.getElementById('editCategoryModal').style.display='none'">
                    Cancel
                </button>
                <button type="submit" class="modal-btn save-btn">
                    <i class="fas fa-save"></i> Update Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Delete Category</h3>
            <button class="close-modal" onclick="document.getElementById('deleteModal').style.display='none'">&times;</button>
        </div>
        <div style="padding: 20px; text-align: center;">
            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--warning); margin-bottom: 15px;"></i>
            <h3 style="margin-bottom: 10px;">Are you sure?</h3>
            <p style="color: var(--gray); margin-bottom: 20px;">Do you want to delete this category? This action cannot be undone.</p>
            <input type="hidden" id="deleteId">
        </div>
        <div class="modal-actions">
            <button type="button" class="modal-btn cancel-btn" onclick="document.getElementById('deleteModal').style.display='none'">
                Cancel
            </button>
            <button type="button" class="modal-btn" style="background: var(--secondary); color: white;" onclick="confirmDelete()">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
// Real-time search functionality
document.getElementById('searchInput').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const categoryCards = document.querySelectorAll('.category-card');
    const emptyState = document.getElementById('emptyState');
    let visibleCount = 0;
    
    categoryCards.forEach(card => {
        const name = card.dataset.name;
        const slug = card.dataset.slug;
        
        if (name.includes(searchTerm) || slug.includes(searchTerm)) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
});

function editCategory(category) {
    document.getElementById('edit_id').value = category.id;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('editCategoryModal').style.display = 'flex';
}

function deleteCategory(id) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteModal').style.display = 'flex';
}

function confirmDelete() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete_category">
        <input type="hidden" name="id" value="${document.getElementById('deleteId').value}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>