<?php

include 'includes/db.php';

if(isset($_POST['save_vaccine'])){

    $cow_id = mysqli_real_escape_string($conn, $_POST['cow_id']);
    $vaccine_name = mysqli_real_escape_string($conn, $_POST['vaccine_name']);
    $vaccination_date = mysqli_real_escape_string($conn, $_POST['vaccination_date']);
    $next_due_date = mysqli_real_escape_string($conn, $_POST['next_due_date']);
    $administered_by = mysqli_real_escape_string($conn, $_POST['administered_by']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    mysqli_query($conn,
        "INSERT INTO vaccinations (cow_id, vaccine_name, vaccination_date, next_due_date, administered_by, remarks) 
         VALUES ('$cow_id', '$vaccine_name', '$vaccination_date', '$next_due_date', '$administered_by', '$remarks')"
    );

    header("Location: vaccinations.php");
    exit(); 
}

$cows = mysqli_query($conn, "SELECT * FROM cows");

// --- START OF SEARCH LOGIC ---
$search_performed = false;
$vaccinations = null;

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_performed = true;
    $search_query = mysqli_real_escape_string($conn, trim($_GET['search']));
    
    // Specifically targets and filters down by the cow's name
    $where_clause = "WHERE cows.cow_name LIKE '%$search_query%'";
    
    $vaccinations = mysqli_query($conn,
        "SELECT vaccinations.*, cows.tag_number, cows.cow_name 
         FROM vaccinations 
         INNER JOIN cows ON vaccinations.cow_id = cows.id 
         $where_clause 
         ORDER BY vaccination_date DESC"
    );
}
// --- END OF SEARCH LOGIC ---

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="main-content">

    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>Vaccinations</h2>
        
        <!-- Search Form Component -->
        <form method="GET" action="" style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search by Cow Name..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
            <button type="submit" class="btn-primary" style="padding: 8px 15px;">Search</button>
            <?php if($search_performed): ?>
                <a href="vaccinations.php" style="padding: 8px; color: red; text-decoration: none;">Clear Search</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- The Input Form: Always visible, always saves to DB -->
    <div class="form-card">
        <form method="POST">
            <div class="form-grid">
                <select name="cow_id">
                    <?php while($cow=mysqli_fetch_assoc($cows)): ?>
                        <option value="<?= $cow['id']; ?>">
                            <?= $cow['tag_number']; ?> - <?= $cow['cow_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <input type="text" name="vaccine_name" placeholder="Vaccine Name">

                <input type="text" name="vaccination_date" placeholder="Select vaccination Date" onfocus="(this.type='date')" onblur="if(!this.value) this.type='text';">

                <input type="text" name="next_due_date" placeholder="Select Next Due Date" onfocus="(this.type='date')" onblur="if(!this.value) this.type='text';">

                <input type="text" name="administered_by" placeholder="Administered By">

                <textarea name="remarks" placeholder="Remarks"></textarea>
            </div>
            <button name="save_vaccine" class="btn-primary">Save Vaccination</button>
        </form>
    </div>

    <!-- Output Table Component: Only renders if a search was executed -->
    <?php if ($search_performed): ?>
        <div class="table-card" style="margin-top: 20px;">
            <h3>Search Results for: "<?= htmlspecialchars($_GET['search']); ?>"</h3>
            <table border="1" cellpadding="10" style="width:100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="background: #f4f4f4; text-align: left;">
                        <th>Tag Number</th>
                        <th>Cow Name</th>
                        <th>Vaccine</th>
                        <th>Vax Date</th>
                        <th>Next Due</th>
                        <th>Administered By</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($vaccinations) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($vaccinations)): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['tag_number']); ?></td>
                                <td><?= htmlspecialchars($row['cow_name']); ?></td>
                                <td><?= htmlspecialchars($row['vaccine_name']); ?></td>
                                <td><?= htmlspecialchars($row['vaccination_date']); ?></td>
                                <td><?= htmlspecialchars($row['next_due_date']); ?></td>
                                <td><?= htmlspecialchars($row['administered_by']); ?></td>
                                <td><?= htmlspecialchars($row['remarks']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #888;">No historical matches found for this cow name.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
