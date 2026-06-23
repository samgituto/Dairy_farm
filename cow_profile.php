<?php

include 'includes/db.php';

$id = $_GET['id'];

$result = mysqli_query(
$conn,
"SELECT * FROM cows WHERE id='$id'"
);

$cow = mysqli_fetch_assoc($result);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

<div class="profile-card">

<h2><?= $cow['cow_name']; ?></h2>

<table class="profile-table">

<tr>
<td>Tag Number</td>
<td><?= $cow['tag_number']; ?></td>
</tr>

<tr>
<td>Breed</td>
<td><?= $cow['breed']; ?></td>
</tr>

<tr>
<td>Gender</td>
<td><?= $cow['gender']; ?></td>
</tr>

<tr>
<td>Weight</td>
<td><?= $cow['weight']; ?> Kg</td>
</tr>

<tr>
<td>Status</td>
<td><?= $cow['status']; ?></td>
</tr>

<tr>
<td>Date of Birth</td>
<td><?= $cow['date_of_birth']; ?></td>
</tr>

<tr>
<td>Notes</td>
<td><?= $cow['notes']; ?></td>
</tr>

</table>

</div>

</div>

<?php include 'includes/footer.php'; ?>