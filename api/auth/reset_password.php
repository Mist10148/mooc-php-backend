<?php
/**
 * reset_password.php
 * Handles the actual password reset using the token from email
 * 
 * Place this in: /api/auth/reset_password.php
 */

// Load your existing config (adjust path as needed)
require_once __DIR__ . '/../config.php';

// Override CORS for more specific control
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
$MIN_PASSWORD_LENGTH = 6;

// =============================================================================
// Main Logic
// =============================================================================

try {
    // $pdo is already available from config.php
    global $pdo;

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

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Check if token exists and is not expired
        $stmt = $pdo->prepare("SELECT user_id FROM password_reset_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $tokenRecord = $stmt->fetch();

        if (!$tokenRecord) {
            $pdo->rollBack();
            http_response_code(401);
            echo json_encode(["message" => "Invalid or expired password reset link."]);
            exit();
        }

        $userId = $tokenRecord['user_id'];

        // Hash the new password using bcrypt
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        // Update user's password
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);

        // Delete the used token
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$token]);

        // Commit transaction
        $pdo->commit();

        // Success
        http_response_code(200);
        echo json_encode(["message" => "Password updated successfully."]);

    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Database error in reset_password.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Database error occurred."]);
} catch (Exception $e) {
    error_log("Exception in reset_password.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Server error: " . $e->getMessage()]);
}
?>