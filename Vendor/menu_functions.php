

<?php
require_once __DIR__ . '/../database/connect.php';

if(isset($_POST["add_vendor"]) || isset($_POST["Update"])){
    
    $contact = $_POST["number"]?? '';
    $location = $_POST["location"]?? '';
    $rating = $_POST["rating"]?? '';

    $errors = [];

    if($contact === ''){
        $errors[] = "Phone number is Required";
    }elseif(!ctype_digit($contact)){
        $errors[] = "Phone number must be numeric";
    }elseif(strlen($contact) != 11){
        $errors[] = "Phone Number must be Exactly 11 digits";
    }
    //checks for duplicate phone numbers
    if(empty($errors)){
        $currentID = $_POST['vencode'] ?? null;
        if($currentID){
            $stmt = $conn -> prepare("SELECT VEN_ID FROM VENDOR 
            WHERE VEN_PHONE_NUMBER = ? AND VEN_ID <> ?");
            $stmt->bind_param("si", $contact, $currentID);
        }else{
            $stmt = $conn -> prepare("SELECT VEN_ID FROM VENDOR 
            WHERE VEN_PHONE_NUMBER = ?");
            $stmt->bind_param("s", $contact);
            
        } 
        $stmt->execute();
        $stmt->store_result();
        if($stmt->num_rows > 0){
            $errors[] = "Phone Number Already Exists";
        }
        $stmt->close();
    }
    if($location === ''){
        $errors[] = "Location is Required";
    } elseif(strlen($location) < 3){
        $errors[] = "Location Must be at least 3 Characters Long";
    }  elseif (!preg_match('/^[a-zA-Z\s,]+$/', $location)) {
        $errors[] = "Location must contain letters and commas only";
    }
    if(!empty($errors)){
        foreach($errors as $error){
            echo $error ."<br>";
        }

    }else{ 
        if (isset($_POST['Update'])) {
        // UPDATE path
        $Code = $_POST['vencode'] ?? null;
        if ($Code != null) {
            $conn->query(
                "UPDATE VENDOR 
                 SET VEN_PHONE_NUMBER = '$contact',
                     VEN_LOCATION = '$location'
                 WHERE VEN_ID = '$Code'"
            );
            echo "Data Updated Sucessfully";
        }
    } elseif (isset($_POST["add_vendor"])) {
    if($rating ===''){
         $sim =  $conn->prepare("INSERT INTO vendor (VEN_PHONE_NUMBER, VEN_LOCATION) 
            VALUES (?,?)");
            $sim->bind_param("ss", $contact, $location);
    }else{
        $sim =  $conn->prepare("INSERT INTO vendor (VEN_PHONE_NUMBER, VEN_LOCATION, VEN_RATING) 
        VALUES (?,?,?)");

        $sim->bind_param("sss", $contact, $location, $rating);
            }
            $sim->execute();
            echo "Data Added Sucessfully";
        }
    }
}

if (isset($_POST['Delete'])) {
    $Code = $_POST['vencode'] ?? null;
    if ($Code) {
        $sim = $conn->prepare("DELETE FROM VENDOR WHERE VEN_ID = ?");
        $sim->bind_param("i", $Code);
        $sim->execute();
        $sim->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

$display = mysqli_query($conn,"SELECT * FROM VENDOR");
?>
<html>
    <head>
        <title>Vendors Table</title>
    </head>
    <body>
        <table border = "1" cellpadding = "4" cellspacing = "0">
        <tr>
            <th>Vendor ID</th>
            <th>Vendor Contact No.</th>
            <th>Vendor Address</th>
            <th>Vendor Rating</th>
        </tr>
            <?php while ($row = mysqli_fetch_array($display)) { ?>
            <tr>
                <td><?php echo htmlspecialchars($row['VEN_ID']); ?></td>
                <td><?php echo htmlspecialchars($row['VEN_PHONE_NUMBER']); ?></td>
                <td><?php echo htmlspecialchars($row['VEN_LOCATION']); ?></td>
                <td><?php echo htmlspecialchars($row['VEN_RATING']); ?></td>
                    <td>
                        <form method="POST" action="menu_functions.php">
                        <input type="hidden" name="vencode" value="<?php echo $row['VEN_ID']; ?>">
                        <button type="submit" name="Delete" style="color:red;">X</button>
                    </form>
                </td>
            <td>
                <form method="POST" action="menu_functions.php">
                <input type="hidden" name="vencode" value="<?php echo $row['VEN_ID']; ?>">

                <input type="text" name="number" value="<?php echo htmlspecialchars($row['VEN_PHONE_NUMBER']); ?>">

                <input type="text" name="location" value="<?php echo htmlspecialchars($row['VEN_LOCATION']); ?>">
                <button type="submit" name="Update" style="color:blue;">Update</button>
                </form>
            </td>
        </tr>
            <?php } ?>
        </table>
        <br>
    <p><a href = "user.html"><button>Back</button></a></p>
    </body>
</html>