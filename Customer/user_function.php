<?php
require_once __DIR__ . '/../database/connect.php'; 

// Initialize errors array
$errors = [];

// --- 2. Handle Form Submissions (Insert or Update) ---
if (isset($_POST["add_customer"]) || isset($_POST["Update"])){

    // 2A. Retrieve and Sanitize Inputs
    $cust_id = $_POST['custcode'] ?? null;
    $name = trim($_POST["cname"] ?? '');
    $contact = trim($_POST["number"] ?? '');
    $location = trim($_POST["location"] ?? '');
    $order = trim($_POST["order"] ?? ''); // Input variable for CUST_ORDER

    // 2B. Validation Checks (All fields must be checked for emptiness)
    
    // Validate Name (CUST_NAME)
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
        if($cust_id){
            $stmt = $conn -> prepare("SELECT CUST_ID FROM CUSTOMER 
            WHERE CUST_NAME = ? AND CUST_ID <> ?");
            $stmt->bind_param("si", $name, $cust_id);
        }else{
            $stmt = $conn -> prepare("SELECT CUST_ID FROM CUSTOMER 
            WHERE CUST_NAME = ?");
            $stmt->bind_param("s", $name);
        } 
        $stmt->execute();
        $stmt->store_result();
        if($stmt->num_rows > 0){
            $errors[] = "Name Already Exists";
        }
        $stmt->close();
    }
    
    // Validate Phone Number (CUST_PHONE_NUMBER)
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
        if($cust_id){
            $stmt = $conn -> prepare("SELECT CUST_ID FROM CUSTOMER 
            WHERE CUST_PHONE_NUMBER = ? AND CUST_ID <> ?");
            $stmt->bind_param("si", $contact, $cust_id);
        }else{
            $stmt = $conn -> prepare("SELECT CUST_ID FROM CUSTOMER 
            WHERE CUST_PHONE_NUMBER = ?");
            $stmt->bind_param("s", $contact);
        } 
        $stmt->execute();
        $stmt->store_result();
        if($stmt->num_rows > 0){
            $errors[] = "Phone Number Already Exists";
        }
        $stmt->close();
    }
    
    // Validate Location (CUST_LOCATION)
    if($location === ''){
        $errors[] = "Location is Required";
    } elseif(strlen($location) < 3){
        $errors[] = "Location Must be at least 3 Characters Long";
    } elseif (!preg_match('/^[a-zA-Z0-9\s,]+$/', $location)) {
        $errors[] = "Location must contain letters, numbers, and commas only";
    }
    
    if ($order === '') {
        $errors[] = "Order details are Required.";
    } elseif (strlen($order) > 50) { // Checks the maximum length constraint
        $errors[] = "Order details cannot exceed 50 characters";
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
                UPDATE CUSTOMER 
                SET CUST_NAME = ?, 
                    CUST_PHONE_NUMBER = ?, 
                    CUST_LOCATION = ?,
                    CUST_ORDER = ?
                WHERE CUST_ID = ?
            ");
            // 'ssssi' binds (Name, Phone, Location, Order, ID)
            $stmt->bind_param("ssssi", $name, $contact, $location, $order, $cust_id);
            
            if ($stmt->execute()) {
                echo "<p style='color:blue;'>Data Updated Successfully.</p>";
            } else {
                echo "<p style='color:red;'>Error Updating Data: " . htmlspecialchars($stmt->error) . "</p>";
            }
            $stmt->close();

        } elseif (isset($_POST["add_customer"])) {
            
            // ✅ FIX: Simplified INSERT - CUST_ORDER is now always included
            $stmt = $conn->prepare("
                INSERT INTO CUSTOMER (CUST_NAME, CUST_PHONE_NUMBER, CUST_LOCATION, CUST_ORDER) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("ssss", $name, $contact, $location, $order);
            
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
    $Code = $_POST['custcode'] ?? null;
    if ($Code) {
        $sim = $conn->prepare("DELETE FROM CUSTOMER WHERE CUST_ID = ?");
        $sim->bind_param("i", $Code);
        $sim->execute();
        $sim->close();
        // Redirect to prevent form re-submission on refresh
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit();
    }
}

// --- 4. Read All Data (SELECT) ---
$display = mysqli_query($conn,"SELECT * FROM CUSTOMER ORDER BY CUST_ID DESC");
?>

<html>
    <head>
        <title>Customer Table</title>
        <style>
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        </style>
    </head>
    <body>
        
        <h2>Add New Customer</h2>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <label>Name (A-Z only):</label> <input type="text" name="cname" required><br>
            <label>Phone (10-15 digits):</label> <input type="text" name="number" required><br>
            <label>Location:</label> <input type="text" name="location" required><br>
            <label>Order (Required):</label> <input type="text" name="order" required><br>
            <button type="submit" name="add_customer">Add Customer</button>
        </form>
        <hr>

        <h2>Current Customers</h2>
        <table border = "1" cellpadding = "4" cellspacing = "0">
        <tr>
            <th>Customer ID</th>
            <th>Customer Name</th>
            <th>Customer Contact No.</th>
            <th>Customer Location</th>
            <th>Customer Order</th>
        </tr>
            <?php while ($row = mysqli_fetch_array($display)) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['CUST_ID']); ?></td>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <input type="hidden" name="custcode" value="<?php echo htmlspecialchars($row['CUST_ID']); ?>">
                <td><input type="text" name="cname" value="<?php echo htmlspecialchars($row['CUST_NAME']); ?>" required></td>
                <td><input type="text" name="number" value="<?php echo htmlspecialchars($row['CUST_PHONE_NUMBER']); ?>" required></td>
                <td><input type="text" name="location" value="<?php echo htmlspecialchars($row['CUST_LOCATION']); ?>" required></td>
                <td><input type="text" name="order" value="<?php echo htmlspecialchars($row['CUST_ORDER']); ?>" required></td>    
                <td><button type="submit" name="Update" style="color:blue;">Update</button></td>
                </form>
                <td>
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return confirm('Are you sure you want to delete this customer?');">
                    <input type="hidden" name="custcode" value="<?php echo htmlspecialchars($row['CUST_ID']); ?>">
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
