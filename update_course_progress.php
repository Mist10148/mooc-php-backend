<?php
// mooc_api/update_course_progress.php

// 1. Include Config
require_once 'config.php';

// 2. Read Input
$raw = file_get_contents("php://input");
$json = json_decode($raw, true);

$userId = isset($json['user_id']) ? (int)$json['user_id'] : 0;
$courseId = isset($json['course_id']) ? (int)$json['course_id'] : 0;
$progress = isset($json['progress']) ? (int)$json['progress'] : 0;
$lessonsFinished = isset($json['lessons_finished']) ? (int)$json['lessons_finished'] : 0;

if ($progress < 0) $progress = 0;
if ($progress > 100) $progress = 100;

if ($userId <= 0 || $courseId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid IDs']);
    exit;
}

try {
    // $pdo is available from config.php

    $sql = "
        UPDATE tra_user_courses
        SET
            progress = :progress,
            lessons_finished = :lessons_finished
        WHERE user_id = :user_id
          AND enrolled_course = :course_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':progress'         => $progress,
        ':lessons_finished' => $lessonsFinished,
        ':user_id'          => $userId,
        ':course_id'        => $courseId,
    ]);

    if ($stmt->rowCount() === 0) {
        // Technically not an error if values didn't change
        echo json_encode(['success' => true, 'message' => 'Progress updated (or no changes needed)']);
    } else {
        echo json_encode(['success' => true, 'progress' => $progress]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
}
?>