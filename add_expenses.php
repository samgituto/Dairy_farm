<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";


/* ============================================================
   SAVE EXPENSE RECORD
============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $category    = mysqli_real_escape_string($conn, $_POST['category']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $quantity    = (float) $_POST['quantity'];
    $unit_cost   = (float) $_POST['unit_cost'];
    $total_cost  = $quantity * $unit_cost;
    $record_date = mysqli_real_escape_string($conn, $_POST['record_date']);

    $recorded_by = $_SESSION['full_name']
        ?? $_SESSION['username']
        ?? 'System';

    $receipt = "";


    /* ========================================================
       VALIDATION
    ======================================================== */

    if ($quantity <= 0) {

        $message = "Quantity must be greater than zero.";

    } elseif ($unit_cost <= 0) {

        $message = "Unit cost must be greater than zero.";

    } else {


        /* ====================================================
           UPLOAD RECEIPT / INVOICE OPTIONAL
        ==================================================== */

        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === 0) {

            $upload_dir = "uploads/receipts/";

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

            $file_extension = strtolower(
                pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION)
            );

            if (in_array($file_extension, $allowed_extensions)) {

                $receipt = time() . "_" . rand(1000, 9999) . "." . $file_extension;

                move_uploaded_file(
                    $_FILES['receipt']['tmp_name'],
                    $upload_dir . $receipt
                );

            } else {

                $message = "Only JPG, JPEG, PNG, and PDF files are allowed.";

            }
        }


        /* ====================================================
           INSERT EXPENSE INTO DATABASE
        ==================================================== */

        if ($message === "") {

            $sql = "
                INSERT INTO expenses (
                    category,
                    description,
                    quantity,
                    unit_cost,
                    total_cost,
                    receipt,
                    record_date,
                    recorded_by
                )
                VALUES (
                    '$category',
                    '$description',
                    '$quantity',
                    '$unit_cost',
                    '$total_cost',
                    '$receipt',
                    '$record_date',
                    '$recorded_by'
                )
            ";

            if (mysqli_query($conn, $sql)) {

                header("Location: expenses.php?success=1");
                exit();

            } else {

                $message = "Error: " . mysqli_error($conn);

            }
        }
    }
}


include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>
                <i class="fas fa-money-bill-wave"></i>
                Add Expense
            </h1>

            <p>
                Record farm expenses such as feeds, medicine, labor, utilities,
                maintenance, breeding, and veterinary costs.
            </p>
        </div>

        <div>
            <a href="expenses.php" class="btn btn-primary">
                <i class="fas fa-list"></i>
                View Expenses
            </a>
        </div>

    </div>


    <?php if ($message !== "") { ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($message); ?>
        </div>

    <?php } ?>


    <form method="POST" enctype="multipart/form-data">

        <div class="form-card">

            <h3>
                <i class="fas fa-receipt"></i>
                Expense Information
            </h3>

            <div class="form-grid">

                <div class="form-group">

                    <label>Category</label>

                    <select name="category" class="form-control" required>

                        <option value="">Select Category</option>
                        <option value="Feeds">Feeds</option>
                        <option value="Medicine">Medicine</option>
                        <option value="Equipment">Equipment</option>
                        <option value="Supplement">Supplement</option>
                        <option value="Breeding">Breeding</option>
                        <option value="Veterinary">Veterinary</option>
                        <option value="Labor">Labor</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Maintenance">Maintenance</option>
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

                    <label>Date</label>

                    <input
                        type="date"
                        name="record_date"
                        class="form-control"
                        value="<?= date('Y-m-d'); ?>"
                        required>

                </div>


                <div class="form-group">

                    <label>Invoice / Receipt Upload Optional</label>

                    <input
                        type="file"
                        name="receipt"
                        class="form-control"
                        accept=".jpg,.jpeg,.png,.pdf">

                </div>


                <div class="form-group" style="grid-column: 1 / -1;">

                    <label>Description</label>

                    <textarea
                        name="description"
                        class="form-control"
                        rows="4"
                        placeholder="Example: Veterinary service, electricity bill, labor payment..."
                        required></textarea>

                </div>

            </div>


            <div class="form-actions">

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Save Expense
                </button>

                <a href="expenses.php" class="btn btn-secondary">
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

    function calculateTotalCost() {
        let qty   = parseFloat(quantity.value) || 0;
        let cost  = parseFloat(unitCost.value) || 0;
        let total = qty * cost;

        totalCost.value = total.toFixed(2);
    }

    quantity.addEventListener("input", calculateTotalCost);
    unitCost.addEventListener("input", calculateTotalCost);

});
</script>

<?php include 'includes/footer.php'; ?>