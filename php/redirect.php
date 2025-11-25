<?php 
    $servername = "localhost:3310";
    $username = "root";
    $password = "";
    $database = "Food_Delivery";

    $conn = new mysqli(
        $servername, 
        $username, 
        $password, 
        $database);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Check if form was submitted
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = $_POST["email"] ?? '';
        $password = $_POST["password"] ?? '';
        $account_type = $_POST["account_type"] ?? '';

        // Validate inputs
        if (empty($email) || empty($password) || empty($account_type)) {
            header("Location: /Core/index.html?error=missing_fields");
            exit();
        }

        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert into database (adjust table/column names as needed)
        $sql = "INSERT INTO users (email, password, account_type) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $email, $hashed_password, $account_type);

        if ($stmt->execute()) {
            // Redirect based on account type
            switch ($account_type) {
                case "Customer":
                    header("Location: /Customer/index.php");
                    break;
                case "Vendor":
                    header("Location: /Vendor/store.php");
                    break;
                case "Rider":
                    header("Location: /Rider/index.php");
                    break;
                default:
                    header("Location: /Core/index.html");
            }
            exit();
        } else {
            header("Location: /Core/index.html?error=account_creation_failed");
            exit();
        }

        $stmt->close();
    }

    $conn->close();
?>