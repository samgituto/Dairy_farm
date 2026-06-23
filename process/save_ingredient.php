<?php

include '../includes/db.php';

$code = $_POST['ingredient_code'];
$name = $_POST['ingredient_name'];
$category = $_POST['category'];
$stock = $_POST['current_stock'];
$cost = $_POST['unit_cost'];
$reorder = $_POST['reorder_level'];

mysqli_query(

$conn,

"INSERT INTO feed_ingredients(

ingredient_code,
ingredient_name,
category,
current_stock,
unit_cost,
reorder_level

)

VALUES(

'$code',
'$name',
'$category',
'$stock',
'$cost',
'$reorder'

)"

);

header("Location: ../feed_ingredients.php");