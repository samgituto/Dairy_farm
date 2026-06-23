<?php

include '../includes/db.php';

$formula_code =
$_POST['formula_code'];

$formula_name =
$_POST['formula_name'];

$animal_category =
$_POST['animal_category'];

$batch_size =
$_POST['batch_size'];

mysqli_query(

$conn,

"INSERT INTO feed_formulations(

formula_code,
formula_name,
animal_category,
batch_size

)

VALUES(

'$formula_code',
'$formula_name',
'$animal_category',
'$batch_size'

)"

);

$formulation_id =
mysqli_insert_id($conn);

$ingredient_ids =
$_POST['ingredient_ids'];

$percentages =
$_POST['percentage'];

$weights =
$_POST['weight'];

for(
$i=0;
$i<count($ingredient_ids);
$i++
){

if($weights[$i] > 0){

mysqli_query(

$conn,

"INSERT INTO formulation_ingredients(

formulation_id,
ingredient_id,
percentage,
weight_kg

)

VALUES(

'$formulation_id',
'{$ingredient_ids[$i]}',
'{$percentages[$i]}',
'{$weights[$i]}'

)"

);

}

}

header(
"Location: ../feed_formulations.php"
);