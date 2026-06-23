<?php

include 'includes/db.php';

$id = $_GET['id'];

mysqli_query(
$conn,
"DELETE FROM cows WHERE id='$id'"
);

header("Location:cows.php");