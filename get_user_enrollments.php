<?php
// mooc_api/get_user_enrollments.php

// 1. Include Config
require_once 'config.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing user_id']);
    exit;
}

try {
    // $pdo is available from config.php
    
    // Define Asset Base locally if not in config
    $pdo = new PDO($dsn, $user, $pass, $options);
    // --- NEW DYNAMIC ASSET URL LOGIC ---
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $ASSETS_BASE = "{$protocol}://{$host}/mooc_assets/";

    $sql = "
        SELECT
            e.id            AS enrollment_id,
            e.user_id,
            e.enrolled_course,
            e.progress AS progress,   
            e.enrolled_at,
            e.status,
            e.course_thumbnail AS enrollment_course_thumbnail,
            c.course_id,
            c.course_title,
            c.course_description,
            c.course_sub_description,
            c.course_price,
            c.course_category,
            c.course_thumbnail,
            c.instructor_id,
            i.instructor_name,
            i.instructor_title
        FROM tra_user_courses AS e
        INNER JOIN ref_courses AS c
            ON e.enrolled_course = c.course_id
        LEFT JOIN ref_instructors AS i
            ON c.instructor_id = i.instructor_id
        WHERE e.user_id = :user_id
        ORDER BY e.enrolled_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $rows = $stmt->fetchAll();

    // Attach thumbnail URLs
    foreach ($rows as &$row) {
        $thumb = $row['enrollment_course_thumbnail'] ?: $row['course_thumbnail'];
        if (!empty($thumb)) {
            $row['course_thumbnail_url'] = $ASSETS_BASE . $thumb;
        } else {
            $row['course_thumbnail_url'] = null;
        }
    }
    unset($row);

    echo json_encode(['success' => true, 'data' => $rows]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
}
?>