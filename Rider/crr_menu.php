<?php
require_once __DIR__ . '/../database/connect.php'; 

$errors = [];

if (isset($_POST["Update"])){
    
    $target_cust_id = $_POST['cust_id'] ?? null;
    $target_timestamp = $_POST['timestamp'] ?? null;
    $new_status = trim($_POST['DEL_STATUS'] ?? '');

    $allowed_statuses = ['Pending', 'In-Transit', 'Delivered', 'Cancelled']; 
    
    if ($new_status === '' || !in_array($new_status, $allowed_statuses)) {
        $errors[] = "Invalid status selected.";
    }

    if ($target_cust_id && $target_timestamp) {
        $check_stmt = $conn->prepare("
            SELECT DEL_STATUS 
            FROM DELIVERY 
            WHERE CUST_ID = ? AND DEL_TIMESTAMP = ? 
            LIMIT 1
        ");
        $check_stmt->bind_param("is", $target_cust_id, $target_timestamp);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $current_data = $check_result->fetch_assoc();
        $check_stmt->close();

        if ($current_data) {
            $current_status = $current_data['DEL_STATUS'];

            if ($current_status === 'Delivered' || $current_status === 'Cancelled') {
                $errors[] = "Error: This order is already '$current_status' and cannot be changed.";
            }
        }
    }


    if(!empty($errors)){
        foreach($errors as $error){
            echo "<p style='color:red;'><b>" . htmlspecialchars($error) ."</b></p>";
        }
    } else { 

        if ($target_cust_id && $target_timestamp) {
            $stmt = $conn->prepare("
                UPDATE DELIVERY 
                SET DEL_STATUS = ?
                WHERE CUST_ID = ? AND DEL_TIMESTAMP = ?
            ");
            $stmt->bind_param("sis", $new_status, $target_cust_id, $target_timestamp);
            
            if ($stmt->execute()) {
                header("Location: " . $_SERVER['PHP_SELF']); 
                exit();
            } else {
                echo "<p style='color:red;'>Error Updating: " . htmlspecialchars($stmt->error) . "</p>";
            }
            $stmt->close();
        }
    }
}

$courier_id = 1; 

$display = $conn->prepare("
    SELECT
        C.CUST_ID, 
        C.CUST_NAME, 
        C.CUST_PHONE_NUMBER, 
        C.CUST_LOCATION, 
        D.DEL_STATUS, 
        D.DEL_TIMESTAMP,
        /* CALCULATE QUANTITY HERE */
        GROUP_CONCAT(
            CONCAT(P.PRD_NAME, ' (x', ROUND(D.PAY_AMOUNT / P.PRD_PRICE), ')') 
            SEPARATOR '\n'
        ) as ITEMS_BOUGHT
    FROM
        DELIVERY AS D
    JOIN
        CUSTOMER AS C ON D.CUST_ID = C.CUST_ID
    JOIN
        PRODUCT AS P ON D.PRD_ID = P.PRD_ID
    WHERE
        D.CRR_ID = ?
    GROUP BY 
        D.DEL_TIMESTAMP, 
        C.CUST_ID, 
        C.CUST_NAME, 
        C.CUST_PHONE_NUMBER, 
        C.CUST_LOCATION, 
        D.DEL_STATUS
    ORDER BY 
        D.DEL_TIMESTAMP DESC
");

$display->bind_param("i", $courier_id);
$display->execute();
$display_result = $display->get_result();
$display->close();
?>

<html>
    <head>
        <title>Courier Menu</title>
        <style>
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; vertical-align: top; }
            .locked { color: gray; font-style: italic; }
            .status-done { color: green; font-weight: bold; }
            .status-cancel { color: red; font-weight: bold; }
        </style>
    </head>
    <body>

        <h1>Courier Menu (Courier ID: <?php echo $courier_id; ?>)</h1>

        <h2>Deliveries Assigned</h2>
        <table border="1" cellpadding="4" cellspacing="0">
        <tr>
            <th>Date/Time</th>
            <th>Customer</th>
            <th>Contact</th>
            <th>Location</th>
            <th>Order Items</th>
            <th>Current Status</th>
            <th>Update Action</th>
        </tr>
            <?php while ($row = $display_result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo date("M d, h:i A", strtotime($row['DEL_TIMESTAMP'])); ?></td>
                
                <td>
                    <?php echo htmlspecialchars($row['CUST_NAME']); ?><br>
                    <small>(ID: <?php echo $row['CUST_ID']; ?>)</small>
                </td>
                
                <td><?php echo htmlspecialchars($row['CUST_PHONE_NUMBER']); ?></td>
                <td><?php echo htmlspecialchars($row['CUST_LOCATION']); ?></td>
                
                <td><?php echo nl2br(htmlspecialchars($row['ITEMS_BOUGHT'])); ?></td>

                <td>
                    <strong><?php echo htmlspecialchars($row['DEL_STATUS']); ?></strong>
                </td>

                <td>
                    <?php 
                    if ($row['DEL_STATUS'] == 'Delivered' || $row['DEL_STATUS'] == 'Cancelled'): 
                    ?>
                        <span class="locked">
                            <?php if($row['DEL_STATUS'] == 'Delivered') echo "<span class='status-done'>Order Complete</span>"; ?>
                            <?php if($row['DEL_STATUS'] == 'Cancelled') echo "<span class='status-cancel'>Order Cancelled</span>"; ?>
                        </span>
                    <?php else: ?>
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <input type="hidden" name="cust_id" value="<?php echo htmlspecialchars($row['CUST_ID']); ?>"> 
                            <input type="hidden" name="timestamp" value="<?php echo htmlspecialchars($row['DEL_TIMESTAMP']); ?>"> 

                            <select name="DEL_STATUS">
                                <option value="Pending" <?php if ($row['DEL_STATUS'] == 'Pending') echo 'selected'; ?>>Pending</option>
                                <option value="In-Transit" <?php if ($row['DEL_STATUS'] == 'In-Transit') echo 'selected'; ?>>In-Transit</option>
                                <option value="Delivered">Delivered</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                            <br><br>
                            <button type="submit" name="Update" style="color:blue; cursor:pointer;">Update Status</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php } ?>
        </table>
        <br>
    <p><a href="courier_function.php"><button>Back</button></a></p>
    </body>
</html>