<?php
include 'includes/db.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Use a prepared statement to securely delete the record
    $stmt = mysqli_prepare($conn, "DELETE FROM milk_records WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Redirect back to your main milk production page
header("Location: milk.php");
exit();
?>