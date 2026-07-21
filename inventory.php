<?php
session_start();

include 'includes/db.php';
include_once 'includes/check_low_stock_sms.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$smsAlertResult = checkLowStockAndSendSMS($conn);
/* ============================================================
   FILTER VALUES
============================================================ */

$view = $_GET['view'] ?? 'current';

$categoryFilter = "";

if (isset($_GET['category']) && $_GET['category'] !== "") {
    $category = mysqli_real_escape_string($conn, $_GET['category']);
    $categoryFilter = "WHERE category = '$category'";
}

/* ============================================================
   LOW STOCK ALERTS
============================================================ */

$lowStockQuery = mysqli_query($conn, "
    SELECT
        item_name,
        category,
        unit,
        SUM(quantity) AS current_stock,
        MAX(reorder_level) AS reorder_level
    FROM inventory
    GROUP BY item_name, category, unit
    HAVING current_stock <= reorder_level
    ORDER BY current_stock ASC
");

/* ============================================================
   CURRENT INVENTORY
============================================================ */

$currentInventoryQuery = mysqli_query($conn, "
    SELECT
        item_name,
        category,
        unit,
        SUM(quantity) AS current_stock,
        MAX(record_date) AS last_restocked_date,
        MAX(reorder_level) AS reorder_level
    FROM inventory
    $categoryFilter
    GROUP BY item_name, category, unit
    ORDER BY item_name ASC
");

/* ============================================================
   HISTORICAL INVENTORY RECORDS
============================================================ */

$historicalQuery = mysqli_query($conn, "
    SELECT *
    FROM inventory
    $categoryFilter
    ORDER BY record_date DESC, created_at DESC
");

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

    <div class="page-header">

        <div>
            <h1>
                <i class="fas fa-warehouse"></i>
                Inventory Management
            </h1>

            <p>
                View current stock, historical records, and low stock alerts.
            </p>
        </div>

        <div>
            <a href="add_stock.php" class="btn btn-success">
                <i class="fas fa-plus"></i>
                Add New Item
            </a>
        </div>

    </div>

    <?php if (isset($_GET['success'])) { ?>

        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Inventory item saved successfully.
        </div>

    <?php } ?>

    <!-- LOW STOCK ALERT CARDS -->

    <div class="cards">

        <?php if (mysqli_num_rows($lowStockQuery) > 0) { ?>

            <?php while ($low = mysqli_fetch_assoc($lowStockQuery)) { ?>

                <div class="card low-stock-card">

                    <div class="card-icon bg-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>

                    <div>
                        <h4><?= htmlspecialchars($low['item_name']); ?></h4>

                        <p>
                            Current Stock:
                            <strong>
                                <?= number_format($low['current_stock'], 2); ?>
                                <?= htmlspecialchars($low['unit']); ?>
                            </strong>
                        </p>

                        <p>
                            Reorder Level:
                            <strong>
                                <?= number_format($low['reorder_level'], 2); ?>
                                <?= htmlspecialchars($low['unit']); ?>
                            </strong>
                        </p>
                    </div>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="card">

                <div class="card-icon bg-green">
                    <i class="fas fa-check-circle"></i>
                </div>

                <div>
                    <h4>No Low Stock Alerts</h4>
                    <p>All items are above reorder level.</p>
                </div>

            </div>

        <?php } ?>

    </div>

    <!-- FILTER FORM -->

    <div class="table-card">

        <form method="GET" action="inventory.php">

            <div class="search-bar">

                <select name="category" class="form-control">

                    <option value="">All Categories</option>

                    <option value="Feeds"
                        <?= (isset($_GET['category']) && $_GET['category'] === 'Feeds') ? 'selected' : ''; ?>>
                        Feeds
                    </option>

                    <option value="Medicine"
                        <?= (isset($_GET['category']) && $_GET['category'] === 'Medicine') ? 'selected' : ''; ?>>
                        Medicine
                    </option>

                    <option value="Equipment"
                        <?= (isset($_GET['category']) && $_GET['category'] === 'Equipment') ? 'selected' : ''; ?>>
                        Equipment
                    </option>

                    <option value="Supplement"
                        <?= (isset($_GET['category']) && $_GET['category'] === 'Supplement') ? 'selected' : ''; ?>>
                        Supplement
                    </option>

                    <option value="Other"
                        <?= (isset($_GET['category']) && $_GET['category'] === 'Other') ? 'selected' : ''; ?>>
                        Other
                    </option>

                </select>

                <select name="view" class="form-control">

                    <option value="current"
                        <?= ($view === 'current') ? 'selected' : ''; ?>>
                        Current Inventory
                    </option>

                    <option value="history"
                        <?= ($view === 'history') ? 'selected' : ''; ?>>
                        All Historical Records
                    </option>

                </select>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i>
                    Apply Filter
                </button>

                <a href="inventory.php" class="btn btn-secondary">
                    Reset
                </a>

            </div>

        </form>

    </div>

    <!-- CURRENT INVENTORY TABLE -->

    <?php if ($view === 'current') { ?>

        <div class="table-card">

            <div class="table-header">
                <h3>
                    <i class="fas fa-list"></i>
                    Current Inventory
                </h3>
            </div>

            <div class="table-responsive">

                <table class="custom-table">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Last Restocked Date</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php
                        $count = 1;

                        if (mysqli_num_rows($currentInventoryQuery) > 0) {

                            while ($row = mysqli_fetch_assoc($currentInventoryQuery)) {

                                $status = "Available";
                                $badge = "badge-success";

                                if ($row['current_stock'] <= $row['reorder_level']) {
                                    $status = "Low Stock";
                                    $badge = "badge-danger";
                                }
                        ?>

                                <tr>
                                    <td><?= $count++; ?></td>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($row['item_name']); ?>
                                        </strong>
                                    </td>

                                    <td><?= htmlspecialchars($row['category']); ?></td>

                                    <td>
                                        <?= number_format($row['current_stock'], 2); ?>
                                        <?= htmlspecialchars($row['unit']); ?>
                                    </td>

                                    <td><?= htmlspecialchars($row['last_restocked_date']); ?></td>

                                    <td>
                                        <?= number_format($row['reorder_level'], 2); ?>
                                        <?= htmlspecialchars($row['unit']); ?>
                                    </td>

                                    <td>
                                        <span class="<?= $badge; ?>">
                                            <?= $status; ?>
                                        </span>
                                    </td>
                                </tr>

                        <?php
                            }

                        } else {
                        ?>

                            <tr>
                                <td colspan="7" style="text-align:center;">
                                    No inventory records found.
                                </td>
                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php } ?>
<?php if (isset($smsAlertResult) && $smsAlertResult['status'] === true) { ?>

    <div class="alert alert-success">
        <i class="fas fa-sms"></i>
        <?= htmlspecialchars($smsAlertResult['message']); ?>
    </div>

<?php } ?>
    <!-- HISTORICAL RECORDS TABLE -->

    <?php if ($view === 'history') { ?>

        <div class="table-card">

            <div class="table-header">
                <h3>
                    <i class="fas fa-history"></i>
                    All Historical Inventory Records
                </h3>
            </div>

            <div class="table-responsive">

                <table class="custom-table">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Unit Cost</th>
                            <th>Total Cost</th>
                            <th>Reorder Level</th>
                            <th>Supplier</th>
                            <th>Date</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php
                        $h = 1;

                        if (mysqli_num_rows($historicalQuery) > 0) {

                            while ($history = mysqli_fetch_assoc($historicalQuery)) {
                        ?>

                                <tr>
                                    <td><?= $h++; ?></td>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($history['item_name']); ?>
                                        </strong>
                                    </td>

                                    <td><?= htmlspecialchars($history['category']); ?></td>

                                    <td>
                                        <?= number_format($history['quantity'], 2); ?>
                                        <?= htmlspecialchars($history['unit']); ?>
                                    </td>

                                    <td>
                                        KSh <?= number_format($history['unit_cost'], 2); ?>
                                    </td>

                                    <td>
                                        KSh <?= number_format($history['total_cost'], 2); ?>
                                    </td>

                                    <td>
                                        <?= number_format($history['reorder_level'], 2); ?>
                                        <?= htmlspecialchars($history['unit']); ?>
                                    </td>

                                    <td><?= htmlspecialchars($history['supplier_name']); ?></td>

                                    <td><?= htmlspecialchars($history['record_date']); ?></td>

                                    <td>
                                        <?php if (!empty($history['receipt'])) { ?>

                                            <a
                                                href="uploads/receipts/<?= htmlspecialchars($history['receipt']); ?>"
                                                target="_blank"
                                                class="btn btn-primary btn-sm">
                                                View
                                            </a>

                                        <?php } else { ?>

                                            No Receipt

                                        <?php } ?>
                                    </td>
                                </tr>

                        <?php
                            }

                        } else {
                        ?>

                            <tr>
                                <td colspan="10" style="text-align:center;">
                                    No historical records found.
                                </td>
                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php } ?>

</div>

<?php include 'includes/footer.php'; ?>