<?php

include 'includes/db.php';

$formulas = mysqli_query(

$conn,

"SELECT *

FROM feed_formulations

ORDER BY formula_name"

);

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="main-content">

<div class="page-header">

<h2>Feed Formulations</h2>

<a href="add_formulation.php"
class="btn-primary">

New Formula

</a>

</div>

<div class="table-card">

<table class="custom-table">

<thead>

<tr>

<th>Formula Code</th>
<th>Name</th>
<th>Category</th>
<th>Batch Size</th>

</tr>

</thead>

<tbody>

<?php while($row=mysqli_fetch_assoc($formulas)): ?>

<tr>

<td><?= $row['formula_code']; ?></td>

<td>

<a href="formulation_details.php?id=<?= $row['id']; ?>">

<?= $row['formula_name']; ?>

</a>

</td>

<td><?= $row['animal_category']; ?></td>

<td><?= $row['batch_size']; ?> kg</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<?php include 'includes/footer.php'; ?>