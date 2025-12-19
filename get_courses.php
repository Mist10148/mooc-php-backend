<?php
// mooc_api/get_courses.php

// 1. Include Config
require_once 'config.php';

try {
    // $pdo is available from config.php

    // base path for image hosting
    $pdo = new PDO($dsn, $user, $pass, $options);

    // --- NEW DYNAMIC ASSET URL LOGIC ---
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $ASSETS_BASE = "{$protocol}://{$host}/mooc_assets/";


    // =====================
    // 1) COURSES + INSTRUCTORS
    // =====================
    $sqlCourses = "
        SELECT
            c.course_id,
            c.course_title,
            c.course_description,
            c.course_sub_description,
            c.course_objectives,      
            c.course_price,
            c.course_category,
            c.course_thumbnail,
            c.instructor_id,
            i.instructor_name,
            i.instructor_title,
            i.instructor_bio,
            i.instructor_image_path
        FROM ref_courses AS c
        LEFT JOIN ref_instructors AS i
            ON c.instructor_id = i.instructor_id
        ORDER BY c.course_id ASC
    ";

    $stmt    = $pdo->query($sqlCourses);
    $courses = $stmt->fetchAll();

    // =====================
    // 2) COURSE CONTENT (ref_course_content)
    // =====================
    $sqlContent = "
        SELECT
            content_id,
            course_conn_id,
            course_id,
            course_content_title,
            course_content_lessons,
            course_content_length,
            created_at,
            updated_at
        FROM ref_course_content
        ORDER BY course_conn_id ASC, course_id ASC
    ";

    $stmtContent  = $pdo->query($sqlContent);
    $contentsRows = $stmtContent->fetchAll();

    // =====================
    // 3) LESSONS (ref_course_lessons)
    $sqlLessons = "
        SELECT
            lesson_id,
            content_id,
            lesson_title,
            lesson_duration,
            lesson_type,
            progress,           
            lesson_directory,
            created_at,
            updated_at
        FROM ref_course_lessons
        ORDER BY content_id ASC, lesson_id ASC
    ";

    $stmtLessons  = $pdo->query($sqlLessons);
    $lessonsRows  = $stmtLessons->fetchAll();

    // Optional: build full URL for lesson_directory
    foreach ($lessonsRows as &$lesson) {
        if (!empty($lesson['lesson_directory'])) {
            $lesson['lesson_directory_url'] = $ASSETS_BASE . $lesson['lesson_directory'];
        } else {
            $lesson['lesson_directory_url'] = null;
        }
    }
    unset($lesson);

    // Group lessons by content_id
    $lessonsByContent = [];
    foreach ($lessonsRows as $lesson) {
        $cid = $lesson['content_id'];
        if (!isset($lessonsByContent[$cid])) {
            $lessonsByContent[$cid] = [];
        }
        $lessonsByContent[$cid][] = $lesson;
    }

    // Group contents by course_conn_id and attach lessons
    $groupedContents = [];
    foreach ($contentsRows as $row) {
        $courseConnId = $row['course_conn_id'];
        $contentId    = $row['content_id'];

        // attach lessons[] for this content/module
        $row['lessons'] = $lessonsByContent[$contentId] ?? [];

        if (!isset($groupedContents[$courseConnId])) {
            $groupedContents[$courseConnId] = [];
        }
        $groupedContents[$courseConnId][] = $row;
    }

    // =====================
    // 4) ENRICH COURSES: image URLs + attached contents
    // =====================
    foreach ($courses as &$c) {
        // Full URL for course thumbnail
        if (!empty($c['course_thumbnail'])) {
            $c['course_thumbnail_url'] = $ASSETS_BASE . $c['course_thumbnail'];
        } else {
            $c['course_thumbnail_url'] = null;
        }

        // Full URL for instructor image
        if (!empty($c['instructor_image_path'])) {
            $c['instructor_image_url'] = $ASSETS_BASE . $c['instructor_image_path'];
        } else {
            $c['instructor_image_url'] = null;
        }

        // Attach all course_content rows (with nested lessons) for this course
        $courseId = $c['course_id']; 
        $c['course_contents'] = $groupedContents[$courseId] ?? [];
    }
    unset($c); 

    echo json_encode($courses);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'Database error',
        'message' => $e->getMessage()
    ]);
}
?>