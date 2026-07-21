<?php 
// Corrected to use your standard path and mysqli variable ($conn)
include 'includes/db.php';

// Fetch active ingredients and suppliers for dropdown selection using MySQLi
$ingredients = mysqli_query($conn, "SELECT id, ingredient_name FROM feed_ingredients");
$suppliers   = mysqli_query($conn, "SELECT supplier_id, supplier_name FROM suppliers");

// Auto generate dynamic Purchase ID based on current date
$date_prefix = date('Ymd');
$unique_suffix = rand(100, 999); 
$auto_purchase_id = "PUR-" . $date_prefix . "-" . $unique_suffix;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Stock / Inventory Form</title>
    <!-- Link to your existing CSS file -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="form-page-body">
<div class="form-container">
    <h2 class="form-title">New Stock / Inventory Entry</h2>
    
    <form action="save_purchase.php" method="POST" class="stock-form">
        <div class="form-grid">
            
            <div class="form-group">
                <label>Purchase ID</label>
                <input type="text" class="form-input read-only-input" name="purchase_id" value="<?php echo $auto_purchase_id; ?>" readonly>
            </div>
            
            <div class="form-group">
                <label>Ingredient</label>
                <select class="form-select" name="ingredient_id" required>
                    <option value="">-- Select Ingredient --</option>
                    <?php while($row = mysqli_fetch_assoc($ingredients)): ?>
                        <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['ingredient_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Supplier</label>
                <select class="form-select" name="supplier_id" required>
                    <option value="">-- Select Supplier --</option>
                    <?php while($row = mysqli_fetch_assoc($suppliers)): ?>
                        <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['supplier_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Purchase Date</label>
                <input type="date" class="form-input" name="purchase_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Transaction Date</label>
                <input type="date" class="form-input" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Batch Number</label>
                <input type="text" class="form-input" name="batch_number" required>
            </div>
            
            <div class="form-group">
                <label>Invoice Number</label>
                <input type="text" class="form-input" name="invoice_number" required>
            </div>

            <div class="form-group">
                <label>Quantity Purchased (kg)</label>
                <input type="number" step="0.01" class="form-input" id="qty" name="quantity" required>
            </div>
            
            <div class="form-group">
                <label>Cost per kg</label>
                <input type="number" step="0.01" class="form-input" id="cost" name="cost_per_kg" required>
            </div>
            
            <div class="form-group">
                <label>Total Cost</label>
                <input type="number" step="0.01" class="form-input read-only-input" id="total" name="total_cost" readonly>
            </div>
            
            <div class="form-group">
                <label>Unit</label>
                <select class="form-select" name="unit" required>
                    <option value="Kg">Kg</option>
                    <option value="Bags">Bags</option>
                    <option value="Tons">Tons</option>
                </select>
            </div>

            <div class="form-group">
                <label>Expiry Date</label>
                <input type="date" class="form-input" name="expiry_date">
            </div>
            
            <div class="form-group">
                <label>Storage Location</label>
                <input type="text" class="form-input" name="storage_location" placeholder="Store A, Silo 2" required>
            </div>
            
            <div class="form-group">
                <label>Payment Status</label>
                <select class="form-select" name="payment_status" required>
                    <option value="Paid">Paid</option>
                    <option value="Pending">Pending</option>
                </select>
            </div>

            <div class="form-group full-width">
                <label>Remarks</label>
                <textarea class="form-textarea" name="remarks" rows="3"></textarea>
            </div>

            <div class="form-actions full-width">
                <button type="submit" class="submit-button">Save Entry & Update Stock</button>
            </div>
        </div>
    </form>
</div>

<script>
const qtyInput = document.getElementById('qty');
const costInput = document.getElementById('cost');
const totalInput = document.getElementById('total');

function calculate() {
    let qty = parseFloat(qtyInput.value) || 0;
    let cost = parseFloat(costInput.value) || 0;
    totalInput.value = (qty * cost).toFixed(2);
}

qtyInput.addEventListener('input', calculate);
costInput.addEventListener('input', calculate);
</script>
</body>
</html>
