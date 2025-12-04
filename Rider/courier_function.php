<?php
require_once __DIR__ . '/../database/connect.php';

$errors = [];

// ADD OR UPDATE
if (isset($_POST["add_courier"]) || isset($_POST["Update"])) {

    // 1. CAPTURE ID (Check both possible input names)
    $crr_id = $_POST['crrcode'] ?? $_POST['crr_id'] ?? null;
    
    $name     = trim($_POST["crrname"] ?? '');   
    $contact  = trim($_POST["number"] ?? '');
    $location = trim($_POST["location"] ?? '');
    $vehicle  = trim($_POST["vehicle"] ?? '');  
    $availability = $_POST["availability"] ?? 0;

    // ---------------- VALIDATION (Only run for Adding) ----------------
    if (isset($_POST["add_courier"])) {
        // Name
        if ($name === '') {
            $errors[] = "Name is required";
        } elseif (strlen($name) < 3) {
            $errors[] = "Name must have at least 3 characters";
        } elseif (!preg_match('/^[a-zA-Z\s,]+$/', $name)) {
            $errors[] = "Name must contain only letters and commas";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT CRR_ID FROM COURIER WHERE CRR_NAME = ?");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $errors[] = "Courier name already exists";
            $stmt->close();
        }

        // Phone Number
        if ($contact === '') {
            $errors[] = "Phone number is required";
        } elseif (!ctype_digit($contact)) {
            $errors[] = "Phone must be numeric";
        } elseif (strlen($contact) != 11) {
            $errors[] = "Phone must be exactly 11 digits";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT CRR_ID FROM COURIER WHERE CRR_PHONE_NUMBER = ?");
            $stmt->bind_param("s", $contact);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $errors[] = "Phone number already exists";
            $stmt->close();
        }

        // Location
        if ($location === '') {
            $errors[] = "Location is required";
        }
    }

    // ---------------- IF ERRORS ----------------
    if (!empty($errors)) {
        foreach ($errors as $error) {
            echo "<p style='color:red;'>Error: " . htmlspecialchars($error) . "</p>";
        }
    }

    // ---------------- UPDATE LOGIC ----------------
    elseif (isset($_POST["Update"])) {
        // FIXED COLUMN NAME: CRR_is_AVAILABLE
        $stmt = $conn->prepare("
            UPDATE COURIER 
            SET CRR_is_AVAILABLE = ?
            WHERE CRR_ID = ?
        ");

        $stmt->bind_param("ii", $availability, $crr_id);

        if ($stmt->execute()) {
            // Success - refresh page
            echo "<script>alert('Status Updated Successfully'); window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
            exit();
        } else {
            echo "Error updating: " . $conn->error;
        }
        $stmt->close();
    }

    // ---------------- INSERT LOGIC ----------------
    elseif (isset($_POST["add_courier"])) {
        // FIXED COLUMN NAME: CRR_is_AVAILABLE
        $stmt = $conn->prepare("
            INSERT INTO COURIER (CRR_NAME, CRR_PHONE_NUMBER, CRR_LOCATION, CRR_VEHICLE, CRR_is_AVAILABLE)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("ssssi", $name, $contact, $location, $vehicle, $availability);

        if ($stmt->execute()) {
            echo "<p style='color:green;'>Courier Added Successfully! ID: " . $conn->insert_id . "</p>";
        } else {
            echo "<p style='color:red;'>Error Adding Data: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}

// ---------------- DELETE ----------------
if (isset($_POST['Delete'])) {
    $Code = $_POST['crrcode'] ?? null;

    if ($Code) {
        $stmt = $conn->prepare("DELETE FROM COURIER WHERE CRR_ID = ?");
        $stmt->bind_param("i", $Code);
        $stmt->execute();
        $stmt->close();

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// ---------------- SELECT ----------------
// We select the raw column CRR_is_AVAILABLE here
$sql = "SELECT 
CRR_ID,
CRR_NAME,
CRR_PHONE_NUMBER,
CRR_LOCATION,
CRR_VEHICLE,
CRR_is_AVAILABLE
FROM COURIER";

$display = mysqli_query($conn, $sql);
if ($display === false) {
    die("Error in SQL query: " . mysqli_error($conn));
}
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
        
        <h2>Add New Courier</h2>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <label>Name (A-Z only):</label> <input type="text" name="crrname" required><br>
            <label>Phone (11 digits):</label> <input type="text" name="number" required><br>
            <label>Location: </label> <input type="text" name="location" required><br>
            <label>Vehicle: </label> <input type="text" name="vehicle"><br>
            <label>Availability: </label>
            <input type="radio" id="available_yes" name="availability" value="1" checked>
            <label for="available_yes">Available</label>
    
            <input type="radio" id="available_no" name="availability" value="0">
            <label for="available_no">Unavailable</label><br>
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
            <th>Courier Vehicle</th>
            <th>Availability</th>
            <th>Update Status</th>
            <th>Delete</th>
        </tr>
            <?php while ($row = mysqli_fetch_array($display)) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['CRR_ID']); ?></td>
                <td><?php echo htmlspecialchars($row['CRR_NAME']); ?></td>
                <td><?php echo htmlspecialchars($row['CRR_PHONE_NUMBER']); ?></td>
                <td><?php echo htmlspecialchars($row['CRR_LOCATION']);?></td>
                <td><?php echo htmlspecialchars($row['CRR_VEHICLE']); ?></td>
                
                <td>
                    <?php echo ($row['CRR_is_AVAILABLE'] == 1) ? 'YES' : 'NO'; ?>
                </td>

                <td>
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display: inline;">
                        <input type="hidden" name="crr_id" value="<?php echo $row['CRR_ID']; ?>">
                    
                        <input type="radio" 
                            name="availability" 
                            value="1"
                            <?php echo ($row['CRR_is_AVAILABLE'] == 1) ? 'checked' : ''; ?>
                            > Yes
                    
                        <input type="radio" 
                            name="availability" 
                            value="0"
                            <?php echo ($row['CRR_is_AVAILABLE'] == 0) ? 'checked' : ''; ?>
                            > No
                    
                        <button type="submit" name="Update" style="color:blue;">Update</button>
                    </form>
                </td>

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
    </body>
</html>