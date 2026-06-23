<?php

include 'includes/db.php';

if(isset($_POST['save_cow'])){

$tag = $_POST['tag_number'];
$name = $_POST['cow_name'];
$breed = $_POST['breed'];
$gender = $_POST['gender'];
$dob = $_POST['date_of_birth'];
$weight = $_POST['weight'];
$status = $_POST['status'];
$notes = $_POST['notes'];

mysqli_query(
$conn,

"INSERT INTO cows
(
tag_number,
cow_name,
breed,
gender,
date_of_birth,
weight,
status,
notes
)

VALUES
(
'$tag',
'$name',
'$breed',
'$gender',
'$dob',
'$weight',
'$status',
'$notes'
)"
);

header("Location:cows.php");

}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">

<div class="form-card">

<h2>Add New Cow</h2>

<form method="POST">

<div class="form-grid">

<input
type="text"
name="tag_number"
placeholder="Tag Number"
required>

<input
type="text"
name="cow_name"
placeholder="Cow Name">

<select name="breed">

<option>Holstein Friesian</option>
<option>Ayrshire</option>
<option>Jersey</option>
<option>Guernsey</option>

</select>

<select name="gender">

<option>Male</option>
<option>Female</option>

</select>

<input
type="date"
name="date_of_birth">

<input
type="number"
step="0.01"
name="weight"
placeholder="Weight">

<select name="status">

<option>Lactating</option>
<option>Pregnant</option>
<option>Dry</option>
<option>Heifer</option>
<option>Bull</option>
<option>Calf</option>

</select>

<textarea
name="notes"
placeholder="Additional Notes">
</textarea>

</div>

<button
class="btn-primary"
name="save_cow">

Save Cow

</button>

</form>

</div>

</div>

<?php include 'includes/footer.php'; ?>