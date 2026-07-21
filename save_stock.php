<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: add_stock.php");
    exit();
}

mysqli_begin_transaction($conn);

try{

/*==================================================
UPLOAD RECEIPT
==================================================*/

$receipt = "";

if(isset($_FILES['receipt']) && $_FILES['receipt']['error']==0){

    $uploadDir = "uploads/receipts/";

    if(!is_dir($uploadDir)){
        mkdir($uploadDir,0777,true);
    }

    $extension = pathinfo($_FILES['receipt']['name'],PATHINFO_EXTENSION);

    $receipt = time()."_".rand(1000,9999).".".$extension;

    move_uploaded_file(
        $_FILES['receipt']['tmp_name'],
        $uploadDir.$receipt
    );
}

/*==================================================
GET FORM VALUES
==================================================*/

$purchase_no = mysqli_real_escape_string($conn,$_POST['purchase_no']);

$ingredient_id = (int)$_POST['ingredient_id'];

$supplier_id = !empty($_POST['supplier_id'])
? (int)$_POST['supplier_id']
: NULL;

$purchase_date = $_POST['purchase_date'];

$transaction_date = $_POST['transaction_date'];

$quantity = (double)$_POST['quantity'];

$cost_per_kg = (double)$_POST['cost_per_kg'];

$total_cost = $quantity * $cost_per_kg;

$unit = mysqli_real_escape_string($conn,$_POST['unit']);

$batch_number = mysqli_real_escape_string($conn,$_POST['batch_number']);

$invoice_number = mysqli_real_escape_string($conn,$_POST['invoice_number']);

$expiry_date = !empty($_POST['expiry_date'])
? $_POST['expiry_date']
: NULL;

$storage_location = mysqli_real_escape_string(
$conn,
$_POST['storage_location']
);

$payment_status = mysqli_real_escape_string(
$conn,
$_POST['payment_status']
);

$remarks = mysqli_real_escape_string(
$conn,
$_POST['remarks']
);

$recorded_by = mysqli_real_escape_string(
$conn,
$_POST['recorded_by']
);

/*==================================================
VALIDATION
==================================================*/

if($ingredient_id<=0){

    throw new Exception("Please select an ingredient.");

}

if($quantity<=0){

    throw new Exception("Quantity must be greater than zero.");

}

if($cost_per_kg<=0){

    throw new Exception("Invalid cost per Kg.");

}
/*==================================================
INSERT PURCHASE RECORD
==================================================*/

$stmt = mysqli_prepare($conn,"
INSERT INTO feed_purchases
(
purchase_no,
ingredient_id,
supplier_id,
purchase_date,
transaction_date,
quantity,
unit_cost,
total_cost,
unit,
batch_number,
invoice_number,
expiry_date,
storage_location,
payment_status,
receipt,
remarks,
recorded_by
)
VALUES
(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

mysqli_stmt_bind_param(
$stmt,
"siissdddsssssssss",
$purchase_no,
$ingredient_id,
$supplier_id,
$purchase_date,
$transaction_date,
$quantity,
$cost_per_kg,
$total_cost,
$unit,
$batch_number,
$invoice_number,
$expiry_date,
$storage_location,
$payment_status,
$receipt,
$remarks,
$recorded_by
);

mysqli_stmt_execute($stmt);

/*==================================================
UPDATE FEED INGREDIENT STOCK
==================================================*/

mysqli_query($conn,"
UPDATE feed_ingredients
SET
current_stock = current_stock + $quantity,
cost_per_unit = $cost_per_kg
WHERE ingredient_id = $ingredient_id
");

/*==================================================
CHECK INVENTORY RECORD
==================================================*/

$check = mysqli_query($conn,"
SELECT inventory_id
FROM feed_inventory
WHERE ingredient_id = $ingredient_id
");

if(mysqli_num_rows($check)>0){

    mysqli_query($conn,"
    UPDATE feed_inventory
    SET
    quantity_available = quantity_available + $quantity,
    unit = '$unit'
    WHERE ingredient_id = $ingredient_id
    ");

}else{

    mysqli_query($conn,"
    INSERT INTO feed_inventory
    (
    ingredient_id,
    quantity_available,
    unit
    )
    VALUES
    (
    $ingredient_id,
    $quantity,
    '$unit'
    )
    ");

}

/*==================================================
RECORD INVENTORY TRANSACTION
==================================================*/

$reference = $purchase_no;

mysqli_query($conn,"
INSERT INTO inventory_transactions
(
ingredient_id,
transaction_type,
quantity,
balance,
reference_no,
remarks,
recorded_by
)
VALUES
(
$ingredient_id,
'Purchase',
$quantity,
(
SELECT current_stock
FROM feed_ingredients
WHERE ingredient_id=$ingredient_id
),
'$reference',
'Feed Purchase',
'$recorded_by'
)
");
/*==================================================
CREATE FINANCIAL EXPENSE (IF MODULE EXISTS)
==================================================*/

$tableCheck = mysqli_query($conn,"
SHOW TABLES LIKE 'expenses'
");

if(mysqli_num_rows($tableCheck)>0){

    $expense_number = "EXP-".date("Ymd")."-".rand(1000,9999);

    $expense_category = "Feed Purchases";

    mysqli_query($conn,"
    INSERT INTO expenses
    (
        expense_number,
        expense_date,
        supplier_id,
        category,
        description,
        quantity,
        unit_cost,
        total_cost,
        payment_method,
        receipt_number,
        attachment,
        recorded_by
    )
    VALUES
    (
        '$expense_number',
        '$purchase_date',
        ".($supplier_id ? $supplier_id : "NULL").",
        '$expense_category',
        'Feed Ingredient Purchase',
        $quantity,
        $cost_per_kg,
        $total_cost,
        'Cash',
        '$invoice_number',
        '$receipt',
        '$recorded_by'
    )
    ");

}

/*==================================================
CREATE AUDIT LOG (OPTIONAL)
==================================================*/

$auditCheck = mysqli_query($conn,"
SHOW TABLES LIKE 'audit_logs'
");

if(mysqli_num_rows($auditCheck)>0){

    mysqli_query($conn,"
    INSERT INTO audit_logs
    (
        user_name,
        activity,
        activity_date
    )
    VALUES
    (
        '$recorded_by',
        'Recorded Feed Stock Purchase ($purchase_no)',
        NOW()
    )
    ");

}

/*==================================================
LOW STOCK STATUS UPDATE
==================================================*/

mysqli_query($conn,"
UPDATE feed_ingredients
SET status =
CASE

WHEN current_stock <=0
THEN 'Out of Stock'

WHEN current_stock <= minimum_stock
THEN 'Low Stock'

ELSE 'Available'

END
");

/*==================================================
COMMIT TRANSACTION
==================================================*/

mysqli_commit($conn);

header("Location: stock_records.php?success=1");

exit();

}

/*==================================================
ROLLBACK IF ERROR OCCURS
==================================================*/

catch(Exception $e){

    mysqli_rollback($conn);

    header("Location:add_stock.php?error=".urlencode($e->getMessage()));

    exit();

}

/*==================================================
CLOSE CONNECTION
==================================================*/

mysqli_close($conn);

?>