<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "dairy_farm_db";

$conn = mysqli_connect(
    $host,
    $user,
    $password,
    $database
);

if(!$conn){
    die("Database Connection Failed");
}
?>