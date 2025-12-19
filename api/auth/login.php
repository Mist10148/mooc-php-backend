<?php
// api/auth/login.php

// 1. Include Config (Handles DB connection, CORS, and Headers)
// Path: Go up one level from 'auth' to 'api' to find config.php
require_once '../../config.php';

// Debugging (Optional: You can move this to config.php if you want it everywhere)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Read Input
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

$email = isset($data['email']) ? trim($data['email']) : '';
$password = isset($data['password']) ? trim($data['password']) : '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Email and password are required"]);
    exit;
}

try {
    // $pdo is already created in config.php

    // 3. Fetch User
    $stmt = $pdo->prepare("SELECT id, name, email, password, role, avatar_id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $userRow = $stmt->fetch();

    // 4. Verify Password
    if ($userRow && password_verify($password, $userRow['password'])) {
        
        // Remove hash before sending
        unset($userRow['password']);

        // 5. Send Response
        echo json_encode([
            "success" => true,
            "message" => "Login successful",
            "user"    => $userRow, 
            "token"   => bin2hex(random_bytes(16)) 
        ]);

    } else {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password"
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error",
        "error"   => $e->getMessage()
    ]);
}
?>