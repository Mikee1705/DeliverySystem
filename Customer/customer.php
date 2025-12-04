<?php
require_once __DIR__ . '/../database/connect.php';
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$current_cust_id = 1; 
$commission_rate = 0.10; 
$courier_id = 1;

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $prd_id = (int)$_POST['prd_id'];
    $ven_id = (int)$_POST['ven_id'];

    // Security: Check current DB stock first
    $stmt = $conn->prepare("SELECT PRD_NAME, PRD_PRICE, PRD_QUANTITY FROM PRODUCT WHERE PRD_ID = ? AND PRD_AVAILABILITY = 1");
    $stmt->bind_param("i", $prd_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($product && $product['PRD_QUANTITY'] > 0) {
        $found = false;
        
        // Check if item already exists in cart to increment quantity
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['prd_id'] == $prd_id) {
                if (($item['quantity'] + 1) <= $product['PRD_QUANTITY']) {
                    $item['quantity']++;
                } else {
                    echo "<script>alert('Cannot add more. Stock limit reached.');</script>";
                }
                $found = true;
                break;
            }
        }
        unset($item); // Break reference

        // If new item, add to array
        if (!$found) {
            $_SESSION['cart'][] = [
                'prd_id'     => $prd_id,
                'name'       => $product['PRD_NAME'],
                'unit_price' => (float)$product['PRD_PRICE'], // Keep unit price, not total
                'quantity'   => 1,
                'ven_id'     => $ven_id
            ];
        }
    } else {
        echo "<script>alert('Item is out of stock.');</script>";
    }
    

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    $index = $_POST['index_to_remove'];
    if (isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); 
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    if (!empty($_SESSION['cart'])) {

        $tip_total = (float)$_POST['tip_amount'];
        $method = $_POST['pay_method'];
        
        $cart_count = count($_SESSION['cart']);
        $tip_per_item = $cart_count > 0 ? ($tip_total / $cart_count) : 0;

        $conn->begin_transaction();

        try {

            $stmtInsert = $conn->prepare("
                INSERT INTO DELIVERY 
                (CUST_ID, CRR_ID, PRD_ID, DEL_STATUS, PAY_METHOD, PAY_AMOUNT, PAY_TIP, PAY_COMMISSION) 
                VALUES (?, ?, ?, 'Pending', ?, ?, ?, ?)
            ");


            $stmtUpdate = $conn->prepare("
                UPDATE PRODUCT 
                SET PRD_QUANTITY = PRD_QUANTITY - ?,
                    PRD_AVAILABILITY = CASE WHEN (PRD_QUANTITY - ?) <= 0 THEN 0 ELSE 1 END
                WHERE PRD_ID = ? AND PRD_QUANTITY >= ?
            ");

            foreach ($_SESSION['cart'] as $item) {
                $total_item_price = $item['unit_price'] * $item['quantity'];
                $commission = $total_item_price * $commission_rate;

                $stmtUpdate->bind_param("iiii", 
                    $item['quantity'],
                    $item['quantity'], 
                    $item['prd_id'], 
                    $item['quantity']  
                );
                $stmtUpdate->execute();

                if ($stmtUpdate->affected_rows === 0) {
                    throw new Exception("Stock changed or insufficient for: " . $item['name']);
                }

                $stmtInsert->bind_param("iiisddd",
                    $current_cust_id,
                    $courier_id,
                    $item['prd_id'],
                    $method,
                    $total_item_price,
                    $tip_per_item,
                    $commission
                );
                $stmtInsert->execute();
            }

            $stmtInsert->close();
            $stmtUpdate->close();
            $conn->commit(); // Save changes

            $_SESSION['cart'] = [];
            header("Location: /DeliverySystem/delivery/delivery.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback(); // Undo changes if error
            echo "<script>alert('Order Failed: " . $e->getMessage() . "');</script>";
        }
    }
}

$view_mode = 'VENDOR_LIST';
$vendor_data = null;

if (isset($_GET['ven_id'])) {
    $view_mode = 'PRODUCT_LIST';
    $ven_id = (int)$_GET['ven_id'];

    $stmt = $conn->prepare("SELECT * FROM VENDOR WHERE VEN_ID = ?");
    $stmt->bind_param("i", $ven_id);
    $stmt->execute();
    $vendor_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM PRODUCT WHERE VEN_ID = ? AND PRD_AVAILABILITY = 1 AND PRD_QUANTITY > 0");
    $stmt->bind_param("i", $ven_id);
    $stmt->execute();
    $products_result = $stmt->get_result();
    $stmt->close();

} else {
    $vendors_result = $conn->query("SELECT * FROM VENDOR");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Ordering</title>
</head>
<body>

    <h2>Welcome, Customer #<?php echo $current_cust_id; ?></h2>

    <div>
        <h3>Check your Orders</h3>
        <p><a href="/DeliverySystem/delivery/delivery.php">Go to Orders Page</a></p>
        <hr>
    </div>

    <div>
        <?php if ($view_mode == 'VENDOR_LIST'): ?>
            <h3>Select a Restaurant</h3>
            <table border="1" cellpadding="5">
                <tr>
                    <th>Restaurant</th>
                    <th>Location</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $vendors_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['VEN_NAME']); ?></td>
                    <td><?php echo htmlspecialchars($row['VEN_LOCATION']); ?></td>
                    <td>
                        <form method="GET">
                            <input type="hidden" name="ven_id" value="<?php echo $row['VEN_ID']; ?>">
                            <button type="submit">View Menu</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>

        <?php elseif ($view_mode == 'PRODUCT_LIST'): ?>
            <h3>Menu: <?php echo htmlspecialchars($vendor_data['VEN_NAME']); ?></h3>
            <p><a href="<?php echo strtok($_SERVER["REQUEST_URI"], '?'); ?>">Start Over (Back to Vendors)</a></p>

            <table border="1" cellpadding="5">
                <tr>
                    <th>Item</th>
                    <th>Price</th>
                    <th>Stock Left</th>
                    <th>Action</th>
                </tr>
                <?php while ($prod = $products_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($prod['PRD_NAME']); ?></td>
                    <td>$<?php echo number_format($prod['PRD_PRICE'], 2); ?></td>
                    <td><?php echo $prod['PRD_QUANTITY']; ?></td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="ven_id" value="<?php echo $ven_id; ?>">
                            <input type="hidden" name="prd_id" value="<?php echo $prod['PRD_ID']; ?>">
                            <button type="submit" name="add_to_cart">Add to Cart</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        <?php endif; ?>
    </div>

    <hr>

    <div>
        <h3>Your Cart</h3>
        <?php if (empty($_SESSION['cart'])): ?>
            <p>Your cart is empty.</p>
        <?php else: ?>
            <ul>
                <?php 
                $grand_total = 0;
                foreach ($_SESSION['cart'] as $index => $item): 
                    $line_total = $item['unit_price'] * $item['quantity'];
                    $grand_total += $line_total;
                ?>
                <li>
                    <?php echo htmlspecialchars($item['name']); ?> 
                    (x<?php echo $item['quantity']; ?>) 
                    - $<?php echo number_format($line_total, 2); ?>

                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="index_to_remove" value="<?php echo $index; ?>">
                        <button type="submit" name="remove_item">[X]</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>

            <strong>Total: $<?php echo number_format($grand_total, 2); ?></strong>

            <br><br>

            <form method="POST">
                <label>Tip Amount:</label><br>
                <input type="number" name="tip_amount" step="0.01" value="0.00"><br><br>

                <label>Payment Method:</label><br>
                <select name="pay_method">
                    <option value="Cash">Cash</option>
                    <option value="Credit">Credit Card</option>
                    <option value="Debit">Debit Card</option>
                    <option value="E-Wallet">E-Wallet</option>
                </select>
                <br><br>

                <button type="submit" name="confirm_order">CONFIRM ORDER</button>
            </form>

        <?php endif; ?>
    </div>

</body>
</html>