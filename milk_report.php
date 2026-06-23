<?php

include 'includes/db.php';
include 'includes/header.php';
include 'includes/sidebar.php';

$report =
mysqli_query(

$conn,

"SELECT

record_date,

SUM(litres)

AS total

FROM milk_records

GROUP BY record_date

ORDER BY record_date DESC"

);

?>

<div class="main-content">

<h2>

Milk Production Report

</h2>

<div class="table-card">

<table class="custom-table">

<tr>

<th>Date</th>

<th>Total Litres</th>

</tr>

<?php while($row=mysqli_fetch_assoc($report)): ?>

<tr>

<td><?= $row['record_date']; ?></td>

<td><?= $row['total']; ?></td>

</tr>

<?php endwhile; ?>

</table>

</div>

</div>

<?php include 'includes/footer.php'; ?>