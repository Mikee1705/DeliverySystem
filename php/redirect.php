<?php
session_start(); // Start native session

$servername = "localhost:3310";
$username = "root";
$password = "";
$database = "Food_Delivery";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"] ?? '';
    $password = $_POST["password"] ?? '';
    $account_type = $_POST["account_type"] ?? '';

    if (empty($email) || empty($password) || empty($account_type)) {
        header("Location: /Core/index.html?error=missing_fields");
        exit();
    }

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['account_type'] = $user['account_type'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            redirectUser($account_type);
        } else {
            header("Location: /Core/index.html?error=invalid_password");
            exit();
        }
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $user_id = generateUserID($account_type); // Function to generate IDs

        $sql = "INSERT INTO users (user_id, email, password, account_type) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $user_id, $email, $hashed_password, $account_type);

        if ($stmt->execute()) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $email;
            $_SESSION['account_type'] = $account_type;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            redirectUser($account_type);
        } else {
            header("Location: /Core/index.html?error=account_creation_failed");
            exit();
        }
    }
    $stmt->close();
}

$conn->close();

function redirectUser($account_type) {
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
}

function generateUserID($account_type) {
    $prefix = substr($account_type, 0, 3); // "Cus", "Ven", "Rid"
    $random = substr(bin2hex(random_bytes(3)), 0, 5);
    return strtoupper($prefix . $random);
}
?>