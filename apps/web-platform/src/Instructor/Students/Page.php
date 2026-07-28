<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class InstructorStudentsPage
{
    public static function render(array $summary, array $students, string $error = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $metric = static fn (string $key): int => max(0, (int) ($summary[$key] ?? 0));
        $rows = '';

        foreach ($students as $student) {
            if (!is_array($student)) {
                continue;
            }

            $studentName = trim((string) ($student['student_name'] ?? 'Student'));
            $studentEmail = trim((string) ($student['student_email'] ?? ''));
            $courseTitle = trim((string) ($student['course_title'] ?? 'Course'));
            $courseId = max(0, (int) ($student['course_id'] ?? 0));
            $completed = max(0, (int) ($student['completed_lessons'] ?? 0));
            $total = max(0, (int) ($student['total_lessons'] ?? 0));
            $progress = max(0, min(100, (int) ($student['progress_percent'] ?? 0)));
            $grantedAt = trim((string) ($student['granted_at'] ?? ''));
            $lastActivity = trim((string) ($student['last_activity_at'] ?? ''));
            $accessType = trim((string) ($student['access_type'] ?? 'lifetime'));
            $enrollmentStatus = trim((string) ($student['enrollment_status'] ?? 'active'));

            $progressState = match (true) {
                $total > 0 && $completed >= $total => ['completed', 'Completed'],
                $completed > 0 => ['active', 'Learning'],
                default => ['pending', 'Not started'],
            };
            $enrolledLabel = $grantedAt !== '' && strtotime($grantedAt) !== false
                ? date('M j, Y', strtotime($grantedAt))
                : 'Recorded';
            $activityLabel = $lastActivity !== '' && strtotime($lastActivity) !== false
                ? 'Last activity ' . date('M j, Y', strtotime($lastActivity))
                : 'No lesson activity yet';
            $lessonLabel = $total > 0
                ? $completed . ' of ' . $total . ' lessons'
                : 'Course has no lessons yet';
            $courseAction = $courseId > 0
                ? '<a class="portal-button secondary" href="/course?id=' . $courseId . '">View course</a>'
                : '';

            $rows .= '<tr>'
                . '<td><strong>' . $e($studentName) . '</strong><small>' . $e($studentEmail) . '</small></td>'
                . '<td><strong>' . $e($courseTitle) . '</strong><small>Course #' . $courseId . '</small></td>'
                . '<td><strong>' . $e($enrolledLabel) . '</strong><small>' . $e(ucfirst($accessType)) . ' access</small></td>'
                . '<td><strong>' . $progress . '%</strong><small>' . $e($lessonLabel) . ' · ' . $e($activityLabel) . '</small></td>'
                . '<td><span class="status-badge ' . $e($progressState[0]) . '">' . $e($progressState[1]) . '</span><small>' . $e(ucfirst($enrollmentStatus)) . ' enrollment</small></td>'
                . '<td>' . $courseAction . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr class="empty-row"><td colspan="6"><div><span>⌁</span><strong>No enrolled students yet</strong><small>Students appear here automatically after they receive active access to one of your courses.</small></div></td></tr>';
        }

        $alert = $error !== '' ? '<div class="form-alert error">' . $e($error) . '</div>' : '';
        $content = $alert
            . '<section class="metric-grid">'
            . '<article class="metric-card blue"><div class="metric-top"><span>Related students</span><i></i></div><strong>' . $metric('students') . '</strong><small>Unique learners in your courses</small></article>'
            . '<article class="metric-card violet"><div class="metric-top"><span>Active enrollments</span><i></i></div><strong>' . $metric('active_enrollments') . '</strong><small>Course access records</small></article>'
            . '<article class="metric-card teal"><div class="metric-top"><span>Learning started</span><i></i></div><strong>' . $metric('learning_started') . '</strong><small>Students with lesson activity</small></article>'
            . '<article class="metric-card orange"><div class="metric-top"><span>Courses completed</span><i></i></div><strong>' . $metric('completed_courses') . '</strong><small>Finished enrollment journeys</small></article>'
            . '</section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>YOUR COURSE STUDENTS</span><h3>Students enrolled in courses you own</h3><p>Only active enrollments connected to the logged-in instructor are shown.</p></div><strong>' . count($students) . ' enrollment' . (count($students) === 1 ? '' : 's') . '</strong></div>'
            . '<div class="table-wrap"><table><thead><tr><th>Student</th><th>Course</th><th>Enrolled</th><th>Progress</th><th>Status</th><th>Action</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';

        return PortalPage::render('instructor', 'Students', $content);
    }
}
