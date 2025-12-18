<?php
require_once 'auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Shop POS'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="container header-container">
            <div class="logo">
                <i class="fas fa-cash-register"></i>
                <span>Shop POS</span>
            </div>
            <div class="user-info">
                <div class="user-role">
                    <i class="fas fa-user"></i> <?php echo ucfirst($_SESSION['role']); ?>
                </div>
                <span>Welcome, <?php echo $_SESSION['username']; ?></span>
                <?php if (isAdmin()): ?>
                <button class="logout-btn" onclick="document.getElementById('adminPasswordModal').style.display='flex'" style="margin-right: 10px; background: var(--warning);">
                    <i class="fas fa-key"></i> Change Password
                </button>
                <?php endif; ?>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="main-content">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <nav class="sidebar" id="sidebar">
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Point of Sale</span>
                    </a>
                </li>
                <?php if (isAdmin() || hasPermission('dashboard')): ?>
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isAdmin() || hasPermission('products')): ?>
                <li class="nav-item">
                    <a href="products.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                        <i class="fas fa-box"></i>
                        <span>Products</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isAdmin() || hasPermission('categories')): ?>
                <li class="nav-item">
                    <a href="categories.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tags"></i>
                        <span>Categories</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isAdmin() || hasPermission('purchases')): ?>
                <li class="nav-item">
                    <a href="purchases.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'purchases.php' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-bag"></i>
                        <span>Purchases</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isAdmin() || hasPermission('daily-sales')): ?>
                <li class="nav-item">
                    <a href="daily-sales.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'daily-sales.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>Daily Sales</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a href="roles.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'roles.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-cog"></i>
                        <span>Role Management</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <main class="content">
        
        <?php if (isAdmin()): ?>
        <!-- Admin Password Change Modal -->
        <div class="modal" id="adminPasswordModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Change Your Password</h3>
                    <button class="close-modal" onclick="document.getElementById('adminPasswordModal').style.display='none'">&times;</button>
                </div>
                <form method="POST" action="update-password.php" onsubmit="return validatePasswordForm()">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                        <small style="color: var(--gray);">Minimum 6 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="button" class="btn btn-danger" style="flex: 1;" onclick="document.getElementById('adminPasswordModal').style.display='none'">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-success" style="flex: 1;">
                            <i class="fas fa-save"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        function validatePasswordForm() {
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (newPassword !== confirmPassword) {
                showMessage('Password Mismatch', 'New passwords do not match!', 'error');
                return false;
            }
            
            return true;
        }
        
        function showMessage(title, message, type) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'flex';
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">${title}</h3>
                        <button class="close-modal" onclick="this.closest('.modal').remove()">&times;</button>
                    </div>
                    <div style="padding: 20px; text-align: center;">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'}" style="font-size: 3rem; color: ${type === 'success' ? 'var(--success)' : 'var(--warning)'}; margin-bottom: 15px;"></i>
                        <p style="font-size: 1.1rem; margin-bottom: 20px;">${message}</p>
                    </div>
                    <div style="display: flex; justify-content: center; padding: 0 25px 25px;">
                        <button class="btn btn-primary" onclick="this.closest('.modal').remove()">
                            OK
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        // Show success/error messages
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.get('success') === 'password_updated') {
                showMessage('Success', 'Password updated successfully!', 'success');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            if (urlParams.get('error') === 'current_password') {
                showMessage('Error', 'Current password is incorrect!', 'error');
                if (document.getElementById('adminPasswordModal')) {
                    document.getElementById('adminPasswordModal').style.display = 'flex';
                }
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            if (urlParams.get('error') === 'has_stock') {
                showMessage('Cannot Delete', 'Cannot delete product that has stock quantity!', 'error');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
        </script>
        <?php endif; ?>