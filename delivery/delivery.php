<?php
session_start();
require_once __DIR__ . '/../database/connect.php'; 

$current_cust_id = 1; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_time = $_POST['order_timestamp']; 

    $conn->begin_transaction();

    try {
        $get_products_sql = "
            SELECT D.PRD_ID, D.PAY_AMOUNT, P.PRD_PRICE
            FROM DELIVERY D
            JOIN PRODUCT P ON D.PRD_ID = P.PRD_ID
            WHERE D.DEL_TIMESTAMP = ? AND D.CUST_ID = ? AND D.DEL_STATUS = 'Pending'
        ";
        
        $stmt_get = $conn->prepare($get_products_sql);
        $stmt_get->bind_param("si", $order_time, $current_cust_id);
        $stmt_get->execute();
        $products = $stmt_get->get_result();
        $stmt_get->close();


        $stmt_cancel = $conn->prepare("UPDATE DELIVERY SET DEL_STATUS = 'Cancelled' WHERE DEL_TIMESTAMP = ? AND CUST_ID = ? AND DEL_STATUS = 'Pending'");
        $stmt_cancel->bind_param("si", $order_time, $current_cust_id);
        $stmt_cancel->execute();
        $stmt_cancel->close();


        $stmt_update = $conn->prepare("UPDATE PRODUCT SET PRD_QUANTITY = PRD_QUANTITY + ?, PRD_AVAILABILITY = 1 WHERE PRD_ID = ?");

        while ($row = $products->fetch_assoc()) {
            $qty_to_restore = 0;
            if ($row['PRD_PRICE'] > 0) {
                $qty_to_restore = round($row['PAY_AMOUNT'] / $row['PRD_PRICE']);
            }

            if ($qty_to_restore > 0) {
                $stmt_update->bind_param("ii", $qty_to_restore, $row['PRD_ID']);
                $stmt_update->execute();
            }
        }
        $stmt_update->close();

        $conn->commit();
        header("Location: " . $_SERVER['PHP_SELF']); 
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        die("Error cancelling order: " . $e->getMessage());
    }
}

$sql = "SELECT 
            /* CONCATENATE NAME + CALCULATED QUANTITY */
            /*Calculates the quantity of product by total_amount/price per item */
            GROUP_CONCAT(
                CONCAT(P.PRD_NAME, ' (x', ROUND(D.PAY_AMOUNT / P.PRD_PRICE), ')') 
                SEPARATOR '\n'
            ) as ITEMS_BOUGHT, 
            
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
    <style>
        body { font-family: sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
        th { background-color: #f2f2f2; }
    </style> 
</head>
<body>

    <h2>Order Tracking</h2>
    <p>Logged in as Customer #<?php echo $current_cust_id; ?></p>
    <p><a href="/DeliverySystem/Customer/customer.php">Back to Menu</a></p>

    <hr>

    <table>
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
                            <?php 
                                $statusColor = 'black';
                                if($row['DEL_STATUS'] == 'Pending') $statusColor = 'orange';
                                if($row['DEL_STATUS'] == 'Cancelled') $statusColor = 'red';
                                if($row['DEL_STATUS'] == 'Delivered') $statusColor = 'green';
                            ?>
                            <span style="color: <?php echo $statusColor; ?>; font-weight: bold;">
                                <?php echo htmlspecialchars($row['DEL_STATUS']); ?>
                            </span>
                        </td>

                        <td><?php echo $courier_info; ?></td>

                        <td>
                            <?php if ($row['DEL_STATUS'] === 'Pending'): ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this entire order?');">
                                    <input type="hidden" name="order_timestamp" value="<?php echo $row['DEL_TIMESTAMP']; ?>">
                                    <button type="submit" name="cancel_order" style="cursor: pointer; color: red;">Cancel Order</button>
                                </form>
                            <?php elseif ($row['DEL_STATUS'] === 'Cancelled'): ?>
                                <span style="color: grey;">Order Cancelled</span>
                            <?php else: ?>
                                <span style="color: grey;">Cannot Cancel</span>
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