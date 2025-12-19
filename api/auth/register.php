<?php
// api/auth/register.php

// 1. Include Config (Handles DB connection, CORS, and Headers)
require_once '../../config.php';

// Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Validate Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed. Use POST."]);
    exit;
}

// 3. Read Input
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (
    empty($data['name']) || 
    empty($data['email']) || 
    empty($data['password']) || 
    empty($data['role'])
) {
    http_response_code(400);
    echo json_encode(["message" => "Incomplete data. Please fill all fields."]);
    exit;
}

$name = trim($data['name']);
$email = trim($data['email']);
$role = trim($data['role']);
$password = $data['password'];

// 4. Validate Email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid email format."]);
    exit;
}

// 5. Validate Password Length
$MIN_PASSWORD_LENGTH = 6;
if (strlen($password) < $MIN_PASSWORD_LENGTH) {
    http_response_code(400);
    echo json_encode(["message" => "Password must be at least {$MIN_PASSWORD_LENGTH} characters long."]);
    exit;
}

try {
    // $pdo comes from config.php

    // 6. Check if email exists
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $checkStmt->execute([':email' => $email]);
    
    if ($checkStmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(["message" => "This email address is already registered."]);
        exit;
    }

    // 7. Hash Password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // 8. Insert User
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role) 
        VALUES (:name, :email, :pass, :role)
    ");
    
    $stmt->execute([
        ':name'  => $name,
        ':email' => $email,
        ':pass'  => $hashed_password,
        ':role'  => $role
    ]);

    $newId = $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        "message" => "User registered successfully.",
        "userId"  => $newId
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "message" => "Database error.",
        "error"   => $e->getMessage()
    ]);
}
?>