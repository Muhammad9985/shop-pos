<?php
require_once 'auth.php';
require_once 'config/database.php';
requireLogin();

if ($_POST) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Get current user data
    $query = "SELECT password FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?error=current_password");
        exit;
    }
    
    // Check if new passwords match
    if ($new_password !== $confirm_password) {
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?error=password_mismatch");
        exit;
    }
    
    // Update password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $query = "UPDATE users SET password = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$hashed_password, $_SESSION['user_id']]);
    
    header("Location: " . $_SERVER['HTTP_REFERER'] . "?success=password_updated");
    exit;
}

header("Location: dashboard.php");
exit;
?>