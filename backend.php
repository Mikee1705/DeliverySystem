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
    $id = $_POST["id"];
    $contact = $_POST["number"];
    $location = $_POST["location"];
    $ratingvendor = $_POST["rating"];

    $conn->query("INSERT INTO vendor (VEN_ID, VEN_PHONE_NUMBER, VEN_LOCATION, VEN_RATING) 
    VALUES ('$id', '$contact', '$location', '$ratingvendor')");
    if(strlen($_POST["id"]) > 8) {
        echo "ID must be less than 8 characters";
        exit;
        if(!isset($_POST["id"])) {
            echo "ID is Missing";
            exit;
        }
         if(!isnumeric($_POST["id"])) {
            echo "ID must be a Number";
            exit;
        }
    }
    if(!is_numeric($_POST["number"])) {
        echo "Number must be a Number";
        exit;
        if(isset($_POST["number"]) > 11) {
            echo "Number must be less than 11 characters";
            exit;
        }
    }
    if(!isset($_POST["location"])) {
        echo "Location is Missing";
        exit;
    }
    if(!isset($_POST["rating"])) {
        echo "Rating is Missing";
        exit;
    }
}  


?>