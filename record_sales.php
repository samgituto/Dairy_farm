<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";


/* ============================================================
   LOAD ACTIVE COWS
============================================================ */

$cowsQuery = mysqli_query($conn, "
    SELECT
        id,
        tag_number,
        cow_name,
        status
    FROM cows
    WHERE is_active = 1
    ORDER BY tag_number ASC
");


/* ============================================================
   LOAD MILK PRODUCTION BY COW FOR SELECTED DATE
============================================================ */

$selected_milk_date = $_GET['milk_date'] ?? date("Y-m-d");

$selected_milk_date = mysqli_real_escape_string(
    $conn,
    $selected_milk_date
);

$milkSummaryQuery = mysqli_query($conn, "
    SELECT
        c.id AS cow_id,
        c.tag_number,
        c.cow_name,
        c.status,
        IFNULL(SUM(m.litres), 0) AS total_litres
    FROM cows c

    LEFT JOIN milk_records m
        ON m.cow_id = c.id
        AND DATE(m.record_date) = '$selected_milk_date'

    WHERE c.is_active = 1

    GROUP BY c.id

    ORDER BY c.tag_number ASC
");


/* ============================================================
   SAVE SALE
============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $sale_no = "SALE-" . date("YmdHis") . rand(10, 99);

    $sale_category = mysqli_real_escape_string(
        $conn,
        $_POST['sale_category']
    );

    $cow_id_value = $_POST['cow_id'] ?? "";

    $cow_id = !empty($cow_id_value)
        ? (int) $cow_id_value
        : "NULL";

    $description = mysqli_real_escape_string(
        $conn,
        $_POST['description']
    );

    $quantity = (float) $_POST['quantity'];

    $unit = mysqli_real_escape_string(
        $conn,
        $_POST['unit']
    );

    $unit_cost = (float) $_POST['unit_cost'];

    $total_cost = $quantity * $unit_cost;

    $customer_name = mysqli_real_escape_string(
        $conn,
        $_POST['customer_name']
    );

    $payment_method = mysqli_real_escape_string(
        $conn,
        $_POST['payment_method']
    );

    $sale_date = mysqli_real_escape_string(
        $conn,
        $_POST['sale_date']
    );

    $recorded_by = $_SESSION['full_name']
        ?? $_SESSION['username']
        ?? 'System';


    /* ========================================================
       VALIDATION
    ======================================================== */

    if ($sale_category === "") {

        $message = "Please select sale category.";

    } elseif ($quantity <= 0) {

        $message = "Quantity must be greater than zero.";

    } elseif ($unit === "") {

        $message = "Please select unit.";

    } elseif ($unit_cost <= 0) {

        $message = "Unit cost must be greater than zero.";

    } elseif ($customer_name === "") {

        $message = "Customer name is required.";

    } elseif ($payment_method === "") {

        $message = "Please select payment method.";

    } elseif ($sale_category === "Cows" && $cow_id === "NULL") {

        $message = "Please select the cow being sold.";

    } else {

        mysqli_begin_transaction($conn);

        try {

            /* =================================================
               INSERT SALE
            ================================================= */

            $salesSql = "
                INSERT INTO sales (
                    sale_no,
                    sale_category,
                    cow_id,
                    description,
                    quantity,
                    unit,
                    unit_cost,
                    total_cost,
                    customer_name,
                    payment_method,
                    sale_date,
                    recorded_by
                )
                VALUES (
                    '$sale_no',
                    '$sale_category',
                    $cow_id,
                    '$description',
                    '$quantity',
                    '$unit',
                    '$unit_cost',
                    '$total_cost',
                    '$customer_name',
                    '$payment_method',
                    '$sale_date',
                    '$recorded_by'
                )
            ";

            if (!mysqli_query($conn, $salesSql)) {
                throw new Exception(mysqli_error($conn));
            }

            $sale_id = mysqli_insert_id($conn);


            /* =================================================
               TRANSFER SALE TO INCOME TABLE
            ================================================= */

            $incomeTransactionNo = "INC-" . date("YmdHis") . rand(10, 99);

            $incomeDescription = mysqli_real_escape_string(
                $conn,
                "Sales Income - " . $sale_category . " - " . $description
            );

            $incomeSql = "
                INSERT INTO income (
                    transaction_no,
                    transaction_date,
                    amount,
                    description,
                    recorded_by
                )
                VALUES (
                    '$incomeTransactionNo',
                    '$sale_date',
                    '$total_cost',
                    '$incomeDescription',
                    '$recorded_by'
                )
            ";

            if (!mysqli_query($conn, $incomeSql)) {
                throw new Exception(mysqli_error($conn));
            }

            $income_id = mysqli_insert_id($conn);


            /* =================================================
               LINK SALE TO INCOME RECORD
            ================================================= */

            $updateSaleSql = "
                UPDATE sales
                SET income_id = '$income_id'
                WHERE sale_id = '$sale_id'
            ";

            if (!mysqli_query($conn, $updateSaleSql)) {
                throw new Exception(mysqli_error($conn));
            }


            /* =================================================
               IF COW IS SOLD, MAKE IT DORMANT
            ================================================= */

            if ($sale_category === "Cows" && $cow_id !== "NULL") {

                $updateCowSql = "
                    UPDATE cows
                    SET
                        is_active = 0,
                        sold_at = '$sale_date'
                    WHERE id = '$cow_id'
                ";

                if (!mysqli_query($conn, $updateCowSql)) {
                    throw new Exception(mysqli_error($conn));
                }
            }

            mysqli_commit($conn);

            header("Location: sales.php?success=1");
            exit();

        } catch (Exception $e) {

            mysqli_rollback($conn);

            $message = "Error: " . $e->getMessage();
        }
    }
}


include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

    <!-- ======================================================
         PAGE HEADER
    ======================================================= -->

    <div class="page-header">

        <div>
            <h1>
                <i class="fas fa-cash-register"></i>
                Record Sale
            </h1>

            <p>
                Record milk, manure, and cow sales. Sales are automatically transferred to income.
            </p>
        </div>

        <div>
            <a href="sales.php" class="btn btn-primary">
                <i class="fas fa-list"></i>
                View Sales
            </a>
        </div>

    </div>


    <!-- ======================================================
         ERROR MESSAGE
    ======================================================= -->

    <?php if ($message !== "") { ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($message); ?>
        </div>

    <?php } ?>


    <!-- ======================================================
         DAILY MILK SUMMARY
    ======================================================= -->

    <div class="table-card">

        <div class="table-header">

            <h3>
                <i class="fas fa-glass-whiskey"></i>
                Daily Milk Available
            </h3>

        </div>


        <form method="GET" action="record_sales.php">

            <div class="search-bar">

                <input
                    type="date"
                    name="milk_date"
                    class="form-control"
                    value="<?= htmlspecialchars($selected_milk_date); ?>">

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i>
                    View Milk
                </button>

            </div>

        </form>


        <div class="table-responsive">

            <table class="custom-table">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cow Tag</th>
                        <th>Cow Name</th>
                        <th>Status</th>
                        <th>Milk Produced</th>
                    </tr>
                </thead>

                <tbody>

                    <?php
                    $m = 1;

                    if (mysqli_num_rows($milkSummaryQuery) > 0) {

                        while ($milk = mysqli_fetch_assoc($milkSummaryQuery)) {
                    ?>

                            <tr>

                                <td><?= $m++; ?></td>

                                <td>
                                    <?= htmlspecialchars($milk['tag_number']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($milk['cow_name'] ?? 'Unnamed'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($milk['status']); ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= number_format($milk['total_litres'], 2); ?>
                                        Litres
                                    </strong>
                                </td>

                            </tr>

                    <?php
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="5" style="text-align:center;">
                                No milk records found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- ======================================================
         SALES FORM
    ======================================================= -->

    <form method="POST" action="record_sales.php">

        <div class="form-card">

            <h3>
                <i class="fas fa-receipt"></i>
                Sale Information
            </h3>

            <div class="form-grid">

                <!-- SALE CATEGORY -->

                <div class="form-group">

                    <label>Sale Category</label>

                    <select
                        name="sale_category"
                        id="sale_category"
                        class="form-control"
                        required>

                        <option value="">Select Category</option>
                        <option value="Milk">Milk</option>
                        <option value="Manure">Manure</option>
                        <option value="Cows">Cows</option>

                    </select>

                </div>


                <!-- COW SELECTION -->

                <div class="form-group">

                    <label>Select Cow</label>

                    <select name="cow_id" class="form-control">

                        <option value="">Select Cow Optional</option>

                        <?php
                        mysqli_data_seek($cowsQuery, 0);

                        while ($cow = mysqli_fetch_assoc($cowsQuery)) {
                        ?>

                            <option value="<?= $cow['id']; ?>">
                                <?= htmlspecialchars($cow['tag_number']); ?>
                                -
                                <?= htmlspecialchars($cow['cow_name'] ?? 'Unnamed'); ?>
                                -
                                <?= htmlspecialchars($cow['status']); ?>
                            </option>

                        <?php } ?>

                    </select>

                    <small>
                        Required when selling a cow. Optional for general milk or manure sales.
                    </small>

                </div>


                <!-- QUANTITY -->

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


                <!-- UNIT -->

                <div class="form-group">

                    <label>Unit</label>

                    <select name="unit" id="unit" class="form-control" required>

                        <option value="">Select Unit</option>
                        <option value="Litres">Litres</option>
                        <option value="Kg">Kg</option>
                        <option value="Bags">Bags</option>
                        <option value="Cows">Cows</option>

                    </select>

                </div>


                <!-- UNIT COST -->

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


                <!-- TOTAL COST -->

                <div class="form-group">

                    <label>Total Cost</label>

                    <input
                        type="text"
                        id="total_cost"
                        class="form-control"
                        readonly>

                </div>


                <!-- CUSTOMER -->

                <div class="form-group">

                    <label>Customer Name</label>

                    <input
                        type="text"
                        name="customer_name"
                        class="form-control"
                        placeholder="Enter customer name"
                        required>

                </div>


                <!-- DATE -->

                <div class="form-group">

                    <label>Date</label>

                    <input
                        type="date"
                        name="sale_date"
                        class="form-control"
                        value="<?= date('Y-m-d'); ?>"
                        required>

                </div>


                <!-- PAYMENT METHOD -->

                <div class="form-group">

                    <label>Payment Method</label>

                    <select name="payment_method" class="form-control" required>

                        <option value="">Select Payment Method</option>
                        <option value="Cash">Cash</option>
                        <option value="Mpesa">Mpesa</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Credit">Credit</option>

                    </select>

                </div>


                <!-- DESCRIPTION -->

                <div class="form-group" style="grid-column: 1 / -1;">

                    <label>Description</label>

                    <textarea
                        name="description"
                        class="form-control"
                        rows="3"
                        placeholder="Example: Morning milk sale, manure bags sold, cow sold..."
                        required></textarea>

                </div>

            </div>


            <!-- FORM BUTTONS -->

            <div class="form-actions">

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i>
                    Save Sale
                </button>

                <a href="sales.php" class="btn btn-secondary">
                    Cancel
                </a>

            </div>

        </div>

    </form>

</div>


<script>
document.addEventListener("DOMContentLoaded", function () {

    const saleCategory = document.getElementById("sale_category");

    const unit = document.getElementById("unit");

    const quantity = document.getElementById("quantity");

    const unitCost = document.getElementById("unit_cost");

    const totalCost = document.getElementById("total_cost");


    function calculateTotal() {

        let qty = parseFloat(quantity.value) || 0;

        let cost = parseFloat(unitCost.value) || 0;

        let total = qty * cost;

        totalCost.value = total.toFixed(2);
    }


    function updateUnitByCategory() {

        if (saleCategory.value === "Milk") {

            unit.value = "Litres";

        } else if (saleCategory.value === "Manure") {

            unit.value = "Bags";

        } else if (saleCategory.value === "Cows") {

            unit.value = "Cows";

            quantity.value = 1;

            calculateTotal();

        }
    }


    saleCategory.addEventListener("change", updateUnitByCategory);

    quantity.addEventListener("input", calculateTotal);

    unitCost.addEventListener("input", calculateTotal);

});
</script>

<?php include 'includes/footer.php'; ?>