<?php
require_once 'config/database.php';
require_once 'auth.php';
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_POST) {
    if ($_POST['action'] == 'update_permissions') {
        $user_id = $_POST['user_id'];
        
        // Delete existing permissions
        $query = "DELETE FROM user_permissions WHERE user_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        
        // Insert new permissions
        $permissions = ['dashboard', 'products', 'categories', 'purchases', 'daily-sales'];
        foreach ($permissions as $permission) {
            if (isset($_POST[$permission])) {
                $can_view = isset($_POST[$permission]['view']) ? 1 : 0;
                $can_add = isset($_POST[$permission]['add']) ? 1 : 0;
                $can_edit = isset($_POST[$permission]['edit']) ? 1 : 0;
                $can_delete = isset($_POST[$permission]['delete']) ? 1 : 0;
                
                if ($can_view || $can_add || $can_edit || $can_delete) {
                    $query = "INSERT INTO user_permissions (user_id, permission, can_view, can_add, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$user_id, $permission, $can_view, $can_add, $can_edit, $can_delete]);
                }
            }
        }
        
        header("Location: roles.php");
        exit;
    }
    
    if ($_POST['action'] == 'change_password') {
        $user_id = $_POST['user_id'];
        $new_password = $_POST['new_password'];
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$hashed_password, $user_id]);
        
        header("Location: roles.php?user_id=$user_id");
        exit;
    }
}

$page_title = 'Role Management';
include 'includes/header.php';

// Fetch shopkeepers
$query = "SELECT * FROM users WHERE role = 'shopkeeper' ORDER BY username";
$stmt = $db->prepare($query);
$stmt->execute();
$shopkeepers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected user permissions
$selected_user_id = $_GET['user_id'] ?? ($shopkeepers[0]['id'] ?? null);
$user_permissions = [];
if ($selected_user_id) {
    $query = "SELECT * FROM user_permissions WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$selected_user_id]);
    $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($perms as $perm) {
        $user_permissions[$perm['permission']] = $perm;
    }
}

$permissions_config = [
    'dashboard' => ['name' => 'Dashboard', 'actions' => ['view']],
    'products' => ['name' => 'Products', 'actions' => ['view', 'add', 'edit', 'delete']],
    'categories' => ['name' => 'Categories', 'actions' => ['view', 'add', 'edit', 'delete']],
    'purchases' => ['name' => 'Purchases', 'actions' => ['view', 'add', 'edit', 'delete']],
    'daily-sales' => ['name' => 'Daily Sales', 'actions' => ['view']]
];
?>

<div class="page-header">
    <h1 class="page-title">Role Management</h1>
    <div class="page-actions">
        <select id="userSelect" class="btn" style="background: white; border: 1px solid var(--light-gray);" onchange="window.location.href='roles.php?user_id='+this.value">
            <?php foreach ($shopkeepers as $shopkeeper): ?>
            <option value="<?php echo $shopkeeper['id']; ?>" <?php echo $shopkeeper['id'] == $selected_user_id ? 'selected' : ''; ?>>
                <?php echo $shopkeeper['username']; ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php if ($selected_user_id): ?>
<div class="table-container">
    <form method="POST">
        <input type="hidden" name="action" value="update_permissions">
        <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
        
        <table>
            <thead>
                <tr>
                    <th>Module</th>
                    <th>View</th>
                    <th>Add</th>
                    <th>Edit</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permissions_config as $key => $config): ?>
                <tr>
                    <td><strong><?php echo $config['name']; ?></strong></td>
                    <?php 
                    $current_perm = $user_permissions[$key] ?? null;
                    $actions = ['view', 'add', 'edit', 'delete'];
                    foreach ($actions as $action): 
                        if (in_array($action, $config['actions'])): ?>
                    <td>
                        <input type="checkbox" name="<?php echo $key; ?>[<?php echo $action; ?>]" 
                               <?php echo ($current_perm && $current_perm['can_'.$action]) ? 'checked' : ''; ?>
                               style="transform: scale(1.2);">
                    </td>
                    <?php else: ?>
                    <td style="text-align: center; color: var(--gray);">-</td>
                    <?php endif; endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="padding: 20px; text-align: center;">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-save"></i> Update Permissions
            </button>
            <button type="button" class="btn btn-warning" style="margin-left: 10px;" onclick="document.getElementById('passwordModal').style.display='flex'">
                <i class="fas fa-key"></i> Change Password
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<div class="table-container">
    <div style="text-align: center; padding: 40px; color: var(--gray);">
        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 15px;"></i>
        <p>No shopkeepers found. Create a shopkeeper account first.</p>
    </div>
</div>
<?php endif; ?>

<!-- Change Password Modal -->
<div class="modal" id="passwordModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Change Password</h3>
            <button class="close-modal" onclick="document.getElementById('passwordModal').style.display='none'">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
            
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="6">
                <small style="color: var(--gray);">Minimum 6 characters</small>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-danger" style="flex: 1;" onclick="document.getElementById('passwordModal').style.display='none'">
                    Cancel
                </button>
                <button type="submit" class="btn btn-success" style="flex: 1;">
                    <i class="fas fa-save"></i> Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>