<?php
$servername = "localhost:3310";
$username = "root";
$password = "slippinjimmy23!";

$database = "Food_Delivery";

$conn = new mysqli(
    $servername, 
    $username, 
    $password, 
    $database);


if(isset($_POST["add_vendor"])){
    $id = $_POST["id"] ?? '';
    $contact = $_POST["number"]?? '';
    $location = $_POST["location"]?? '';
    $ratingvendor = $_POST["rating"]?? '';

    
    if(strlen($_POST["id"]) > 8) {
        echo "ID must be less than 8 characters";
    
        if(!isset($_POST["id"])) {
            echo "ID is Missing";
            
        }
         if(!is_numeric($_POST["id"])) {
            echo "ID must be a Number";
        
        }
    }
    $search = 0;
    $searchagain = 9;
    if(!is_numeric($_POST["number"])) {
        echo "Phone number must be a Number";
        
        if(!in_array($contact, $search) && !in_array($contact, $searchagain) && strlen($_POST["number"]) < 10) {
            echo "Invalid Phone Number";
            
        }
        if(strlen($_POST["number"]) > 10) {
            echo "Number must be less than 10 characters";
        
        }
    }
    if(strlen($_POST["location"]) < 3) {
            echo "Location must be more than 3 Characters";
            
        }
    if(!isset($_POST["location"])) {
        echo "Location is Missing";
        
     }
    if(!is_string($_POST["location"])) {
            echo "Location must be a String";
            
    }
    $conn->query("INSERT INTO vendor (VEN_ID, VEN_PHONE_NUMBER, VEN_LOCATION, VEN_RATING) 
    VALUES ('$id', '$contact', '$location', '$ratingvendor')");
}
?>