<?php

session_start();

include 'includes/db.php';

include 'includes/header.php';
include 'includes/sidebar.php';

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

// Total Feed Stock

$totalStockQuery = mysqli_query(

$conn,

"SELECT
SUM(current_stock) AS total_stock

FROM inventory"

);

$totalStock = mysqli_fetch_assoc($totalStockQuery);

// Low Stock Count

$lowStockCountQuery = mysqli_query(

$conn,

"SELECT COUNT(*) AS total

FROM feed_ingredients

WHERE current_stock <= reorder_level"

);

$lowStockCount = mysqli_fetch_assoc($lowStockCountQuery);

// Monthly Feed Cost

$monthlyCostQuery = mysqli_query(

$conn,

"SELECT
SUM(total_cost) AS total_cost

FROM feed_batches

WHERE MONTH(batch_date)=MONTH(CURDATE())
AND YEAR(batch_date)=YEAR(CURDATE())"

);

$monthlyCost = mysqli_fetch_assoc($monthlyCostQuery);

// Feed Batches

$batchCountQuery = mysqli_query(

$conn,

"SELECT COUNT(*) AS total

FROM feed_batches"

);

$batchCount = mysqli_fetch_assoc($batchCountQuery);

// Low Stock Items

$lowStockItems = mysqli_query(

$conn,

"SELECT *

FROM feed_ingredients

WHERE current_stock <= reorder_level

ORDER BY current_stock ASC"

);

// Recent Transactions

$transactions = mysqli_query(

$conn,

"SELECT

inventory_transactions.*,

feed_ingredients.ingredient_name

FROM inventory_transactions

INNER JOIN feed_ingredients

ON inventory_transactions.ingredient_id =
feed_ingredients.id

ORDER BY transaction_date DESC

LIMIT 10"

);

// Feed Usage Chart

$chartQuery = mysqli_query(

$conn,

"SELECT

usage_date,

SUM(quantity_used) AS total

FROM feed_usage

GROUP BY usage_date

ORDER BY usage_date ASC

LIMIT 7"

);

$labels = [];
$data = [];

while($row=mysqli_fetch_assoc($chartQuery))
{

    $labels[] = date(
        "d M",
        strtotime($row['usage_date'])
    );

    $data[] = $row['total'];
}

?>

<div class="main-content">

    <div class="page-header">

        <h2>Feed Analytics Dashboard</h2>

    </div>

    <!-- Analytics Cards -->

    <div class="cards">

        <div class="card">

            <div class="card-icon">

                <i class="fas fa-warehouse"></i>

            </div>

            <div>

                <h4>Total Feed Stock</h4>

                <h2>

                    <?=
                    number_format(
                    $totalStock['total_stock'] ?? 0
                    );
                    ?>

                    kg

                </h2>

            </div>

        </div>

        <div class="card">

            <div class="card-icon">

                <i class="fas fa-triangle-exclamation"></i>

            </div>

            <div>

                <h4>Low Stock Items</h4>

                <h2>

                    <?= $lowStockCount['total']; ?>

                </h2>

            </div>

        </div>

        <div class="card">

            <div class="card-icon">

                <i class="fas fa-money-bill-wave"></i>

            </div>

            <div>

                <h4>Monthly Feed Cost</h4>

                <h2>

                    KES

                    <?=
                    number_format(
                    $monthlyCost['total_cost'] ?? 0
                    );
                    ?>

                </h2>

            </div>

        </div>

        <div class="card">

            <div class="card-icon">

                <i class="fas fa-industry"></i>

            </div>

            <div>

                <h4>Total Feed Batches</h4>

                <h2>

                    <?= $batchCount['total']; ?>

                </h2>

            </div>

        </div>

    </div>

    <!-- Feed Usage Chart -->

    <div class="chart-card">

        <h3>Feed Usage Trend</h3>

        <canvas id="feedChart"></canvas>

    </div>

    <!-- Low Stock Alerts -->

    <div class="table-card">

        <h3>

            <i class="fas fa-triangle-exclamation"></i>

            Low Stock Alerts

        </h3>

        <table class="custom-table">

            <thead>

                <tr>

                    <th>Ingredient</th>
                    <th>Current Stock</th>
                    <th>Reorder Level</th>
                    <th>Status</th>

                </tr>

            </thead>

            <tbody>

                <?php while($row=mysqli_fetch_assoc($lowStockItems)): ?>

                <tr>

                    <td>

                        <?= $row['ingredient_name']; ?>

                    </td>

                    <td>

                        <?= $row['current_stock']; ?>

                        kg

                    </td>

                    <td>

                        <?= $row['reorder_level']; ?>

                        kg

                    </td>

                    <td>

                        <span class="badge-danger">

                            Reorder Required

                        </span>

                    </td>

                </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

    <!-- Inventory Transactions -->

    <div class="table-card">

        <h3>

            Recent Inventory Transactions

        </h3>

        <table class="custom-table">

            <thead>

                <tr>

                    <th>Date</th>
                    <th>Ingredient</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Remarks</th>

                </tr>

            </thead>

            <tbody>

                <?php while($row=mysqli_fetch_assoc($transactions)): ?>

                <tr>

                    <td>

                        <?= $row['transaction_date']; ?>

                    </td>

                    <td>

                        <?= $row['ingredient_name']; ?>

                    </td>

                    <td>

                        <?php

                        if(
                        $row['transaction_type']
                        == 'IN'
                        )
                        {
                            echo
                            "<span class='badge-success'>
                            STOCK IN
                            </span>";
                        }
                        else
                        {
                            echo
                            "<span class='badge-danger'>
                            STOCK OUT
                            </span>";
                        }

                        ?>

                    </td>

                    <td>

                        <?= $row['quantity']; ?>

                        kg

                    </td>

                    <td>

                        <?= $row['remarks']; ?>

                    </td>

                </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</div>

<script>

const ctx =
document.getElementById('feedChart');

new Chart(ctx, {

    type: 'line',

    data: {

        labels:
        <?= json_encode($labels); ?>,

        datasets: [{

            label:
            'Feed Usage (kg)',

            data:
            <?= json_encode($data); ?>,

            tension:0.4,

            fill:false

        }]

    },

    options: {

        responsive:true,

        plugins:{

            legend:{

                display:true

            }

        },

        scales:{

            y:{

                beginAtZero:true

            }

        }

    }

});

</script>

<?php include 'includes/footer.php'; ?>