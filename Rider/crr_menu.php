<?php
require_once __DIR__ . '/../database/connect.php'; 

// Initialize errors array
$errors = [];

// --- 2. Handle Form Submissions (Update) ---
if (isset($_POST["Update"])){
    // 2A. Retrieve and Sanitize Inputs
    $del_id = $_POST['del_id'] ?? null; 
    $delivery_status = trim($_POST['DEL_STATUS'] ?? '');

    // 2B. Validation Checks (Only for DEL_STATUS)
    $allowed_statuses = ['Pending', 'In-Transit', 'Delivered', 'Cancelled']; 
    
    // NOTE: Removed strtolower() as ENUM values are case-sensitive.
    if ($delivery_status === '' || !in_array($delivery_status, $allowed_statuses)) {
        $errors[] = "Invalid or missing Delivery status. Must be one of: " . implode(', ', $allowed_statuses);
    }

    // Display Errors or Proceed to Database
    if(!empty($errors)){
        foreach($errors as $error){
            echo "<p style='color:red;'>Error: " . htmlspecialchars($error) ."</p>";
        }
    }else{ 
        // --- SECURE Database Operations ---
        if (isset($_POST['Update']) && $del_id) {
            $stmt = $conn->prepare("
                UPDATE DELIVERY 
                SET DEL_STATUS = ?
                WHERE DEL_ID = ?
            ");
            // 'si' binds (Status, ID)
            $stmt->bind_param("si", $delivery_status, $del_id);
            
            if ($stmt->execute()) {
                // Redirect to show updated status and prevent resubmission
                header("Location: " . $_SERVER['PHP_SELF']); 
                exit();
            } else {
                echo "<p style='color:red;'>Error Updating Data: " . htmlspecialchars($stmt->error) . "</p>";
            }
            $stmt->close();
        }
    }
}


// --- 4. Read Data for Current Courier ---
// NOTE: $courier_id should come from a secure session variable in a real app
$courier_id = 1; 

// Using Prepared Statement for Read (Secure and efficient)
$display = $conn->prepare("
    SELECT
        C.CUST_ID, 
        C.CUST_NAME, 
        C.CUST_PHONE_NUMBER, 
        C.CUST_LOCATION, 
        D.DEL_STATUS, 
        D.DEL_ID,(
        SELECT GROUP_CONCAT(P.PRD_NAME SEPARATOR '\n')
        FROM DELIVERY AS D2
        JOIN PRODUCT P ON D2.PRD_ID = P.PRD_ID
        WHERE D2.DEL_ID = D.DEL_ID
        GROUP BY D2.DEL_ID) as ITEMS_BOUGHT
    FROM
        CUSTOMER AS C
    JOIN
        DELIVERY AS D ON C.CUST_ID = D.CUST_ID
    WHERE
        D.CRR_ID = ?
    GROUP BY D.DEL_ID, C.CUST_ID, C.CUST_NAME, C.CUST_PHONE_NUMBER
    ORDER BY D.DEL_TIMESTAMP DESC
");

if ($display === false) {
    die("Error preparing statement: " . $conn->error);
}

$display->bind_param("i", $courier_id);
if(!$display->execute()){
    die("Error: " . $display->error);
}

$display_result = $display->get_result();
$display->close();
?>

<html>
    <head>
        <title>Courier Menu</title>
        <style>
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        </style>
    </head>
    <body>

        <h1>Courier Menu (Courier ID: <?php echo $courier_id; ?>)</h1>

        <h2>Deliveries Assigned</h2>
        <table border = "1" cellpadding = "4" cellspacing = "0">
        <tr>
            <th>Customer ID</th>
            <th>Customer Name</th>
            <th>Contact No.</th>
            <th>Location</th>
            <th>Order</th>
            <th>Delivery Status</th>
            <th>Update</th>
        </tr>
            <?php while ($row = $display_result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['CUST_ID']); ?></td>
                <td><?php echo htmlspecialchars($row['CUST_NAME']); ?></td>
                <td><?php echo htmlspecialchars($row['CUST_PHONE_NUMBER']); ?></td>
                <td><?php echo htmlspecialchars($row['CUST_LOCATION']); ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['ITEMS_BOUGHT'])); ?></td>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <input type="hidden" name="del_id" value="<?php echo htmlspecialchars($row['DEL_ID']); ?>"> 

                    <td>
                        <select name="DEL_STATUS">
                            <option value="Pending" <?php if ($row['DEL_STATUS'] == 'Pending') echo 'selected'; ?>>Pending</option>
                            <option value="In-Transit" <?php if ($row['DEL_STATUS'] == 'In-Transit') echo 'selected'; ?>>In-Transit</option>
                            <option value="Delivered" <?php if ($row['DEL_STATUS'] == 'Delivered') echo 'selected'; ?>>Delivered</option>
                            <option value="Cancelled" <?php if ($row['DEL_STATUS'] == 'Cancelled') echo 'selected'; ?>>Cancelled</option>
                        </select>
                    </td>
                    
                    <td><button type="submit" name="Update" style="color:blue;">Update</button></td>
                </form>
                
            </tr>
            <?php } ?>
        </table>
        <br>
    <p><a href = "user.html"><button>Back</button></a></p>
    </body>
</html>
