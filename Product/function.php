<?php
require_once __DIR__ . '/../database/connect.php';

// Initialize variables
$ven_id = $_POST['ven_id'] ?? $_GET['ven_id'] ?? null;
$message = "";       // For success messages
$messageType = "";   // 'success' or 'error'
$errors = [];

// ---------------------------------------------------------
// 1. LOGIC: ADD PRODUCT
// ---------------------------------------------------------
if (isset($_POST['add_product'])) {
    $ven_id = (int)($_POST['ven_id'] ?? $_GET['ven_id'] ?? 0);
    $prod_name = trim($_POST['prod_name'] ?? '');
    $price = $_POST['price'] ?? '';
    $prd_avail = isset($_POST['prd_avail']) ? (int)$_POST['prd_avail'] : null;
    $quantity = $_POST['quantity'] ?? '';

    // Validation
    if ($prod_name == '') {
        $errors[] = "Please Input Product Name";
    } elseif (strlen($prod_name) < 3) {
        $errors[] = "Product name must have at least 3 characters";
    } elseif (!preg_match('/^[a-zA-Z0\s,]+$/', $prod_name)) {
        $errors[] = "Product name must contain strings only";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT PRD_ID FROM PRODUCT WHERE PRD_NAME = ?");
        $stmt->bind_param("s", $prod_name);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Product Name Already Exists";
        }
        $stmt->close();
    }

    if ($price === '' || !is_numeric($price)) {
        $errors[] = "Product price must be numeric";
    }

    if ($prd_avail === null || !in_array($prd_avail, [0, 1])) {
        $errors[] = "Please select product status";
    }

    if ($prd_avail == 1) {
        if (empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
            $errors[] = "Please ensure the availability and the quantity match";
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO PRODUCT(VEN_ID, PRD_NAME, PRD_PRICE, PRD_AVAILABILITY, PRD_QUANTITY) VALUES(?, ?, ?, ?, ?)");
        $stmt->bind_param("isdii", $ven_id, $prod_name, $price, $prd_avail, $quantity);
        
        if ($stmt->execute()) {
            $message = "Product added successfully!";
            $messageType = "success";
        } else {
            $errors[] = "Error Adding Data: " . $conn->error;
        }
        $stmt->close();
    }
}

// ---------------------------------------------------------
// 2. LOGIC: UPDATE PRODUCT
// ---------------------------------------------------------
if (isset($_POST['Update'])) {
    $code = $_POST['prdcode'] ?? null;
    $ven_id = $_POST['ven_id'] ?? $ven_id; 

    if ($code) {
        $stmt_check = $conn->prepare("SELECT * FROM PRODUCT WHERE PRD_ID = ?");
        $stmt_check->bind_param("i", $code);
        $stmt_check->execute();
        $row = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($row) {
            $price = ($_POST['price'] !== "") ? $_POST['price'] : $row['PRD_PRICE'];
            
            $current_avail = ($row['PRD_AVAILABILITY'] == 1 || $row['PRD_AVAILABILITY'] == 'YES') ? 1 : 0;
            $prd_avail = isset($_POST['prd_avail']) ? (int)$_POST['prd_avail'] : $current_avail;
            
            $quantity = ($_POST['quantity'] !== "") ? $_POST['quantity'] : $row['PRD_QUANTITY'];

            if ($price <= 0) $errors[] = "Price must be greater than 0";

            if ($prd_avail == 1 && $quantity <= 0) {
                $errors[] = "Quantity must be greater than 0 when product is available";
            } elseif ($prd_avail == 0) {
                $quantity = 0; 
            }

            if (empty($errors)) {
                $stmt = $conn->prepare("UPDATE PRODUCT SET PRD_PRICE = ?, PRD_AVAILABILITY = ?, PRD_QUANTITY = ? WHERE PRD_ID = ?");
                $stmt->bind_param("diii", $price, $prd_avail, $quantity, $code);
                
                if ($stmt->execute()) {
                    $message = "Product updated successfully!";
                    $messageType = "success";
                } else {
                    $message = "Error updating database.";
                    $messageType = "error";
                }
                $stmt->close();
            }
        }
    }
}

// ---------------------------------------------------------
// 3. LOGIC: DELETE PRODUCT
// ---------------------------------------------------------
if (isset($_POST['Delete'])) {
    $Code = $_POST['prdcode'] ?? null;
    if ($Code) {
        $sim = $conn->prepare("DELETE FROM PRODUCT WHERE PRD_ID = ?");
        $sim->bind_param("i", $Code);
        if($sim->execute()) {
            $message = "Product deleted successfully!";
            $messageType = "success";
        } else {
            $message = "Error deleting product.";
            $messageType = "error";
        }
        $sim->close();
    }
}

// Fetch Data
$execute = mysqli_query($conn, "SELECT PRD_ID, VEN_ID, PRD_NAME, PRD_PRICE, CASE WHEN PRD_AVAILABILITY = 1 THEN 'YES' ELSE 'NO' END AS PRD_AVAILABILITY, PRD_QUANTITY FROM PRODUCT");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Products Table</title>
    <style>
        /* General Styles */
        body { font-family: sans-serif; padding: 20px; }
        
        /* Table Styles */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; vertical-align: middle; }
        th { background-color: #f2f2f2; text-align: left; }
        
        /* Message Banner Styles */
        .msg-banner { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Form Row Layout - The "Flex" Magic */
        .form-row {
            display: flex;
            align-items: center;
            gap: 15px; /* Space between elements */
            flex-wrap: wrap; /* Allows wrapping on small screens */
        }
        
        /* Input Styling */
        .input-group { display: flex; align-items: center; gap: 5px; }
        .input-small { width: 70px; padding: 5px; }
        .btn { padding: 5px 10px; cursor: pointer; border: none; border-radius: 3px; color: white; }
        .btn-update { background-color: #007bff; }
        .btn-delete { background-color: #dc3545; }
        .radio-group { display: flex; gap: 10px; font-size: 0.9em; }
    </style>
</head>
<body>
    
    <h3>Product Management</h3>

    <?php if (!empty($message)): ?>
        <div class="msg-banner <?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="msg-banner error">
            <strong>Please fix the following errors:</strong><br>
            <?php foreach ($errors as $error) echo "- " . $error . "<br>"; ?>
        </div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">ID</th>
                <th style="width: 15%;">Name</th>
                <th style="width: 10%;">Current Price</th>
                <th style="width: 10%;">Current Status</th>
                <th style="width: 10%;">Current Qty</th>
                <th>Edit Product</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = mysqli_fetch_array($execute)) { ?>
        <tr>
            <td><?php echo htmlspecialchars($row['PRD_ID']); ?></td>
            <td><?php echo htmlspecialchars($row['PRD_NAME']); ?></td>
            <td>$<?php echo htmlspecialchars($row['PRD_PRICE']); ?></td>
            <td>
                <span style="color: <?php echo ($row['PRD_AVAILABILITY']=='YES')?'green':'red'; ?>">
                    <?php echo htmlspecialchars($row['PRD_AVAILABILITY']); ?>
                </span>
            </td>
            <td><?php echo htmlspecialchars($row['PRD_QUANTITY']); ?></td>

            <td>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <input type="hidden" name="prdcode" value="<?php echo $row['PRD_ID'] ?>">
                    <input type="hidden" name="ven_id" value="<?php echo $ven_id; ?>">

                    <div class="form-row">
                        
                        <div class="input-group">
                            <label>Price:</label>
                            <input type="number" name="price" step="0.01" class="input-small"
                                   value="<?php echo htmlspecialchars($row['PRD_PRICE']) ?>" required>
                        </div>

                        <div class="radio-group">
                            <label>
                                <input type="radio" name="prd_avail" value="1"
                                <?php echo ($row['PRD_AVAILABILITY'] == 'YES') ? 'checked' : ''; ?>> Avail
                            </label>
                            <label>
                                <input type="radio" name="prd_avail" value="0"
                                <?php echo ($row['PRD_AVAILABILITY'] == 'NO') ? 'checked' : ''; ?>> Unavail
                            </label>
                        </div>

                        <div class="input-group">
                            <label>Qty:</label>
                            <input type="number" name="quantity" min="0" class="input-small"
                                   value="<?php echo htmlspecialchars($row['PRD_QUANTITY']) ?>" required>
                        </div>

                        <div style="margin-left: auto; display: flex; gap: 5px;">
                            <button type="submit" name="Update" class="btn btn-update">Update</button>
                            <button type="submit" name="Delete" class="btn btn-delete" onclick="return confirm('Delete this item?');">X</button>
                        </div>

                    </div>
                </form>
            </td>
        </tr>
        <?php } ?>
        </tbody>
    </table>

    <br>
    <p>
        <a href="service.php?ven_id=<?php echo $ven_id; ?>">
            <button type="button" style="padding: 10px;">Back to Service</button>
        </a>
    </p>

</body>
</html>