<?php
require_once __DIR__ . '/../database/connect.php';

if(isset($_POST['add_product'])) {

    $ven_id = (int) ($_POST['ven_id']?? 0);
    $prod_name = $_POST['prod_name']?? '';
    $price = (float)($_POST['price']??'');
    $prd_avail = isset($_POST['prd_avail']) ? (int)$_POST['prd_avail'] : 0;
    $quantity = $_POST['quantity']??'';

    var_dump($_POST['ven_id'], $ven_id);
    if(!$ven_id) {
        die("Vendor is Required");
    }
    $stmt = $conn->prepare("
    INSERT INTO PRODUCT(VEN_ID,PRD_NAME, PRD_PRICE, PRD_AVAILABILITY, PRD_QUANTITY)
    VALUES(?,?,?,?,?)");
    if (!$stmt) {
    die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("isdii", $ven_id, $prod_name, $price, $prd_avail, $quantity);
    $stmt->execute();
    $stmt->close();

    echo "Data Added Sucessfully";
    $execute = mysqli_query($conn,"SELECT
    PRD_ID,
    VEN_ID,
    PRD_NAME,
    PRD_PRICE,
    CASE 
        WHEN PRD_AVAILABILITY = 1 THEN 'YES'
        ELSE 'NO'
    END AS PRD_AVAILABILITY,
    PRD_QUANTITY
FROM PRODUCT;
    ");
}


?>
<html>
<head>
        <title>Products Table</title>
    </head>
    <body>
        <table border = "1" cellpadding = "4" cellspacing = "0">
        <tr>
            <th>Product ID</th>
            <th>Vendor ID</th>
            <th>Product Name</th>
            <th>Product Price</th>
            <th>Product Availability</th>
            <th>Product Quantity</th>
        <tr>
            <?php while ($row = mysqli_fetch_array($execute)){ ?>
        </tr>
             <td><?php echo htmlspecialchars($row['PRD_ID']); ?></td>
             <td><?php echo htmlspecialchars($row['VEN_ID']); ?></td>
             <td><?php echo htmlspecialchars($row['PRD_NAME']); ?></td>
             <td><?php echo htmlspecialchars($row['PRD_PRICE']); ?></td>
             <td><?php echo htmlspecialchars($row['PRD_AVAILABILITY']); ?></td>
             <td><?php echo htmlspecialchars($row['PRD_QUANTITY']); ?></td>
          <?php } ?>   
    </body>
</html>