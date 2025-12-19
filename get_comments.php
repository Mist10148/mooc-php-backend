<?php
// mooc_api/get_comments.php

// 1. Include Config
require_once 'config.php';

// Debugging (Optional)
error_reporting(E_ALL);
ini_set('display_errors', 1);

$content_id = isset($_GET['content_id']) ? (int)$_GET['content_id'] : 0;

if ($content_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "content_id is required"]);
    exit;
}

try {
    // $pdo is available from config.php

    $stmt = $pdo->prepare("
        SELECT comment_id, content_id, user_id, user_name, rating, comment_text, created_at
        FROM tra_comment
        WHERE content_id = :content_id
        ORDER BY created_at DESC, comment_id DESC
    ");
    $stmt->execute([":content_id" => $content_id]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error", "error" => $e->getMessage()]);
}
?>