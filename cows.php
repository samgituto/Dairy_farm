<?php

session_start();
include 'includes/db.php';

$result = mysqli_query(
$conn,
"SELECT * FROM cows ORDER BY id DESC"
);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

<div class="page-header">

<h2>Herd Management</h2>

<a href="add_cow.php" class="btn-primary">

<i class="fas fa-plus"></i>
Add Cow

</a>

</div>

<div class="table-card">

<table class="custom-table">

<thead>

<tr>

<th>Tag No</th>
<th>Name</th>
<th>Breed</th>
<th>Gender</th>
<th>Status</th>
<th>Weight</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while($cow = mysqli_fetch_assoc($result)): ?>

<tr>

<td><?= $cow['tag_number']; ?></td>

<td><?= $cow['cow_name']; ?></td>

<td><?= $cow['breed']; ?></td>

<td><?= $cow['gender']; ?></td>

<td><?= $cow['status']; ?></td>

<td><?= $cow['weight']; ?> Kg</td>

<td>

<a href="cow_profile.php?id=<?= $cow['id']; ?>">

View

</a>

|

<a href="edit_cow.php?id=<?= $cow['id']; ?>">

Edit

</a>

|

<a
onclick="return confirm('Delete cow?')"
href="delete_cow.php?id=<?= $cow['id']; ?>">

Delete

</a>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<?php include 'includes/footer.php'; ?>