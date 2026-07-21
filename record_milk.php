<?php 
include 'includes/db.php'; 
include 'includes/header.php'; 
include 'includes/sidebar.php'; 

$error_message = "";
$success_message = "";

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cow_id      = trim($_POST['cow_id']);
    $record_date = trim($_POST['record_date']);
    $session     = trim($_POST['session']);
    $litres      = trim($_POST['litres']);
    $remarks     = trim($_POST['remarks']);

    // Basic Validation
    if (empty($cow_id) || empty($record_date) || empty($session) || empty($litres)) {
        $error_message = "All fields except remarks are required.";
    } else {
        // PREVENT DUPLICATE SESSION ENTRIES: Check if record exists
        $check_sql = "SELECT id FROM milk_records WHERE cow_id = ? AND record_date = ? AND session = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "iss", $cow_id, $record_date, $session);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            // Duplicate found
            $error_message = "Error: This cow already has a milk record submitted for the selected date and session.";
            mysqli_stmt_close($check_stmt);
        } else {
            mysqli_stmt_close($check_stmt);

            // No duplicate: Proceed with insertion
            $insert_sql = "INSERT INTO milk_records (cow_id, record_date, session, litres, remarks) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "issds", $cow_id, $record_date, $session, $litres, $remarks);

            if (mysqli_stmt_execute($insert_stmt)) {
                $success_message = "Milk record saved successfully!";
            } else {
                $error_message = "Something went wrong. Please try again. " . mysqli_error($conn);
            }
            mysqli_stmt_close($insert_stmt);
        }
    }
}

// Fetch cows for the dropdown list
$cows_query = "SELECT id, tag_number, cow_name FROM cows ORDER BY tag_number ASC";
$cows_result = mysqli_query($conn, $cows_query);
?>

<div class="main-content">
    <div class="page-header" style="margin-bottom: 20px;">
        <h2>Record Milk Production</h2>
        <a href="milk.php" class="btn-secondary" style="text-decoration: none; padding: 8px 15px; background: #6c757d; color: white; border-radius: 4px;">Back to Records</a>
    </div>

    <!-- Feedback Alerts -->
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" style="background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
            <?= htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success" style="background-color: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            <?= htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="form-card" style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 600px;">
        <form action="record_milk.php" method="POST">
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label for="record_date" style="display: block; font-weight: bold; margin-bottom: 5px;">Date</label>
                <input type="date" name="record_date" id="record_date" class="form-control" value="<?= date('Y-m-d'); ?>" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="cow_id" style="display: block; font-weight: bold; margin-bottom: 5px;">Select Cow</label>
                <select name="cow_id" id="cow_id" class="form-control" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">-- Choose Cow --</option>
                    <?php while($cow = mysqli_fetch_assoc($cows_result)): ?>
                        <option value="<?= $cow['id']; ?>">
                            <?= htmlspecialchars($cow['tag_number'] . ' - ' . $cow['cow_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="session" style="display: block; font-weight: bold; margin-bottom: 5px;">Milking Session</label>
                <select name="session" id="session" class="form-control" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">-- Choose Session --</option>
                    <option value="Morning">Morning</option>
                    <option value="Noon">Noon</option>
                    <option value="Evening">Evening</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label for="litres" style="display: block; font-weight: bold; margin-bottom: 5px;">Milk Yield (Litres)</label>
                <input type="number" step="0.01" name="litres" id="litres" class="form-control" placeholder="e.g. 12.5" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="remarks" style="display: block; font-weight: bold; margin-bottom: 5px;">Remarks / Notes</label>
                <textarea name="remarks" id="remarks" class="form-control" rows="3" placeholder="Any health or feeding context..." style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; resize: vertical;"></textarea>
            </div>

            <button type="submit" class="btn-primary" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; font-weight: bold; cursor: pointer;">
                Save Milk Record
            </button>
            
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>