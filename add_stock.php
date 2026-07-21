<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";

/* ============================================================
   SAVE INVENTORY RECORD
============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $item_name     = mysqli_real_escape_string($conn, $_POST['item_name']);
    $category      = mysqli_real_escape_string($conn, $_POST['category']);
    $quantity      = (float) $_POST['quantity'];
    $unit          = mysqli_real_escape_string($conn, $_POST['unit']);
    $unit_cost     = (float) $_POST['unit_cost'];
    $total_cost    = $quantity * $unit_cost;
    $reorder_level = (float) $_POST['reorder_level'];
    $supplier_name = mysqli_real_escape_string($conn, $_POST['supplier_name']);
    $record_date   = mysqli_real_escape_string($conn, $_POST['record_date']);

    $recorded_by = $_SESSION['full_name']
        ?? $_SESSION['username']
        ?? 'System';

    $receipt = "";

    /* ========================================================
       UPLOAD RECEIPT OPTIONAL
    ======================================================== */

    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === 0) {

        $upload_dir = "uploads/receipts/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo(
            $_FILES['receipt']['name'],
            PATHINFO_EXTENSION
        );

        $receipt = time() . "_" . rand(1000, 9999) . "." . $file_extension;

        move_uploaded_file(
            $_FILES['receipt']['tmp_name'],
            $upload_dir . $receipt
        );
    }

    /* ========================================================
       INSERT DATA
    ======================================================== */

    $sql = "
        INSERT INTO inventory (
            item_name,
            category,
            quantity,
            unit,
            unit_cost,
            total_cost,
            reorder_level,
            supplier_name,
            record_date,
            receipt,
            recorded_by
        )
        VALUES (
            '$item_name',
            '$category',
            '$quantity',
            '$unit',
            '$unit_cost',
            '$total_cost',
            '$reorder_level',
            '$supplier_name',
            '$record_date',
            '$receipt',
            '$recorded_by'
        )
    ";

    if (mysqli_query($conn, $sql)) {
        header("Location: inventory.php?success=1");
        exit();
    } else {
        $message = "Error: " . mysqli_error($conn);
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>
                <i class="fas fa-plus-circle"></i>
                Add New Inventory Item
            </h1>

            <p>
                Record feeds, medicine, equipment, supplements, and other farm stock.
            </p>
        </div>

        <div>
            <a href="inventory.php" class="btn btn-primary">
                <i class="fas fa-list"></i>
                View Inventory
            </a>
        </div>

    </div>

    <?php if ($message !== "") { ?>

        <div class="alert alert-danger">
            <?= $message; ?>
        </div>

    <?php } ?>

    <form method="POST" enctype="multipart/form-data">

        <div class="form-card">

            <h3>
                <i class="fas fa-boxes"></i>
                Stock Information
            </h3>

            <div class="form-grid">

                <div class="form-group">
                    <label>Item Name</label>

                    <input
                        type="text"
                        name="item_name"
                        class="form-control"
                        placeholder="Example: Dairy Meal"
                        required>
                </div>

                <div class="form-group">
                    <label>Category</label>

                    <select name="category" class="form-control" required>
                        <option value="">Select Category</option>
                        <option value="Feeds">Feeds</option>
                        <option value="Medicine">Medicine</option>
                        <option value="Equipment">Equipment</option>
                        <option value="Supplement">Supplement</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Quantity</label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="quantity"
                        id="quantity"
                        class="form-control"
                        placeholder="Enter quantity"
                        required>
                </div>

                <div class="form-group">
                    <label>Unit</label>

                    <select name="unit" class="form-control" required>
                        <option value="">Select Unit</option>
                        <option value="Kgs">Kgs</option>
                        <option value="Bags">Bags</option>
                        <option value="Pieces">Pieces</option>
                        <option value="Ml">Ml</option>
                        <option value="Litres">Litres</option>
                        <option value="Grams">Grams</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Unit Cost</label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="unit_cost"
                        id="unit_cost"
                        class="form-control"
                        placeholder="Enter unit cost"
                        required>
                </div>

                <div class="form-group">
                    <label>Total Cost</label>

                    <input
                        type="text"
                        id="total_cost"
                        class="form-control"
                        readonly>
                </div>

                <div class="form-group">
                    <label>Reorder Level</label>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="reorder_level"
                        class="form-control"
                        placeholder="Minimum stock level"
                        required>
                </div>

                <div class="form-group">
                    <label>Supplier</label>

                    <input
                        list="supplierList"
                        name="supplier_name"
                        class="form-control"
                        placeholder="Type supplier or choose Internal Farm Supply"
                        required>

                    <datalist id="supplierList">
                        <option value="Internal Farm Supply">
                    </datalist>
                </div>

                <div class="form-group">
                    <label>Date</label>

                    <input
                        type="date"
                        name="record_date"
                        class="form-control"
                        value="<?= date('Y-m-d'); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label>Upload Receipt Optional</label>

                    <input
                        type="file"
                        name="receipt"
                        class="form-control"
                        accept=".jpg,.jpeg,.png,.pdf">
                </div>

            </div>

            <div class="form-actions">

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Save Item
                </button>

                <a href="inventory.php" class="btn btn-secondary">
                    Cancel
                </a>

            </div>

        </div>

    </form>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const quantity  = document.getElementById("quantity");
    const unitCost  = document.getElementById("unit_cost");
    const totalCost = document.getElementById("total_cost");

    function calculateTotal() {
        let qty   = parseFloat(quantity.value) || 0;
        let cost  = parseFloat(unitCost.value) || 0;
        let total = qty * cost;

        totalCost.value = total.toFixed(2);
    }

    quantity.addEventListener("input", calculateTotal);
    unitCost.addEventListener("input", calculateTotal);

});
</script>

<?php include 'includes/footer.php'; ?>