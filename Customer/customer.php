<?php
session_start();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/../database/connect.php'; 


$current_cust_id = 1; 
$commission_rate = 0.10; 
$courier_id = 1;

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_cart'])) {
        $item = [
            'prd_id' => $_POST['prd_id'],
            'name' => $_POST['prd_name'],
            'price' => (float)$_POST['prd_price'],
            'ven_id' => $_POST['ven_id']
        ];
        
        $_SESSION['cart'][] = $item;
    }


    if (isset($_POST['remove_item'])) {
        $index = $_POST['index_to_remove'];
        if (isset($_SESSION['cart'][$index])) {
            unset($_SESSION['cart'][$index]);
            $_SESSION['cart'] = array_values($_SESSION['cart']); // reindex
        }
    }


    if (isset($_POST['confirm_order'])) {
        if (!empty($_SESSION['cart'])) {

            $tip = (float)$_POST['tip_amount'];
            $method = $_POST['pay_method'];

            
            $stmt = $conn->prepare("
                INSERT INTO DELIVERY 
                (CUST_ID, CRR_ID, PRD_ID, DEL_STATUS, PAY_METHOD, PAY_AMOUNT, PAY_TIP, PAY_COMMISSION) 
                VALUES (?, ?, ?, 'Pending', ?, ?, ?, ?)
            ");

            foreach ($_SESSION['cart'] as $item) {
                $commission = $item['price'] * $commission_rate;

                
                $stmt->bind_param("iiisddd",
                    $current_cust_id,
                    $courier_id,
                    $item['prd_id'],
                    $method,
                    $item['price'],
                    $tip,
                    $commission
                );


                if (!$stmt->execute()) {
                    die("SQL ERROR: " . $stmt->error);
                }
            }

            $stmt->close();


            $_SESSION['cart'] = [];
            header("Location: /DeliverySystem/delivery/delivery.php");
            exit();
        }
    }
}

$view_mode = 'VENDOR_LIST';
$vendor_data = null;

if (isset($_GET['ven_id'])) {
    $view_mode = 'PRODUCT_LIST';
    $ven_id = $_GET['ven_id'];


    $stmt = $conn->prepare("SELECT * FROM VENDOR WHERE VEN_ID = ?");
    $stmt->bind_param("i", $ven_id);
    $stmt->execute();
    $vendor_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();


    $stmt = $conn->prepare("SELECT * FROM PRODUCT WHERE VEN_ID = ? AND PRD_AVAILABILITY = 1");
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
            <p><a href="customer.html">Start Over (Back to Vendors)</a></p>

            <table border="1" cellpadding="5">
                <tr>
                    <th>Item</th>
                    <th>Price</th>
                    <th>Action</th>
                </tr>

                <?php while ($prod = $products_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($prod['PRD_NAME']); ?></td>
                    <td>$<?php echo number_format($prod['PRD_PRICE'], 2); ?></td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="ven_id" value="<?php echo $ven_id; ?>">
                            <input type="hidden" name="prd_id" value="<?php echo $prod['PRD_ID']; ?>">
                            <input type="hidden" name="prd_name" value="<?php echo $prod['PRD_NAME']; ?>">
                            <input type="hidden" name="prd_price" value="<?php echo $prod['PRD_PRICE']; ?>">
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
                $total = 0;
                foreach ($_SESSION['cart'] as $index => $item): 
                    $total += $item['price'];
                ?>
                <li>
                    <?php echo htmlspecialchars($item['name']); ?> - $<?php echo $item['price']; ?>

                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="index_to_remove" value="<?php echo $index; ?>">

                        <?php if (isset($_GET['ven_id'])): ?>
                            <input type="hidden" name="ven_id" value="<?php echo $_GET['ven_id']; ?>">
                        <?php endif; ?>

                        <button type="submit" name="remove_item">[X]</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>

            <strong>Total: $<?php echo number_format($total, 2); ?></strong>

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
