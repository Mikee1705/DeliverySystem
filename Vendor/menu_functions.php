<?php
$servername = "localhost:3310";
$username = "root";
$password = "slippinjimmy23!";

$database = "DeliverySystem";

$conn = new mysqli(
    $servername, 
    $username, 
    $password, 
    $database);


if(isset($_POST["add_vendor"])){
    
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

    if($location === ''){
        $errors[] = "Location is Required";
    } elseif(strlen($location) < 3){
        $errors[] = "Location Must be at least 3 Characters Long";
    }  elseif (!preg_match('/^[a-zA-Z\s]+$/', $location)) {
    $errors[] = "Location must contain letters only";
    }
    if(!empty($errors)){
        foreach($errors as $error){
            echo $error ."<br>";
        }

    }else{ 
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
?>