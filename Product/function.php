<?php
require_once __DIR__ . '/../database/connect.php';
$ven_id = $_POST['ven_id'] ?? $_GET['ven_id'] ?? null;

if(isset($_POST['add_product'])) {

    $ven_id = (int) ($_POST['ven_id']?? $_GET['ven_id']?? null);
    $prod_name = $_POST['prod_name']?? '';
    $price = (float)($_POST['price']??'');
    $prd_avail = isset($_POST['prd_avail']) ? (int)$_POST['prd_avail'] : 0;
    $quantity = $_POST['quantity']??'';


    $errors = [];
    
    if($prod_name == ''){
        $errors [] = "Please Input product Name";
    }elseif(strlen($prod_name)< 3){
        $errors [] = "Product name must have atleast 3 characters";
    }elseif(!preg_match('/^[a-zA-Z0\s,]+$/', $prod_name)){
        $errors [] = "Product name must contain strings only";
    }
    if(empty($errors)){
        $currentID = ['prdcode'] ?? null;
        if($currentID){
            $stmt = $conn->prepare("SELECT PRD_ID FROM PRODUCT WHERE
            PRD_NAME = ? AND VEN_ID != ?");
            $stmt->bind_param("si",$prod_name,$currentID);
        }else{
            $stmt = $conn -> prepare("SELECT PRD_ID FROM PRODUCT 
            WHERE PRD_NAME = ?");
            $stmt->bind_param("s", $prod_name);
        }
        $stmt->execute();
        $stmt->store_result();
        if($stmt->num_rows > 0){
            $errors[] = "Product Name Already Exists";
        }
        $stmt->close();
    }
    if($price == ''){
        $errors [] = "Please Input product price";
    }elseif(!is_numeric($price)){
        $errors [] = "Product price must be numeric";
    }
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])){
        $errors = [];
        if(!isset($_POST['prd_avail'])){
            $errors[] = "Please select product status";
        }else{
            $prd_avail = $_POST['prd_avail'];
            if(!in_array($prd_avail, ['0','1'])){
                $errors[] = "Invalid Product Input";
            }
        }
        if(isset($_POST['prd_avail']) && $_POST['prd_avail']){
            if(empty($_POST['quantity']) || !is_numeric($_POST['quantity']) 
                || $_POST['quantity'] <= 0){
                    $errors[] = "Please Ensure the availability and the quantity match";

            }
        }
    }
}
    if(!empty($errors)){
        foreach($errors as $error){
            echo $error ."<br>";
        }
        return;
    }else {
        if(isset($_POST['Update'])){
            $code = $_POST['prdcode'] ?? null;
             $result = $conn->query("SELECT * FROM PRODUCT WHERE PRD_ID = '$code'");
             $row = $result->fetch_assoc();
            if($code != null){
               $prd_avail = $_POST['prd_avail'] ?? $row['PRD_AVAILABILITY'];

            // FIX: Keep old quantity if not entered
               $quantity = $_POST['quantity'] !== "" ? $_POST['quantity'] : $row['PRD_QUANTITY'];

            // Price defaults to old if not entered
                $price = $_POST['price'] !== "" ? $_POST['price'] : $row['PRD_PRICE'];

                // Validate price
                if($price <= 0) {
                    $errors[] = "Price must be greater than 0";
                    }

                // Validate quantity based on availability
                if($prd_avail == 1) { // If product is available
                    if($quantity <= 0) {
                        $errors[] = "Quantity must be greater than 0 when product is available";
                        }
                    } else { // If product is not available
                        $quantity = 0; // Force quantity to 0 if not available
                        }

                    if(!empty($errors)) {
                            foreach($errors as $error) {
                                echo $error . "<br>";
                            }
                             return;
                    }

        
                $stmt = $conn ->prepare("UPDATE PRODUCT
                    SET PRD_PRICE = ?,
                    PRD_AVAILABILITY = ?,
                    PRD_QUANTITY = ?
                    WHERE PRD_ID = ?");
                $stmt ->bind_param("iiii", 
                $price, $prd_avail, $quantity, $code);
                if($stmt -> execute()){
                    echo "Data Updated Sucessfully";
                }else{
                    echo "Error Updating Data";
                }
                $stmt->close();
            }
            
        
        }elseif(isset($_POST['add_product'])){

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
        }
    }
if (isset($_POST['Delete'])) {
    $Code = $_POST['prdcode'] ?? null;
    if ($Code) {
        $sim = $conn->prepare("DELETE FROM PRODUCT WHERE PRD_ID = ?");
        $sim->bind_param("i", $Code);
        $sim->execute();
        $sim->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

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
        </tr>
            <?php while ($row = mysqli_fetch_array($execute)){ ?>
            <tr>
             <td><?php echo htmlspecialchars($row['PRD_ID']); ?></td>
             <td><?php echo htmlspecialchars($row['VEN_ID']); ?></td>
             <td><?php echo htmlspecialchars($row['PRD_NAME']); ?></td>
             <td><?php echo htmlspecialchars($row['PRD_PRICE']); ?></td>
             <td><?php echo htmlspecialchars($row['PRD_AVAILABILITY']); ?></td>
             <td><?php echo htmlspecialchars($row['PRD_QUANTITY']); ?></td>
             <td>
                <form method = "POST" action = "function.php">
                <input type = "hidden" name = "prdcode" value = "<?php echo $row['PRD_ID']?>">
                <button type="submit" name="Delete" style="color:red;">X</button>

                <input type = "number" name = "price" value ="<?php echo htmlspecialchars($row['PRD_PRICE'])?>" required>

                <input type="radio" id="yes_<?php echo $row['PRD_ID']?>" 
                name="prd_avail" value="1"
                <?php ($row['PRD_AVAILABILITY'] == 'YES') ? 'checked' : '' ?>>
                <label for="yes_<?php echo $row['PRD_ID']?>">Available</label>

                <input type="radio" id="no_<?php echo $row['PRD_ID']?>" 
                name="prd_avail" value="0"
                <?php ($row['PRD_AVAILABILITY'] == 'NO') ? 'checked' : '' ?>>
                <label for="no_<?php echo $row['PRD_ID']?>">Unavailable</label>


                <input type = "number" name = "quantity" min = "0"
                value ="<?php echo htmlspecialchars($row['PRD_QUANTITY'])?>" required>
                <button type="submit" name="Update" style="color:blue;">Update</button>
                <input type="hidden" name="ven_id" value="<?php echo $ven_id; ?>">
                </form>
             </td>
            </tr>
          <?php } ?>
        </table>
        <p>
        <a href="service.php?ven_id=<?php echo $ven_id; ?>">
        <button type = "button">Back</button>
    </a>
</p>
    </body>
</html>