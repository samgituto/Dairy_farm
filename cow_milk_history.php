<?php

include 'includes/db.php';

$id = $_GET['id'];

$cow =
mysqli_fetch_assoc(

mysqli_query(
$conn,
"SELECT * FROM cows
WHERE id='$id'"
)

);

$milk =
mysqli_query(

$conn,

"SELECT *

FROM milk_records

WHERE cow_id='$id'

ORDER BY record_date DESC"

);

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="main-content">

<h2>

<?= $cow['cow_name']; ?>

Milk History

</h2>

<div class="table-card">

<table class="custom-table">

<tr>

<th>Date</th>
<th>Session</th>
<th>Litres</th>

</tr>

<?php while($row=mysqli_fetch_assoc($milk)): ?>

<tr>

<td><?= $row['record_date']; ?></td>

<td><?= $row['session']; ?></td>

<td><?= $row['litres']; ?></td>

</tr>

<?php endwhile; ?>

</table>

</div>

</div>

<?php include 'includes/footer.php'; ?>