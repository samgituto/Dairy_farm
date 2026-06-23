<?php

include 'includes/db.php';
include 'includes/header.php';
include 'includes/sidebar.php';

$result = mysqli_query($conn,

"SELECT
milk_records.*,
cows.tag_number,
cows.cow_name

FROM milk_records

INNER JOIN cows
ON milk_records.cow_id = cows.id

ORDER BY record_date DESC

");

?>

<div class="main-content">

<div class="page-header">

<h2>Milk Production</h2>

<a href="record_milk.php"
class="btn-primary">

Record Milk

</a>

</div>

<div class="table-card">

<table class="custom-table">

<thead>

<tr>

<th>Date</th>
<th>Cow</th>
<th>Session</th>
<th>Litres</th>
<th>Remarks</th>

</tr>

</thead>

<tbody>

<?php while($row=mysqli_fetch_assoc($result)): ?>

<tr>

<td><?= $row['record_date']; ?></td>

<td>
<?= $row['tag_number']; ?>
-
<?= $row['cow_name']; ?>
</td>

<td><?= $row['session']; ?></td>

<td><?= $row['litres']; ?> L</td>

<td><?= $row['remarks']; ?></td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<?php include 'includes/footer.php'; ?>