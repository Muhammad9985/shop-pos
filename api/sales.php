<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_POST['action'] == 'submit_sale') {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $db->beginTransaction();
        
        $cart = json_decode($_POST['cart'], true);
        $transaction_id = 'TXN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $total = 0;
        foreach ($cart as $item) {
            $total += $item['price'];
        }
        
        // Insert transaction
        $query = "INSERT INTO transactions (transaction_id, total_amount, tax_amount) VALUES (?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$transaction_id, $total, 0]);
        
        // Check stock availability before processing sale
        foreach ($cart as $item) {
            $query = "SELECT 
                      COALESCE((SELECT SUM(quantity) FROM purchases WHERE product_id = ?), 0) - 
                      COALESCE((SELECT COUNT(*) FROM sales WHERE product_id = ?), 0) as current_stock";
            $stmt = $db->prepare($query);
            $stmt->execute([$item['productId'], $item['productId']]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($stock['current_stock'] <= 0) {
                throw new Exception("Insufficient stock for product: " . $item['name']);
            }
        }
        
        // Insert sales (stock is automatically calculated from purchases - sales)
        foreach ($cart as $item) {
            $query = "INSERT INTO sales (transaction_id, product_id, product_name, price) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$transaction_id, $item['productId'], $item['name'], $item['price']]);
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'transaction_id' => $transaction_id,
            'total' => number_format($total, 2)
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>