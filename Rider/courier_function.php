<?php
require_once __DIR__ . '/../database/connect.php'; 

// Initialize errors array
$errors = [];

// --- 2. Handle Form Submissions (Insert or Update) ---
if (isset($_POST["add_courier"]) || isset($_POST["Update"])){

    // 2A. Retrieve and Sanitize Inputs
    $crr_id = $_POST['crrcode'] ?? null;
    $name = trim($_POST["crrname"] ?? '');
    $contact = trim($_POST["number"] ?? '');
    $location = trim($_POST["location"] ?? '');
    $vehicle = trim($_POST["vehicle"] ?? '');
    $availability = trim($_POST["availability"] ?? '');

    // 2B. Validation Checks (All fields must be checked for emptiness)
    
    // Validate Name (CRR_NAME)
    if($name === ''){
        $errors[] = "Name Is Required";
    }elseif(strlen($name) < 3){
        $errors[] = "Name Must Have More Than 3 Characters";
    }elseif (!preg_match('/^[a-zA-Z\s,]+$/', $name)) { 
        $errors[] = "Name Must Contain letters and commas only (No numbers allowed)"; 
    }
    
    // Check for duplicate names
    if(empty($errors)){
        $stmt = null;
        if($crr_id){
            $stmt = $conn -> prepare("SELECT CRR_ID FROM COURIER 
            WHERE CRR_NAME = ? AND CRR_ID <> ?");
            $stmt->bind_param("si", $name, $crr_id);
        }else{
            $stmt = $conn -> prepare("SELECT CRR_ID FROM COURIER 
            WHERE CRR_NAME = ?");
            $stmt->bind_param("s", $name);
        } 
        $stmt->execute();
        $stmt->store_result();
        if($stmt->num_rows > 0){
            $errors[] = "Name Already Exists";
        }
        $stmt->close();
    }
    
    // Validate Phone Number (CRR_PHONE_NUMBER)
    if($contact === ''){
        $errors[] = "Phone number is Required";
    }elseif(!ctype_digit($contact)){
        $errors[] = "Phone number must be numeric";
    }elseif(strlen($contact) < 10 || strlen($contact) > 15){ 
        $errors[] = "Phone Number must be between 10 and 15 digits (Global Max Length)."; 
    }
    
    // Check for duplicate phone numbers
    if(empty($errors)){
        $stmt = null;
        if($crr_id){
            $stmt = $conn -> prepare("SELECT CRR_ID FROM COURIER 
            WHERE CRR_PHONE_NUMBER = ? AND CRR_ID <> ?");
            $stmt->bind_param("si", $contact, $crr_id);
        }else{
            $stmt = $conn -> prepare("SELECT CRR_ID FROM COURIER 
            WHERE CRR_PHONE_NUMBER = ?");
            $stmt->bind_param("s", $contact);
        } 
        $stmt->execute();
        $stmt->store_result();
        if($stmt->num_rows > 0){
            $errors[] = "Phone Number Already Exists";
        }
        $stmt->close();
    }
    
    // Validate Location (CRR_LOCATION)
    if($location === ''){
        $errors[] = "Location is Required";
    } elseif(strlen($location) < 3){
        $errors[] = "Location Must be at least 3 Characters Long";
    } elseif (!preg_match('/^[a-zA-Z0-9\s,]+$/', $location)) {
        $errors[] = "Location must contain letters, numbers, and commas only";
    }
    
    // Validate Vechile (CRR_VECHILE)
    if (strlen($vehicle) > 50) { // Checks the maximum length constraint
        $errors[] = "Vechile details cannot exceed 50 characters";
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $vehicle)) {
        $errors[] = "Vehicle must contain letters and spaces only";
    }

    // Validate Availability (CRR_AVAILABILITY) - Default is 'true'
    $allowed_statuses = ['true', 'false']; // Define the exact allowed status strings
        // 1. Enforce Default
    if (trim($availability) === '') {
        $availability = 'true'; 
    }
        // 2. Normalize for Validation
    $normalized_availability = strtolower(trim($availability));
        // 3. Validation Check: If the normalized value is NOT one of the allowed ones, it must be an error.
    if (!in_array($normalized_availability, $allowed_statuses)) {
        $errors[] = "Courier status must be 'true' or 'false'.";
    }

    // Display Errors or Proceed to Database
    if(!empty($errors)){
        foreach($errors as $error){
            echo "<p style='color:red;'>Error: " . htmlspecialchars($error) ."</p>";
        }
    }else{ 
        // --- SECURE Database Operations ---
        
        if (isset($_POST['Update'])) {
            // Using a secure Prepared Statement for UPDATE
            $stmt = $conn->prepare("
                UPDATE COURIER 
                SET CRR_NAME = ?, 
                    CRR_PHONE_NUMBER = ?, 
                    CRR_LOCATION = ?,
                    CRR_VECHILE = ?,
                    CRR_is_AVAILABLE = ?
                WHERE CRR_ID = ?
            ");
            // 'ssssi' binds (Name, Phone, Location, Order, ID)
            $stmt->bind_param("sssssi", $name, $contact, $location, $vechile, $availability, $crr_id);
            
            if ($stmt->execute()) {
                echo "<p style='color:blue;'>Data Updated Successfully.</p>";
            } else {
                echo "<p style='color:red;'>Error Updating Data: " . htmlspecialchars($stmt->error) . "</p>";
            }
            $stmt->close();

        } elseif (isset($_POST["add_courier"])) {

            // Using a secure Prepared Statement for INSERT
            $stmt = $conn->prepare("
                INSERT INTO COURIER (CRR_NAME, CRR_PHONE_NUMBER, CRR_LOCATION, CRR_VECHILE, CRR_is_AVAILABLE) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssss", $name, $contact, $location, $vechile, $availability);
            
            if ($stmt->execute()) {
                echo "<p style='color:green;'>Data Added Successfully. ID: " . $conn->insert_id . "</p>";
            } else {
                 echo "<p style='color:red;'>Error Adding Data: " . htmlspecialchars($stmt->error) . "</p>";
            }
            $stmt->close();
        }
    }
}

// --- 3. Handle Delete Operation (Secure) ---
if (isset($_POST['Delete'])) {
    $Code = $_POST['crrcode'] ?? null;
    if ($Code) {
        $sim = $conn->prepare("DELETE FROM COURIER WHERE CRR_ID = ?");
        $sim->bind_param("i", $Code);
        $sim->execute();
        $sim->close();
        // Redirect to prevent form re-submission on refresh
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit();
    }
}

// --- 4. Read All Data (SELECT) ---
$display = mysqli_query($conn,"SELECT * FROM COURIER ORDER BY CRR_ID DESC");
?>

<html>
    <head>
        <title>Courier Table</title>
        <style>
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        </style>
    </head>
    <body>
        
        <h2>Add New Courier</h2>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <label>Name (A-Z only):</label> <input type="text" name="crrname" required><br>
            <label>Phone (10-15 digits):</label> <input type="text" name="number" required><br>
            <label>Location: </label> <input type="text" name="location" required><br>
            <label>Vechile: </label> <input type="text" name="vechile"><br>
            <label>Availability (true/false): </label> <input type="text" name="availability" value="true"><br>
            <button type="submit" name="add_courier">Add Courier</button>
        </form>
        <hr>

        <h2>Current Courier</h2>
        <table border = "1" cellpadding = "4" cellspacing = "0">
        <tr>
            <th>Courier ID</th>
            <th>Courier Name</th>
            <th>Courier Contact No.</th>
            <th>Courier Location</th>
            <th>Courier Vechile</th>
            <th>Courier Availability</th>
        </tr>
            <?php while ($row = mysqli_fetch_array($display)) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['CRR_ID']); ?></td>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <td><?php echo htmlspecialchars($row['CRR_NAME']); ?></td>
                <td><?php echo htmlspecialchars($row['CRR_PHONE_NUMBER']); ?></td>
                <td><?php echo htmlspecialchars($row['CRR_LOCATION']);?></td>
                <td><?php echo htmlspecialchars($row['CRR_VEHICLE']); ?></td>
                <td><input type="text" name="availability" value="<?php echo htmlspecialchars($row['CRR_is_AVAILABLE']); ?>"></td>
                <td><button type="submit" name="Update" style="color:blue;">Update</button></td>
                </form>
                <td>
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return confirm('Are you sure you want to delete this courier?');">
                    <input type="hidden" name="crrcode" value="<?php echo htmlspecialchars($row['CRR_ID']); ?>">
                    <button type="submit" name="Delete" style="color:red;">X</button>
                    </form>
                </td>
            </tr>
            <?php } ?>
        </table>
        <br>
    <p><a href = "user.html"><button>Back</button></a></p>
    </body>
</html>
