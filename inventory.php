<?php

include 'includes/db.php';

$totalStock = mysqli_fetch_assoc(

mysqli_query(

$conn,

"SELECT
SUM(current_stock)
AS total_stock

FROM feed_ingredients"

)

);

$lowStock = mysqli_query(

$conn,

"SELECT *

FROM feed_ingredients

WHERE current_stock
<= reorder_level"

);

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="main-content">

<div class="cards">

<div class="card">

<h3>Total Feed Stock</h3>

<h1>

<?= number_format($totalStock['total_stock']); ?>

kg

</h1>

</div>

</div>

<div class="table-card">

<h3>Low Stock Alerts</h3>

<table class="custom-table">

<?php while($row=mysqli_fetch_assoc($lowStock)): ?>

<tr>

<td><?= $row['ingredient_name']; ?></td>

<td><?= $row['current_stock']; ?> kg</td>

</tr>

<?php endwhile; ?>

</table>

</div>

</div>

<?php include 'includes/footer.php'; ?>