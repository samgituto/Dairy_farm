<?php

include 'includes/db.php';

$full_name = $_POST['full_name'];

$email = $_POST['email'];

$password =
password_hash(
$_POST['password'],
PASSWORD_DEFAULT
);

$role = $_POST['role'];

$sql =
"INSERT INTO users
(
full_name,
email,
password,
role
)

VALUES
(
'$full_name',
'$email',
'$password',
'$role'
)";

mysqli_query($conn,$sql);

header("Location: login.php");