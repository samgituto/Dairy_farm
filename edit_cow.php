<?php

include 'includes/db.php';

$id = $_GET['id'];

$result =
mysqli_query(
$conn,
"SELECT * FROM cows WHERE id='$id'"
);

$cow =
mysqli_fetch_assoc($result);

if(isset($_POST['update_cow'])){

$name = $_POST['cow_name'];
$weight = $_POST['weight'];
$status = $_POST['status'];

mysqli_query(
$conn,

"UPDATE cows
SET

cow_name='$name',
weight='$weight',
status='$status'

WHERE id='$id'"
);

header("Location:cows.php");
}
?>

<form method="POST">

<input
type="text"
name="cow_name"
value="<?= $cow['cow_name']; ?>">

<input
type="number"
step="0.01"
name="weight"
value="<?= $cow['weight']; ?>">

<select name="status">

<option><?= $cow['status']; ?></option>

<option>Lactating</option>
<option>Pregnant</option>
<option>Dry</option>

</select>

<button name="update_cow">

Update

</button>

</form>
<?php include 'includes/footer.php'; ?>