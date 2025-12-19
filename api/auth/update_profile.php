<?php
// api/auth/update_profile.php

// 1. Include Config
// Path: Go up two levels (auth -> api -> root) to find config.php
require_once '../../config.php';

// --- Token Validation ---
function validateToken($pdo) {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        return false;
    }
    
    list($type, $token) = explode(' ', $headers['Authorization'], 2);

    if (strtolower($type) !== 'bearer' || empty($token)) {
        return false;
    }

    $user_id = filter_var($token, FILTER_VALIDATE_INT);
    
    if ($user_id !== false && $user_id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            return $user_id;
        }
    }
    
    return false;
}

// --- Main Logic ---

// 2. Authenticate
$user_id = validateToken($pdo);

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized: Invalid or missing token."]);
    exit();
}

// 3. Get Input
$data = json_decode(file_get_contents("php://input"));
$fullName = isset($data->fullName) ? trim($data->fullName) : null;
$avatarId = isset($data->avatarId) ? $data->avatarId : null; 

// 4. Build Query
$set_clauses = [];
$params = [];

if (!empty($fullName)) {
    $set_clauses[] = "name = ?";
    $params[] = $fullName;
}

if ($avatarId !== null) {
    $set_clauses[] = "avatar_id = ?";
    $params[] = $avatarId; 
} else {
    $set_clauses[] = "avatar_id = NULL";
}

if (empty($set_clauses)) {
    http_response_code(200);
    echo json_encode(["message" => "No fields to update."]);
    exit();
}

$sql = "UPDATE users SET " . implode(', ', $set_clauses) . " WHERE id = ?";
$params[] = $user_id;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // 5. Fetch Updated Data
    $stmt = $pdo->prepare("SELECT id, name, email, role, avatar_id, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $updatedUser = $stmt->fetch();
    
    if ($updatedUser) {
        http_response_code(200);
        echo json_encode([
            "message" => "Profile updated successfully.",
            "user" => [
                "id" => $updatedUser['id'],
                "email" => $updatedUser['email'],
                "fullName" => $updatedUser['name'], 
                "role" => $updatedUser['role'],
                "avatarId" => $updatedUser['avatar_id'],
                "createdAt" => $updatedUser['created_at']
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Update succeeded but failed to fetch user data."]);
    }

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Server error updating profile."]);
}
?>