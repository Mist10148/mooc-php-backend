<?php
// mooc_api/create_comment.php

// 1. Include Config (Handles DB connection, CORS, and Headers)
require_once 'config.php';

// Debugging (Optional)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Read JSON body
$rawInput = file_get_contents("php://input");
$data     = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON body"]);
    exit;
}

$content_id   = isset($data['content_id'])   ? (int)$data['content_id']   : 0;
$user_id      = isset($data['user_id'])      ? (int)$data['user_id']      : 0;
$rating       = isset($data['rating'])       ? (int)$data['rating']       : 0;
$comment_text = isset($data['comment_text']) ? trim($data['comment_text']) : "";

if ($content_id <= 0 || $user_id <= 0 || $rating < 1 || $rating > 5 || $comment_text === "") {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Validation failed: Check IDs, rating (1-5), and comment text."]);
    exit;
}

try {
    // $pdo is available from config.php

    // 3. Fetch user_name to store with comment
    $stmtUser = $pdo->prepare("SELECT name FROM users WHERE id = :id LIMIT 1");
    $stmtUser->execute([":id" => $user_id]);
    $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "User not found"]);
        exit;
    }

    $user_name = $userRow["name"];

    // 4. Insert Comment
    $stmt = $pdo->prepare("
        INSERT INTO tra_comment (content_id, user_id, user_name, rating, comment_text)
        VALUES (:content_id, :user_id, :user_name, :rating, :comment_text)
    ");

    $stmt->execute([
        ":content_id"   => $content_id,
        ":user_id"      => $user_id,
        ":user_name"    => $user_name,
        ":rating"       => $rating,
        ":comment_text" => $comment_text
    ]);

    echo json_encode([
        "success"      => true,
        "message"      => "Comment saved successfully",
        "comment_id"   => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error", "error" => $e->getMessage()]);
}
?>