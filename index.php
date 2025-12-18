<?php
require_once 'config/database.php';
$page_title = 'Point of Sale';
include 'includes/header.php';

$database = new Database();
$db = $database->getConnection();

// Fetch categories
$query = "SELECT * FROM categories ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch products with category info, calculated stock, and latest sale price
$query = "SELECT p.*, c.slug as category_slug,
          (SELECT sale_price FROM purchases WHERE product_id = p.id ORDER BY created_at DESC LIMIT 1) as suggested_price,
          COALESCE((SELECT SUM(quantity) FROM purchases WHERE product_id = p.id), 0) - 
          COALESCE((SELECT COUNT(*) FROM sales WHERE product_id = p.id), 0) as current_stock
          FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.name";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1 class="page-title">Point of Sale - Individual Entries</h1>
    <div class="page-actions">
        <div class="current-time" id="currentTime"></div>
    </div>
</div>

<div class="pos-container">
    <div class="products-section">
        <div class="product-categories">
            <button class="category-btn active" data-category="all">All Products</button>
            <?php foreach($categories as $category): ?>
            <button class="category-btn" data-category="<?php echo $category['slug']; ?>">
                <?php echo ucfirst($category['name']); ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="products-grid" id="products-grid">
            <?php foreach($products as $product): ?>
            <div class="product-card <?php echo $product['current_stock'] <= 0 ? 'out-of-stock' : ''; ?>" data-id="<?php echo $product['id']; ?>" data-category="<?php echo $product['category_slug']; ?>">
                <div class="product-img">
                    <?php if($product['image'] && file_exists('uploads/products/'.$product['image'])): ?>
                        <img src="uploads/products/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">
                    <?php else: ?>
                        <i class="fas <?php 
                            echo $product['category_slug'] == 'chargers' ? 'fa-charging-station' : 
                                ($product['category_slug'] == 'headphones' ? 'fa-headphones' : 
                                ($product['category_slug'] == 'cables' ? 'fa-plug' : 'fa-mobile-alt')); 
                        ?>"></i>
                    <?php endif; ?>
                </div>
                <div class="product-name"><?php echo $product['name']; ?></div>
                <?php if ($product['brand']): ?>
                <div class="product-brand"><?php echo $product['brand']; ?></div>
                <?php endif; ?>
                <div class="product-stock">Stock: <?php echo $product['current_stock']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="cart-section">
        <div class="cart-header">
            <h3><i class="fas fa-shopping-cart"></i> Current Sale</h3>
        </div>
        
        <div class="cart-items" id="cart-items">
            <div class="cart-empty">
                <i class="fas fa-shopping-cart"></i>
                <p>No products added yet</p>
                <p style="font-size: 0.9rem; margin-top: 10px;">Click on products to add them as individual entries</p>
            </div>
        </div>
        
        <div class="cart-footer">
            <div class="cart-summary">
                <div class="cart-summary-item">
                    <span>Items:</span>
                    <span id="cart-items-count">0</span>
                </div>
                <div class="cart-total">
                    <span>Total:</span>
                    <span>Rs.<span id="cart-total">0.00</span></span>
                </div>
            </div>
            
            <div class="cart-actions">
                <button class="btn btn-danger" id="clear-cart-btn">
                    <i class="fas fa-trash"></i> Clear All
                </button>
                <button class="btn btn-success" id="submit-sale-btn">
                    <i class="fas fa-check-circle"></i> Submit Sale
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="saleSuccessModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Sale Completed Successfully!</h3>
            <button class="close-modal" id="closeModal">&times;</button>
        </div>
        <div style="text-align: center; padding: 20px 0;">
            <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--success); margin-bottom: 20px;"></i>
            <h3 style="margin-bottom: 10px;">Transaction ID: <span id="transactionId">TXN-001</span></h3>
            <p style="font-size: 1.2rem; margin-bottom: 5px;">Total Amount: <strong>Rs.<span id="modalTotal">0.00</span></strong></p>
            <p style="color: var(--gray);">Date: <span id="transactionDate"></span></p>
            <p style="color: var(--gray); margin-top: 10px;">The sale has been recorded in today's sales report.</p>
        </div>
        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button class="btn btn-primary" style="flex: 1;" id="printReceiptBtn">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <button class="btn btn-success" style="flex: 1;" id="newSaleBtn">
                <i class="fas fa-plus"></i> New Sale
            </button>
        </div>
    </div>
</div>

<script>
const products = <?php echo json_encode($products); ?>;
let cart = [];
let cartItemId = 1;

document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000);
});

function setupEventListeners() {
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            const product = products.find(p => p.id == productId);
            addToCart(product);
        });
    });
    
    document.querySelectorAll('.category-btn').forEach(button => {
        button.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            filterProducts(category);
        });
    });
    
    document.getElementById('clear-cart-btn').addEventListener('click', clearCart);
    document.getElementById('submit-sale-btn').addEventListener('click', submitSale);
    document.getElementById('closeModal').addEventListener('click', () => {
        document.getElementById('saleSuccessModal').style.display = 'none';
    });
    document.getElementById('newSaleBtn').addEventListener('click', () => {
        document.getElementById('saleSuccessModal').style.display = 'none';
        clearCart();
    });
    document.getElementById('printReceiptBtn').addEventListener('click', printReceipt);
}

function addToCart(product) {
    if (product.current_stock <= 0) {
        showNotification('Product is out of stock!', 'error');
        return;
    }
    
    const suggestedPrice = Math.round(parseFloat(product.suggested_price)) || 0;
    const cartItem = {
        id: cartItemId++,
        productId: product.id,
        name: product.name,
        brand: product.brand || '',
        price: suggestedPrice,
        suggestedPrice: suggestedPrice
    };
    cart.push(cartItem);
    updateCartDisplay();
}

function updateCartDisplay() {
    const cartItems = document.getElementById('cart-items');
    
    if (cart.length === 0) {
        cartItems.innerHTML = `
            <div class="cart-empty">
                <i class="fas fa-shopping-cart"></i>
                <p>No products added yet</p>
            </div>
        `;
        updateCartTotals();
        return;
    }
    
    cartItems.innerHTML = '';
    
    cart.forEach(item => {
        const cartItem = document.createElement('div');
        cartItem.className = 'cart-item';
        cartItem.innerHTML = `
            <div class="cart-item-info">
                <div class="cart-item-name">${item.name}</div>
                ${item.brand ? `<div class="cart-item-brand">${item.brand}</div>` : ''}
            </div>
            <div class="cart-item-actions">
                <div class="cart-item-price">
                    <label>Rs.</label>
                    <input type="number" class="cart-item-price-input" placeholder="0" 
                           value="${Math.round(item.price || item.suggestedPrice)}" step="1" min="1" required
                           oninput="updateItemPrice(${item.id}, this.value)">
                </div>
                <button class="cart-item-remove" onclick="removeFromCart(${item.id})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        cartItems.appendChild(cartItem);
    });
    
    updateCartTotals();
}

function removeFromCart(id) {
    cart = cart.filter(item => item.id !== id);
    updateCartDisplay();
}

function clearCart() {
    if (cart.length === 0) {
        showNotification('Cart is already empty!', 'warning');
        return;
    }
    showConfirmModal('Clear all items?', 'Are you sure you want to remove all items from the cart?', () => {
        cart = [];
        updateCartDisplay();
    });
}

function submitSale() {
    if (cart.length === 0) {
        showNotification('Cannot submit an empty sale!', 'error');
        return;
    }
    
    // Validate all items have prices
    for (let item of cart) {
        if (!item.price || item.price <= 0) {
            showNotification('Please enter sale price for all items before submitting!', 'error');
            return;
        }
    }
    
    const formData = new FormData();
    formData.append('action', 'submit_sale');
    formData.append('cart', JSON.stringify(cart));
    
    fetch('api/sales.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('transactionId').textContent = data.transaction_id;
            document.getElementById('modalTotal').textContent = data.total;
            document.getElementById('transactionDate').textContent = new Date().toLocaleString();
            document.getElementById('saleSuccessModal').style.display = 'flex';
            cart = [];
            updateCartDisplay();
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    });
}

function updateItemPrice(itemId, price) {
    const item = cart.find(i => i.id === itemId);
    if (item) {
        item.price = parseInt(price) || 0;
        updateCartTotals();
    }
}

function updateCartTotals() {
    let subtotal = 0;
    cart.forEach(item => {
        if (item.price) subtotal += item.price;
    });
    
    document.getElementById('cart-items-count').textContent = cart.length;
    document.getElementById('cart-total').textContent = Math.round(subtotal);
}

function filterProducts(category) {
    document.querySelectorAll('.product-card').forEach(card => {
        if (category === 'all' || card.getAttribute('data-category') === category) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function updateCurrentTime() {
    const now = new Date();
    let hours = now.getHours();
    let minutes = now.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    minutes = minutes < 10 ? '0' + minutes : minutes;
    document.getElementById('currentTime').textContent = `${hours}:${minutes} ${ampm}`;
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="background:none;border:none;color:white;font-size:1.2rem;cursor:pointer;margin-left:10px;">&times;</button>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function showConfirmModal(title, message, onConfirm) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">${title}</h3>
            </div>
            <div style="padding: 20px 0; text-align: center;">
                <i class="fas fa-question-circle" style="font-size: 3rem; color: var(--warning); margin-bottom: 15px;"></i>
                <p style="font-size: 1.1rem; margin-bottom: 20px;">${message}</p>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button class="btn btn-danger" style="flex: 1;" onclick="this.closest('.modal').remove()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn btn-success" style="flex: 1;" onclick="confirmAction()">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </div>
        </div>
    `;
    
    window.confirmAction = () => {
        onConfirm();
        modal.remove();
    };
    
    document.body.appendChild(modal);
}

function printReceipt() {
    const transactionId = document.getElementById('transactionId').textContent;
    const total = document.getElementById('modalTotal').textContent;
    const date = new Date().toLocaleString();
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Receipt - ${transactionId}</title>
            <style>
                body { font-family: monospace; padding: 20px; max-width: 300px; }
                .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
                .item { display: flex; justify-content: space-between; margin: 5px 0; }
                .total { border-top: 2px solid #000; padding-top: 10px; margin-top: 15px; font-weight: bold; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>SHOP POS</h2>
                <p>Sales Receipt</p>
            </div>
            <div class="item"><span>Transaction ID:</span><span>${transactionId}</span></div>
            <div class="item"><span>Date:</span><span>${date}</span></div>
            <div class="item"><span>Cashier:</span><span>${document.querySelector('.user-info span').textContent}</span></div>
            <div class="total">
                <div class="item"><span>TOTAL AMOUNT:</span><span>Rs.${total}</span></div>
            </div>
            <div class="footer">
                <p>Thank you for your purchase!</p>
                <p>Visit us again</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php include 'includes/footer.php'; ?>