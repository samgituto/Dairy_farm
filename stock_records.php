<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'includes/header.php';
include 'includes/sidebar.php';


/* ============================================================
   1. DASHBOARD STATISTICS
============================================================ */

$totalIngredientsResult = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM feed_ingredients
");

$totalIngredients = mysqli_fetch_assoc($totalIngredientsResult)['total'] ?? 0;


$totalStockResult = mysqli_query($conn, "
    SELECT IFNULL(SUM(current_stock), 0) AS total
    FROM feed_ingredients
");

$totalStock = mysqli_fetch_assoc($totalStockResult)['total'] ?? 0;


$totalValueResult = mysqli_query($conn, "
    SELECT IFNULL(SUM(current_stock *unit_cost), 0) AS total
    FROM feed_ingredients
");

$totalValue = mysqli_fetch_assoc($totalValueResult)['total'] ?? 0;


$lowStockResult = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM feed_ingredients
    WHERE current_stock <= reorder_level
");

$lowStock = mysqli_fetch_assoc($lowStockResult)['total'] ?? 0;


/* ============================================================
   2. SEARCH FILTER
============================================================ */

$where = "";

if (isset($_GET['search']) && trim($_GET['search']) !== "") {
    $search = mysqli_real_escape_string($conn, trim($_GET['search']));

    $where = "
        WHERE fi.ingredient_name LIKE '%$search%'
        OR fi.category LIKE '%$search%'
        OR fi.status LIKE '%$search%'
    ";
}


/* ============================================================
   3. LOAD CURRENT INVENTORY RECORDS
============================================================ */

$sql = "
    SELECT
        fi.*,

        (
            SELECT fs.supplier_name
            FROM feed_purchases fp
            LEFT JOIN suppliers fs
                ON fp.supplier_id = fs.supplier_id
            WHERE fp.ingredient_id = fi.ingredient_id
            ORDER BY fp.purchase_date DESC, fp.transaction_date DESC
            LIMIT 1
        ) AS supplier_name

    FROM feed_ingredients fi

    $where

    ORDER BY fi.ingredient_name ASC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Stock records query failed: " . mysqli_error($conn));
}


/* ============================================================
   4. LOAD CHART DATA
============================================================ */

$chartLabels = [];
$chartStock = [];
$chartValues = [];

$chartResult = mysqli_query($conn, "
    SELECT
        ingredient_name,
        current_stock,
        cost_per_unit
    FROM feed_ingredients
    ORDER BY ingredient_name ASC
");

while ($chart = mysqli_fetch_assoc($chartResult)) {
    $chartLabels[] = $chart['ingredient_name'];
    $chartStock[] = (float) $chart['current_stock'];
    $chartValues[] = (float) $chart['current_stock'] * (float) $chart['cost_per_unit'];
}


/* ============================================================
   5. LOAD LOW STOCK ITEMS
============================================================ */

$lowStockItems = mysqli_query($conn, "
    SELECT
        ingredient_name,
        current_stock,
        minimum_stock,
        unit
    FROM feed_ingredients
    WHERE current_stock <= minimum_stock
    ORDER BY current_stock ASC
");


/* ============================================================
   6. LOAD RECENT PURCHASES
============================================================ */

$recentPurchases = mysqli_query($conn, "
    SELECT
        fp.purchase_no,
        fp.purchase_date,
        fp.quantity,
        fp.unit_cost,
        fp.total_cost,
        fi.ingredient_name,
        fs.supplier_name

    FROM feed_purchases fp

    LEFT JOIN feed_ingredients fi
        ON fp.ingredient_id = fi.ingredient_id

    LEFT JOIN feed_suppliers fs
        ON fp.supplier_id = fs.supplier_id

    ORDER BY fp.purchase_date DESC, fp.transaction_date DESC

    LIMIT 5
");
?>

<div class="main-content">

    <!-- ======================================================
         PAGE HEADER
    ======================================================= -->

    <div class="page-header">

        <div>
            <h1>
                <i class="fas fa-boxes"></i>
                Feed Inventory Records
            </h1>

            <p>
                Manage stock levels, suppliers, inventory value, and low stock alerts.
            </p>
        </div>

        <div>
            <a href="add_stock.php" class="btn btn-success">
                <i class="fas fa-plus"></i>
                Add New Stock
            </a>
        </div>

    </div>


    <!-- ======================================================
         ALERT MESSAGES
    ======================================================= -->

    <?php if (isset($_GET['success'])) { ?>

        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Stock record saved successfully.
        </div>

    <?php } ?>


    <?php if (isset($_GET['deleted'])) { ?>

        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Stock record deleted successfully.
        </div>

    <?php } ?>


    <?php if (isset($_GET['error'])) { ?>

        <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i>
            <?= htmlspecialchars($_GET['error']); ?>
        </div>

    <?php } ?>


    <!-- ======================================================
         KPI CARDS
    ======================================================= -->

    <div class="cards">

        <div class="card">

            <div class="card-icon bg-green">
                <i class="fas fa-seedling"></i>
            </div>

            <div>
                <h4>Total Ingredients</h4>
                <h2><?= number_format($totalIngredients); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-blue">
                <i class="fas fa-warehouse"></i>
            </div>

            <div>
                <h4>Total Stock</h4>
                <h2><?= number_format($totalStock, 2); ?> Kg</h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-warning">
                <i class="fas fa-money-bill-wave"></i>
            </div>

            <div>
                <h4>Inventory Value</h4>
                <h2>KSh <?= number_format($totalValue, 2); ?></h2>
            </div>

        </div>


        <div class="card">

            <div class="card-icon bg-danger">
                <i class="fas fa-exclamation-triangle"></i>
            </div>

            <div>
                <h4>Low Stock Items</h4>
                <h2><?= number_format($lowStock); ?></h2>
            </div>

        </div>

    </div>


    <!-- ======================================================
         SEARCH FORM
    ======================================================= -->

    <div class="table-card">

        <form method="GET" action="stock_records.php">

            <div class="search-bar">

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search ingredient, category, or status..."
                    value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Search
                </button>

                <a href="stock_records.php" class="btn btn-secondary">
                    <i class="fas fa-sync"></i>
                    Reset
                </a>

                <button type="button" onclick="window.print()" class="btn btn-warning">
                    <i class="fas fa-print"></i>
                    Print
                </button>

            </div>

        </form>

    </div>


    <!-- ======================================================
         CURRENT INVENTORY TABLE
    ======================================================= -->

    <div class="table-card">

        <div class="table-header">
            <h3>
                <i class="fas fa-list"></i>
                Current Feed Inventory
            </h3>
        </div>

        <div class="table-responsive">

            <table class="custom-table">

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Ingredient</th>
                        <th>Category</th>
                        <th>Latest Supplier</th>
                        <th>Unit</th>
                        <th>Cost / Unit</th>
                        <th>Current Stock</th>
                        <th>Minimum Stock</th>
                        <th>Inventory Value</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                    <?php
                    $count = 1;

                    if (mysqli_num_rows($result) > 0) {

                        while ($row = mysqli_fetch_assoc($result)) {

                            $inventoryValue = (float) $row['current_stock'] * (float) $row['cost_per_unit'];

                            $status = "Available";
                            $badge = "badge-success";

                            if ($row['current_stock'] <= 0) {
                                $status = "Out of Stock";
                                $badge = "badge-danger";
                            } elseif ($row['current_stock'] <= $row['minimum_stock']) {
                                $status = "Low Stock";
                                $badge = "badge-warning";
                            }
                    ?>

                            <tr>

                                <td><?= $count++; ?></td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row['ingredient_name']); ?>
                                    </strong>
                                </td>

                                <td><?= htmlspecialchars($row['category']); ?></td>

                                <td>
                                    <?= htmlspecialchars($row['supplier_name'] ?? 'No Supplier'); ?>
                                </td>

                                <td><?= htmlspecialchars($row['unit']); ?></td>

                                <td>
                                    KSh <?= number_format($row['cost_per_unit'], 2); ?>
                                </td>

                                <td>
                                    <?= number_format($row['current_stock'], 2); ?>
                                </td>

                                <td>
                                    <?= number_format($row['minimum_stock'], 2); ?>
                                </td>

                                <td>
                                    KSh <?= number_format($inventoryValue, 2); ?>
                                </td>

                                <td>
                                    <span class="<?= $badge; ?>">
                                        <?= $status; ?>
                                    </span>
                                </td>

                                <td>

                                    <div class="action-buttons">

                                        <a
                                            href="edit_stock.php?id=<?= $row['ingredient_id']; ?>"
                                            class="btn btn-warning btn-sm"
                                            title="Edit Stock">

                                            <i class="fas fa-edit"></i>

                                        </a>

                                        <a
                                            href="delete_stock.php?id=<?= $row['ingredient_id']; ?>"
                                            class="btn btn-danger btn-sm"
                                            title="Delete Stock"
                                            onclick="return confirm('Are you sure you want to delete this stock record?');">

                                            <i class="fas fa-trash"></i>

                                        </a>

                                    </div>

                                </td>

                            </tr>

                    <?php
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="11" style="text-align:center;">
                                No stock records found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- ======================================================
         LOW STOCK ALERTS
    ======================================================= -->

    <?php if (mysqli_num_rows($lowStockItems) > 0) { ?>

        <div class="table-card">

            <h3>
                <i class="fas fa-exclamation-circle"></i>
                Low Stock Alerts
            </h3>

            <div class="table-responsive">

                <table class="custom-table">

                    <thead>

                        <tr>
                            <th>#</th>
                            <th>Ingredient</th>
                            <th>Current Stock</th>
                            <th>Minimum Stock</th>
                            <th>Shortage</th>
                            <th>Status</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php
                        $alertCount = 1;

                        while ($alert = mysqli_fetch_assoc($lowStockItems)) {

                            $shortage = (float) $alert['minimum_stock'] - (float) $alert['current_stock'];

                            if ($shortage < 0) {
                                $shortage = 0;
                            }
                        ?>

                            <tr>

                                <td><?= $alertCount++; ?></td>

                                <td>
                                    <?= htmlspecialchars($alert['ingredient_name']); ?>
                                </td>

                                <td>
                                    <?= number_format($alert['current_stock'], 2); ?>
                                    <?= htmlspecialchars($alert['unit']); ?>
                                </td>

                                <td>
                                    <?= number_format($alert['minimum_stock'], 2); ?>
                                    <?= htmlspecialchars($alert['unit']); ?>
                                </td>

                                <td>
                                    <?= number_format($shortage, 2); ?>
                                    <?= htmlspecialchars($alert['unit']); ?>
                                </td>

                                <td>
                                    <span class="badge-danger">
                                        Restock Required
                                    </span>
                                </td>

                            </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php } ?>


    <!-- ======================================================
         RECENT PURCHASES
    ======================================================= -->

    <div class="table-card">

        <h3>
            <i class="fas fa-shopping-cart"></i>
            Recent Feed Purchases
        </h3>

        <div class="table-responsive">

            <table class="custom-table">

                <thead>

                    <tr>
                        <th>#</th>
                        <th>Purchase No</th>
                        <th>Date</th>
                        <th>Ingredient</th>
                        <th>Supplier</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Total Cost</th>
                    </tr>

                </thead>

                <tbody>

                    <?php
                    $purchaseCount = 1;

                    if (mysqli_num_rows($recentPurchases) > 0) {

                        while ($purchase = mysqli_fetch_assoc($recentPurchases)) {
                    ?>

                            <tr>

                                <td><?= $purchaseCount++; ?></td>

                                <td>
                                    <?= htmlspecialchars($purchase['purchase_no']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($purchase['purchase_date']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($purchase['ingredient_name'] ?? 'N/A'); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($purchase['supplier_name'] ?? 'No Supplier'); ?>
                                </td>

                                <td>
                                    <?= number_format($purchase['quantity'], 2); ?>
                                </td>

                                <td>
                                    KSh <?= number_format($purchase['unit_cost'], 2); ?>
                                </td>

                                <td>
                                    KSh <?= number_format($purchase['total_cost'], 2); ?>
                                </td>

                            </tr>

                    <?php
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="8" style="text-align:center;">
                                No recent purchases found.
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- ======================================================
         CHARTS
    ======================================================= -->

    <div class="cards">

        <div class="chart-card">

            <h3>
                <i class="fas fa-chart-bar"></i>
                Stock Levels
            </h3>

            <canvas id="stockChart"></canvas>

        </div>


        <div class="chart-card">

            <h3>
                <i class="fas fa-chart-pie"></i>
                Inventory Value
            </h3>

            <canvas id="valueChart"></canvas>

        </div>

    </div>

</div>


<!-- ==========================================================
     CHART.JS SCRIPT
========================================================== -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function () {

        const stockLabels = <?= json_encode($chartLabels); ?>;
        const stockData = <?= json_encode($chartStock); ?>;
        const valueData = <?= json_encode($chartValues); ?>;


        /* ======================================================
           STOCK LEVEL BAR CHART
        ====================================================== */

        const stockCanvas = document.getElementById("stockChart");

        if (stockCanvas) {

            new Chart(stockCanvas, {
                type: "bar",

                data: {
                    labels: stockLabels,

                    datasets: [
                        {
                            label: "Current Stock",
                            data: stockData,
                            borderWidth: 1,
                            borderRadius: 6
                        }
                    ]
                },

                options: {
                    responsive: true,

                    plugins: {
                        legend: {
                            display: false
                        }
                    },

                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

        }


        /* ======================================================
           INVENTORY VALUE DOUGHNUT CHART
        ====================================================== */

        const valueCanvas = document.getElementById("valueChart");

        if (valueCanvas) {

            new Chart(valueCanvas, {
                type: "doughnut",

                data: {
                    labels: stockLabels,

                    datasets: [
                        {
                            label: "Inventory Value",
                            data: valueData
                        }
                    ]
                },

                options: {
                    responsive: true,

                    plugins: {
                        legend: {
                            position: "bottom"
                        }
                    }
                }
            });

        }

    });
</script>

<?php include 'includes/footer.php'; ?>
```

Add this CSS to **`assets/css/style.css`**:

```css
.search-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.search-bar .form-control {
    flex: 1;
    min-width: 250px;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.action-buttons {
    display: flex;
    gap: 6px;
}

.badge-success {
    background: #2e7d32;
    color: #ffffff;
    padding: 6px 10px;
    border-radius: 5px;
    font-size: 13px;
}

.badge-warning {
    background: #f9a825;
    color: #000000;
    padding: 6px 10px;
    border-radius: 5px;
    font-size: 13px;
}

.badge-danger {
    background: #c62828;
    color: #ffffff;
    padding: 6px 10px;
    border-radius: 5px;
    font-size: 13px;
}

.chart-card {
    background: #ffffff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
}

.chart-card canvas {
    max-height: 350px;
}

.table-responsive {
    overflow-x: auto;
}

@media (max-width: 768px) {
    .search-bar {
        flex-direction: column;
        align-items: stretch;
    }

    .search-bar .btn {
        width: 100%;
    }

    .table-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
```
