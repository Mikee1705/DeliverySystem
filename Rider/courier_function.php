<?php
require_once __DIR__ . '/../database/connect.php';

$errors = [];
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST['update_courier'])) {

        $cid = $_POST['crrcode'] ?? null;
        $availability = $_POST['availability'] ?? null;

        if ($availability === null) {
        $result = $conn->query("SELECT CRR_AVAILABILITY FROM COURIER WHERE CRR_ID = '$cid'");
        $row = $result->fetch_assoc();
        $availability = $row['CRR_AVAILABILITY'];
        }

        $stmt = $conn->prepare("
            UPDATE COURIER
            SET CRR_availability = ?
            WHERE CRR_ID = ?
        ");

        $stmt->bind_param("ii", $availability, $cid);
        $stmt->execute();

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}    

if (isset($_POST["add_courier"])) {


    $crr_id   = $_POST['crrcode'] ?? null;
    $name     = trim($_POST["crrname"] ?? '');   
    $contact  = trim($_POST["number"] ?? '');
    $location = trim($_POST["location"] ?? '');
    $vehicle  = trim($_POST["vehicle"] ?? '');  
    $availability = $_POST["availability"] ?? 0;



    // Name
    if ($name === '') {
        $errors[] = "Name is required";
    } elseif (strlen($name) < 3) {
        $errors[] = "Name must have at least 3 characters";
    } elseif (!preg_match('/^[a-zA-Z\s,]+$/', $name)) {
        $errors[] = "Name must contain only letters and commas";
    }

    // Duplicate NAME
    if (empty($errors)) {
        if ($crr_id) {
            $stmt = $conn->prepare("SELECT CRR_ID FROM COURIER WHERE CRR_NAME = ? AND CRR_ID <> ?");
            $stmt->bind_param("si", $name, $crr_id);
        } else {
            $stmt = $conn->prepare("SELECT CRR_ID FROM COURIER WHERE CRR_NAME = ?");
            $stmt->bind_param("s", $name);
        }

        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Courier name already exists";
        }

        $stmt->close();
    }

    // Phone Number
    if ($contact === '') {
        $errors[] = "Phone number is required";
    } elseif (!ctype_digit($contact)) {
        $errors[] = "Phone must be numeric";
    } elseif (!preg_match('/^09\d{9}$/', $contact)) {
        $errors[] = "Phone number must start with 09 and be 11 digits long";
    }


    // Duplicate CONTACT
    if (empty($errors)) {
        if ($crr_id) {
            $stmt = $conn->prepare("SELECT CRR_ID FROM COURIER WHERE CRR_PHONE_NUMBER = ? AND CRR_ID <> ?");
            $stmt->bind_param("si", $contact, $crr_id);
        } else {
            $stmt = $conn->prepare("SELECT CRR_ID FROM COURIER WHERE CRR_PHONE_NUMBER = ?");
            $stmt->bind_param("s", $contact);
        }

        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Phone number already exists";
        }

        $stmt->close();
    }

    // Location
    if ($location === '') {
        $errors[] = "Location is required";
    } elseif (strlen($location) < 3) {
        $errors[] = "Location must be at least 3 characters long";
    } elseif (!preg_match('/^[a-zA-Z0-9\s,]+$/', $location)) {
        $errors[] = "Location must contain letters, numbers, and commas only";
    }


    if (!empty($errors)) {
        foreach ($errors as $error) {
            echo "<p style='color:red;'>Error: " . htmlspecialchars($error) . "</p>";
        }
    } else{

        $stmt = $conn->prepare("
            INSERT INTO COURIER (CRR_NAME, CRR_PHONE_NUMBER, CRR_LOCATION, CRR_VEHICLE, CRR_AVAILABILITY)
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




$sql = "SELECT 
CRR_ID,
CRR_NAME,
CRR_PHONE_NUMBER,
CRR_LOCATION,
CRR_VEHICLE,
CASE 
    WHEN CRR_AVAILABILITY = 1 THEN 'YES'
    ELSE 'NO'
END AS CRR_AVAILABILITY
FROM COURIER";
$display = mysqli_query($conn, $sql);
if ($display === false) {
    die("Error in SQL query: " . mysqli_error($conn) . "<br>Query was: " . $sql);
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
                <td><?php echo htmlspecialchars($row['CRR_AVAILABILITY']); ?></td>
                <td>
               

               <input type="hidden" name="crrcode" value="<?= $row['CRR_ID'] ?>">

                <label>
                   <input type="radio" name="availability" value="1"> Available
                </label>

                <label>
                  <input type="radio" name="availability" value="0"> Unavailable
                </label>
                  <button type="submit" name="update_courier">Update</button>

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
    <p><a href = "/DeliverySystem/Admin/admin.html"><button>Back</button></a></p>
    <p><a href = "crr_menu.php"><button>View Deliveries</button></a></p>
    </body>
</html>
