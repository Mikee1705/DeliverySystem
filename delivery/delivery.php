<?php
session_start();

require_once __DIR__ . '/../database/connect.php'; 

$current_cust_id = 1; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_time = $_POST['order_timestamp']; 

    $stmt_cancel = $conn->prepare("UPDATE DELIVERY SET DEL_STATUS = 'Cancelled' WHERE DEL_TIMESTAMP = ? AND CUST_ID = ? AND DEL_STATUS = 'Pending'");
    $stmt_cancel->bind_param("si", $order_time, $current_cust_id);
    
    if ($stmt_cancel->execute()) {
        header("Location: delivery.php"); 
        exit();
    }
}

//Logic: This query fetches all orders for the current customer, grouping them by order timestamp.
// This is done to avoid listing each product separately for the same order.
$sql = "SELECT 
            GROUP_CONCAT(P.PRD_NAME SEPARATOR '\n') as ITEMS_BOUGHT, 
            SUM(D.PAY_AMOUNT) as TOTAL_PRODUCT_PRICE,              
            MAX(D.PAY_TIP) as ORDER_TIP,                           
            D.DEL_STATUS,
            D.DEL_TIMESTAMP,
            D.CRR_ID,
            C.CRR_NAME,
            C.CRR_PHONE_NUMBER,
            C.CRR_VEHICLE
        FROM DELIVERY D
        JOIN PRODUCT P ON D.PRD_ID = P.PRD_ID
        LEFT JOIN COURIER C ON D.CRR_ID = C.CRR_ID
        WHERE D.CUST_ID = ?
        GROUP BY D.DEL_TIMESTAMP, D.DEL_STATUS, D.CRR_ID 
        ORDER BY D.DEL_TIMESTAMP DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_cust_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Orders</title>
    <meta http-equiv="refresh" content="30"> 
</head>
<body>

    <h2>Order Tracking</h2>
    <p>Logged in as Customer #<?php echo $current_cust_id; ?></p>
    <p><a href="/DeliverySystem/Customer/customer.php">Back to Menu</a></p>

    <hr>

    <table border="1" cellpadding="10" cellspacing="0">
        <thead>
            <tr>
                <th>Date Placed</th>
                <th>Items</th>
                <th>Payment Summary</th>
                <th>Status</th>
                <th>Courier Details</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    
                    <?php 
                        $grand_total = $row['TOTAL_PRODUCT_PRICE'] + $row['ORDER_TIP'];
                        
                        $courier_info = "Waiting for a Rider...";
                        if (!empty($row['CRR_ID'])) {
                            $courier_info = "<strong>" . htmlspecialchars($row['CRR_NAME']) . "</strong><br>" .
                                            "Phone: " . htmlspecialchars($row['CRR_PHONE_NUMBER']) . "<br>" .
                                            "Vehicle: " . htmlspecialchars($row['CRR_VEHICLE']);
                        }
                    ?>

                    <tr>
                        <td><?php echo date("M d, Y h:i A", strtotime($row['DEL_TIMESTAMP'])); ?></td>

                        <td><?php echo nl2br(htmlspecialchars($row['ITEMS_BOUGHT'])); ?></td>

                        <td>
                            Subtotal: $<?php echo number_format($row['TOTAL_PRODUCT_PRICE'], 2); ?><br>
                            Tip: $<?php echo number_format($row['ORDER_TIP'], 2); ?><br>
                            <strong>Total: $<?php echo number_format($grand_total, 2); ?></strong>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($row['DEL_STATUS']); ?>
                        </td>

                        <td><?php echo $courier_info; ?></td>

                        <td>
                            <?php if ($row['DEL_STATUS'] === 'Pending'): ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this entire order?');">
                                    <input type="hidden" name="order_timestamp" value="<?php echo $row['DEL_TIMESTAMP']; ?>">
                                    <button type="submit" name="cancel_order">Cancel Order</button>
                                </form>
                            <?php elseif ($row['DEL_STATUS'] === 'Cancelled'): ?>
                                Cancelled
                            <?php else: ?>
                                Cannot Cancel
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align:center;">No orders found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>