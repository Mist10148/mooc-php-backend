<?php
// mooc_api/delete_comment.php

// 1. Include Config (Handles DB connection, CORS, and Headers)
require_once 'config.php';

// Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Read Input
$rawInput = file_get_contents("php://input");
$data     = json_decode($rawInput, true);

$comment_id = isset($data['comment_id']) ? (int)$data['comment_id'] : 0;
$user_id    = isset($data['user_id'])    ? (int)$data['user_id']    : 0;

if ($comment_id <= 0 || $user_id <= 0) {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Invalid IDs"]);
    exit;
}

try {
    // $pdo is available from config.php

    // 3. Verify ownership
    $stmt = $pdo->prepare("SELECT user_id FROM tra_comment WHERE comment_id = :id LIMIT 1");
    $stmt->execute([":id" => $comment_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Comment not found"]);
        exit;
    }

    // Check if the user requesting deletion owns the comment
    if ((int)$row['user_id'] !== $user_id) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Unauthorized"]);
        exit;
    }

    // 4. Delete Comment
    $del = $pdo->prepare("DELETE FROM tra_comment WHERE comment_id = :id");
    $del->execute([":id" => $comment_id]);

    echo json_encode(["success" => true, "message" => "Deleted successfully"]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error", "error" => $e->getMessage()]);
}
?>