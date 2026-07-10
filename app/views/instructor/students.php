<?php

require_once __DIR__ . '/../../middleware/InstructorMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

InstructorMiddleware::handle();

$user = Auth::user();
$instructorId = (int) ($user['id'] ?? 0);

$students = [];
$totalStudents = 0;
$totalEnrollments = 0;
$errorMessage = '';

$sql = "
    SELECT
        u.id AS student_id,
        u.full_name,
        u.email,
        u.phone,
        u.profile_image,
        u.status AS student_status,
        c.id AS course_id,
        c.title AS course_title,
        c.thumbnail AS course_thumbnail,
        e.id AS enrollment_id,
        e.status AS enrollment_status,
        e.access_type,
        e.granted_at,
        e.created_at AS enrolled_at,
        o.id AS order_id,
        o.final_amount,
        p.payment_method,
        p.payment_status
    FROM enrollments e
    INNER JOIN courses c ON e.course_id = c.id
    INNER JOIN users u ON e.student_id = u.id
    LEFT JOIN orders o ON e.order_id = o.id
    LEFT JOIN payments p ON e.payment_id = p.id
    WHERE c.instructor_id = ?
      AND u.role = 'student'
      AND e.status = 'active'
    ORDER BY e.created_at DESC
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $instructorId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }

    $stmt->close();
} else {
    $errorMessage = 'Unable to load enrolled students.';
}

$totalEnrollments = count($students);

$uniqueStudentIds = [];

foreach ($students as $student) {
    $uniqueStudentIds[$student['student_id']] = true;
}

$totalStudents = count($uniqueStudentIds);

function display_value($value, $fallback = 'Not provided')
{
    $value = trim((string) $value);

    return $value !== '' ? $value : $fallback;
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/instructor_navbar.php';
?>



<main class="instructor-students-page">
    <section class="instructor-students-wrapper">

        <div class="students-header">
            <div>
                <p class="page-label">Audience</p>
                <h1>Enrolled students</h1>
                <p>
                    Only students with an active enrollment in one of your courses appear here.
                </p>
            </div>
        </div>

        <?php if ($errorMessage !== ''): ?>
            <div class="students-alert error">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <div class="students-stats-grid">
            <div class="stat-card">
                <span>Total Students</span>
                <strong><?php echo $totalStudents; ?></strong>
            </div>

            <div class="stat-card">
                <span>Total Enrollments</span>
                <strong><?php echo $totalEnrollments; ?></strong>
            </div>
        </div>

        <?php if (empty($students)): ?>

            <div class="empty-students-box">
                <div class="empty-icon">No students</div>
                <h2>No students yet</h2>
                <p>
                    When students buy or enroll in your courses, they will appear here.
                </p>
            </div>

        <?php else: ?>

            <div class="students-grid">

                <?php foreach ($students as $student): ?>
                    <?php
                        $studentInitial = strtoupper(substr((string) $student['full_name'], 0, 1));

                        $courseThumbnail = $student['course_thumbnail'] ?? '';

                        if ($courseThumbnail !== '') {
                            $courseImagePath = 'assets/uploads/course_thumbnails/' . $courseThumbnail;
                        } else {
                            $courseImagePath = 'assets/images/course-placeholder.svg';
                        }

                        $email = display_value($student['email']);
                        $phone = display_value($student['phone']);
                    ?>

                    <article class="student-card">

                        <div class="student-top">
                            <div class="student-avatar">
                                <span aria-hidden="true"><?php echo htmlspecialchars($studentInitial); ?></span>
                            </div>

                            <div class="student-main-info">
                                <h2><?php echo htmlspecialchars($student['full_name']); ?></h2>

                                <span class="student-status">
                                    <?php echo htmlspecialchars(ucfirst($student['student_status'])); ?>
                                </span>
                            </div>
                        </div>

                        <div class="student-contact-box">
                            <div class="contact-row">
                                <span>Email</span>
                                <a href="mailto:<?php echo htmlspecialchars($email); ?>">
                                    <?php echo htmlspecialchars($email); ?>
                                </a>
                            </div>

                            <div class="contact-row">
                                <span>Phone</span>
                                <?php if ($student['phone']): ?>
                                    <a href="tel:<?php echo htmlspecialchars($student['phone']); ?>">
                                        <?php echo htmlspecialchars($phone); ?>
                                    </a>
                                <?php else: ?>
                                    <p><?php echo htmlspecialchars($phone); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="student-course-box">
                            <div class="course-mini-image">
                                <img 
                                    src="<?php echo htmlspecialchars($courseImagePath); ?>" 
                                    alt="<?php echo htmlspecialchars($student['course_title']); ?>"
                                >
                            </div>

                            <div class="course-mini-info">
                                <span>Enrolled Course</span>
                                <h3><?php echo htmlspecialchars($student['course_title']); ?></h3>
                            </div>
                        </div>

                        <div class="student-extra-info">
                            <div>
                                <span>Enrollment</span>
                                <strong><?php echo htmlspecialchars(ucfirst($student['enrollment_status'])); ?></strong>
                            </div>

                            <div>
                                <span>Access</span>
                                <strong><?php echo htmlspecialchars(ucfirst($student['access_type'])); ?></strong>
                            </div>

                            <div>
                                <span>Payment</span>
                                <strong>
                                    <?php echo htmlspecialchars(ucfirst($student['payment_status'] ?? 'Unknown')); ?>
                                </strong>
                            </div>

                            <div>
                                <span>Amount</span>
                                <strong>
                                    Rs. <?php echo number_format((float) ($student['final_amount'] ?? 0), 2); ?>
                                </strong>
                            </div>
                        </div>

                        <div class="student-date">
                            Enrolled:
                            <?php
                                $dateValue = $student['enrolled_at'] ?: $student['granted_at'];
                                echo $dateValue ? date('M d, Y', strtotime($dateValue)) : 'Unknown';
                            ?>
                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>
</main>

</body>
</html>
