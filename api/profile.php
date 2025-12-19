<?php
// api/profile.php - Handles fetching (GET) and updating (POST) user profile data.

// Set necessary CORS headers for your frontend to communicate
header("Access-Control-Allow-Origin: *"); // Restrict this to your frontend domain in production
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle pre-flight OPTIONS request (required for complex CORS requests)
if ($_SERVER["REQUEST_METHOD"] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session or check for a JWT/Authentication token
session_start();

// Include database configuration (make sure the path is correct: "../config.php")
require_once '../config.php';
$pdo = connect_db();

if ($pdo === null) {
    http_response_code(500);
    echo json_encode(["error" => "Server error: Database connection failed."]);
    exit();
}

// --- Placeholder for Authenticated User ID ---
// In a real application, this user_id MUST come from a secure session or token verification.
// Example: $_SESSION['user_id'] or decoded JWT payload.
$logged_in_user_id = 1; // HARDCODED for demonstration. CHANGE THIS.
// if (!isset($_SESSION['user_id'])) {
//     http_response_code(401);
//     echo json_encode(["error" => "Authentication required."]);
//     exit();
// }
// $logged_in_user_id = $_SESSION['user_id'];
// ---------------------------------------------


$request_method = $_SERVER["REQUEST_METHOD"];

switch ($request_method) {
    case 'GET':
        handle_get_profile($pdo, $logged_in_user_id);
        break;

    case 'POST':
        handle_update_profile($pdo, $logged_in_user_id);
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed. Use GET or POST."]);
        break;
}

/**
 * Fetches the user's profile data from the database.
 */
function handle_get_profile($pdo, $user_id) {
    try {
        // Select all required fields from the `users` table as seen in your my_app_db.sql
        $sql = "SELECT id, full_name, email, role, avatar_id, created_at FROM users WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user) {
            http_response_code(200);
            
            // Convert database snake_case to frontend camelCase for seamless integration with ProfileForm.tsx
            $response = [
                "id" => $user['id'],
                "fullName" => $user['full_name'], // Matches `fullName` in ProfileForm.tsx
                "email" => $user['email'],
                "role" => $user['role'],
                "avatarId" => $user['avatar_id'], // Matches `selectedAvatar` in ProfileForm.tsx
                "createdAt" => $user['created_at'],
            ];
            
            echo json_encode($response);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "User profile not found."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Profile Fetch Error: " . $e->getMessage());
        echo json_encode(["error" => "A server error occurred during profile retrieval."]);
    }
}

/**
 * Updates the user's profile data (full_name and avatar_id) in the database.
 */
function handle_update_profile($pdo, $user_id) {
    // Read the raw JSON body from the POST request
    $data = json_decode(file_get_contents("php://input"), true);

    // Extract fields expected from ProfileForm.tsx: { fullName, avatarId }
    $fullName = $data['fullName'] ?? null;
    $avatarId = $data['avatarId'] ?? null;

    if (empty($fullName) || $avatarId === null) {
        http_response_code(400);
        echo json_encode(["error" => "Full name and Avatar ID must be provided."]);
        return;
    }

    // --- Server-side Validation ---
    $fullName = trim(strip_tags($fullName)); // Basic cleaning
    // Assuming avatarId is an INT for simplicity (adjust if it's a string like 'smile')
    $avatarId = (int) $avatarId; 

    if (strlen($fullName) < 3 || strlen($fullName) > 255) {
        http_response_code(400);
        echo json_encode(["error" => "Full name is invalid."]);
        return;
    }

    try {
        // Use a prepared statement to securely update the 'users' table
        $sql = "UPDATE users SET full_name = :full_name, avatar_id = :avatar_id WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        // Bind parameters safely
        $stmt->bindParam(':full_name', $fullName);
        $stmt->bindParam(':avatar_id', $avatarId, PDO::PARAM_INT);
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Check if any row was actually updated
            if ($stmt->rowCount() > 0) {
                http_response_code(200);
                echo json_encode(["message" => "Profile updated successfully."]);
            } else {
                 // It executed but no change (data was the same, or user ID didn't match)
                 // You might return a success message or a warning depending on requirements
                 http_response_code(200); 
                 echo json_encode(["message" => "No changes detected or user not found.", "updated" => false]);
            }
        } 
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Profile Update Error: " . $e->getMessage());
        echo json_encode(["error" => "A server error occurred during profile update."]);
    }
}
?>