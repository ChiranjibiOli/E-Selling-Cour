<?php

require_once '../app/middleware/StudentMiddleware.php';
require_once '../app/config/database.php';

StudentMiddleware::handle();
Security::requirePost();

$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);
$courseId = (int) ($_POST['course_id'] ?? 0);

if ($courseId <= 0) {
    header("Location: courses.php?invalid=1");
    exit;
}

$courseSql = "
    SELECT id 
    FROM courses 
    WHERE id = ? 
      AND status = 'published'
    LIMIT 1
";

$courseStmt = $conn->prepare($courseSql);
$courseStmt->bind_param("i", $courseId);
$courseStmt->execute();
$courseResult = $courseStmt->get_result();

if (!$courseResult || $courseResult->num_rows !== 1) {
    $courseStmt->close();
    header("Location: courses.php?notfound=1");
    exit;
}

$courseStmt->close();

$enrollSql = "
    SELECT id 
    FROM enrollments 
    WHERE student_id = ? 
      AND course_id = ? 
      AND status = 'active'
    LIMIT 1
";

$enrollStmt = $conn->prepare($enrollSql);
$enrollStmt->bind_param("ii", $studentId, $courseId);
$enrollStmt->execute();
$enrollResult = $enrollStmt->get_result();

if ($enrollResult && $enrollResult->num_rows > 0) {
    $enrollStmt->close();
    header("Location: student-course-view.php?course_id=" . $courseId);
    exit;
}

$enrollStmt->close();

$cartCheckSql = "
    SELECT id 
    FROM cart 
    WHERE student_id = ? 
      AND course_id = ?
    LIMIT 1
";

$cartCheckStmt = $conn->prepare($cartCheckSql);
$cartCheckStmt->bind_param("ii", $studentId, $courseId);
$cartCheckStmt->execute();
$cartCheckResult = $cartCheckStmt->get_result();

if ($cartCheckResult && $cartCheckResult->num_rows === 0) {
    $insertSql = "
        INSERT INTO cart (student_id, course_id)
        VALUES (?, ?)
    ";

    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param("ii", $studentId, $courseId);
    $insertStmt->execute();
    $insertStmt->close();
}

$cartCheckStmt->close();

header("Location: cart.php?added=1");
exit;
