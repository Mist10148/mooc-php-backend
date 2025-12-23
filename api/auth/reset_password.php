<?php
/**
 * reset_password.php
 * Handles the actual password reset using the token from email
 * 
 * Place this in: /api/auth/reset_password.php
 */

// =============================================================================
// CORS Headers - Allow requests from your frontend
// =============================================================================
header("Access-Control-Allow-Origin: https://mooc-frontend-myqa.onrender.com");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit();
}

// =============================================================================
// Configuration
// =============================================================================
require_once __DIR__ . '/db_config.php'; // Your existing database config

// Password requirements
$MIN_PASSWORD_LENGTH = 6;

// =============================================================================
// Main Logic
// =============================================================================

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $token = isset($data['token']) ? trim($data['token']) : '';
    $newPassword = isset($data['newPassword']) ? $data['newPassword'] : '';

    // Validate inputs
    if (empty($token) || empty($newPassword)) {
        http_response_code(400);
        echo json_encode(["message" => "Token and new password are required."]);
        exit();
    }

    // Validate password length
    if (strlen($newPassword) < $MIN_PASSWORD_LENGTH) {
        http_response_code(400);
        echo json_encode(["message" => "Password must be at least {$MIN_PASSWORD_LENGTH} characters long."]);
        exit();
    }

    // Connect to database
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        http_response_code(500);
        echo json_encode(["message" => "Database connection failed"]);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Check if token exists and is not expired
        $stmt = $conn->prepare("SELECT user_id FROM password_reset_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            http_response_code(401);
            echo json_encode(["message" => "Invalid or expired password reset link."]);
            $stmt->close();
            $conn->close();
            exit();
        }

        $tokenRecord = $result->fetch_assoc();
        $userId = $tokenRecord['user_id'];
        $stmt->close();

        // Hash the new password using bcrypt
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        // Update user's password
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update password: " . $stmt->error);
        }
        $stmt->close();

        // Delete the used token (and any other tokens for this user)
        $stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $stmt->bind_param("s", $token);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to invalidate token: " . $stmt->error);
        }
        $stmt->close();

        // Commit transaction
        $conn->commit();
        $conn->close();

        // Success
        http_response_code(200);
        echo json_encode(["message" => "Password updated successfully."]);

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Exception in reset_password.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Server error: " . $e->getMessage()]);
}
?>
