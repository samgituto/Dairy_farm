<?php
include 'includes/db.php';

session_start();
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $purchase_id       = trim($_POST['purchase_id']);
    $ingredient_id     = intval($_POST['ingredient_id']);
    $supplier_id       = intval($_POST['supplier_id']);
    $purchase_date     = $_POST['purchase_date'];
    $transaction_date  = $_POST['transaction_date'];
    $quantity          = floatval($_POST['quantity']);
    $cost_per_kg       = floatval($_POST['cost_per_kg']);
    $total_cost        = $quantity * $cost_per_kg; 
    $unit              = $_POST['unit'];
    $batch_number      = trim($_POST['batch_number']);
    $invoice_number    = trim($_POST['invoice_number']);
    $expiry_date       = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $storage_location  = trim($_POST['storage_location']);
    $payment_status    = $_POST['payment_status'];
    $remarks           = trim($_POST['remarks']);
    $recorded_by       = $_SESSION['user_id'];

    // Start MySQLi transaction
    mysqli_begin_transaction($conn);

    try {
        // STEP 1: Insert into feed_purchases
        $sql_purchase = "INSERT INTO feed_purchases 
            (purchase_id, supplier_id, purchase_date, transaction_date, quantity_purchased, cost_per_kg, total_cost, unit, batch_number, invoice_number, expiry_date, storage_location, payment_status, remarks, recorded_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql_purchase);
        mysqli_stmt_bind_param($stmt, "sissddssssssssi", 
            $purchase_id, $supplier_id, $purchase_date, $transaction_date, 
            $quantity, $cost_per_kg, $total_cost, $unit, $batch_number, 
            $invoice_number, $expiry_date, $storage_location, $payment_status, 
            $remarks, $recorded_by
        );
        mysqli_stmt_execute($stmt);
        $feed_purchase_db_id = mysqli_insert_id($conn);

        // STEP 2: Update stock quantity in feed_ingredients
        $sql_ingredient = "UPDATE feed_ingredients SET current_stock = current_stock + ?, cost_per_unit = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql_ingredient);
        mysqli_stmt_bind_param($stmt, "ddi", $quantity, $cost_per_kg, $ingredient_id);
        mysqli_stmt_execute($stmt);

        // STEP 3: Update or Insert record in feed_inventory (Upsert)
        $sql_inventory = "INSERT INTO feed_inventory (ingredient_id, batch_number, quantity, expiry_date, storage_location) 
                          VALUES (?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE 
                          quantity = quantity + VALUES(quantity), 
                          storage_location = VALUES(storage_location)";
        $stmt = mysqli_prepare($conn, $sql_inventory);
        mysqli_stmt_bind_param($stmt, "isdss", $ingredient_id, $batch_number, $quantity, $expiry_date, $storage_location);
        mysqli_stmt_execute($stmt);

        // STEP 4: Create a record in inventory_transactions
        $sql_transaction = "INSERT INTO inventory_transactions (ingredient_id, purchase_id, transaction_type, quantity, transaction_date) 
                            VALUES (?, ?, 'Purchase', ?, ?)";
        $stmt = mysqli_prepare($conn, $sql_transaction);
        mysqli_stmt_bind_param($stmt, "iids", $ingredient_id, $feed_purchase_db_id, $quantity, $transaction_date);
        mysqli_stmt_execute($stmt);

        // STEP 5: Create a financial expense entry
        $sql_expense = "INSERT INTO financial_expenses (transaction_date, amount, category, reference_id, status) 
                        VALUES (?, ?, 'Feed Purchase', ?, ?)";
        $stmt = mysqli_prepare($conn, $sql_expense);
        mysqli_stmt_bind_param($stmt, "sdss", $transaction_date, $total_cost, $purchase_id, $payment_status);
        mysqli_stmt_execute($stmt);

        // Commit transaction if all operations pass
        mysqli_commit($conn);
        
        // Instant clean redirect straight back to your primary inventory screen
        header("Location: inventory.php?success=1");
        exit();
        
    } catch (Exception $e) {
        // Rollback instantly on failure
        mysqli_rollback($conn);
        echo "Error: Failed to save record safely. " . $e->getMessage();
    }
}
?>
