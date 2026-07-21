<?php
session_start();

include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: formulations.php");
    exit();
}

$formulation_id = (int) $_GET['id'];

mysqli_query($conn, "
    DELETE FROM feed_formulations
    WHERE formulation_id = '$formulation_id'
");

header("Location: formulations.php?deleted=1");
exit();