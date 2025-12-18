<?php
session_start();

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header("Location: index.php");
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isShopkeeper() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'shopkeeper';
}

function hasPermission($permission, $action = 'view') {
    if (isAdmin()) return true;
    
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT can_$action FROM user_permissions WHERE user_id = ? AND permission = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $permission]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result && $result['can_'.$action];
}

function requirePermission($permission, $action = 'view') {
    requireLogin();
    if (!hasPermission($permission, $action)) {
        header("Location: index.php");
        exit;
    }
}
?>