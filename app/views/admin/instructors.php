<?php

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
require_once __DIR__ . '/../../config/database.php';

AdminMiddleware::handle();

$message = '';
$messageType = '';

if (isset($_GET['approved'])) {
    $message = 'Instructor approved successfully.';
    $messageType = 'success';
}

if (isset($_GET['blocked'])) {
    $message = 'Instructor blocked successfully.';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_instructor'])) {
    $instructorId = (int) ($_POST['instructor_id'] ?? 0);

    if ($instructorId > 0) {
        $sql = "UPDATE users SET status = 'active' WHERE id = ? AND role = 'instructor'";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("i", $instructorId);
            $stmt->execute();
            $stmt->close();

            header("Location: admin-instructors.php?approved=1");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_instructor'])) {
    $instructorId = (int) ($_POST['instructor_id'] ?? 0);

    if ($instructorId > 0) {
        $sql = "UPDATE users SET status = 'blocked' WHERE id = ? AND role = 'instructor'";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("i", $instructorId);
            $stmt->execute();
            $stmt->close();

            header("Location: admin-instructors.php?blocked=1");
            exit;
        }
    }
}

$instructors = [];

$sql = "
    SELECT *
    FROM users
    WHERE role = 'instructor'
    ORDER BY 
        CASE 
            WHEN status = 'inactive' THEN 1
            WHEN status = 'pending' THEN 1
            WHEN status = 'active' THEN 2
            WHEN status = 'blocked' THEN 3
            ELSE 4
        END,
        created_at DESC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $instructors[] = $row;
    }
}

$totalInstructors = count($instructors);
$pendingCount = 0;
$activeCount = 0;
$blockedCount = 0;

foreach ($instructors as $instructor) {
    $status = $instructor['status'] ?? '';

    if ($status === 'inactive' || $status === 'pending') {
        $pendingCount++;
    } elseif ($status === 'active') {
        $activeCount++;
    } elseif ($status === 'blocked') {
        $blockedCount++;
    }
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function readable_field_name($field)
{
    return ucwords(str_replace('_', ' ', $field));
}

function display_value($value)
{
    $value = trim((string) $value);

    return $value !== '' ? $value : 'Not provided';
}

function instructor_status_label($status)
{
    if ($status === 'inactive' || $status === 'pending') {
        return 'Pending Approval';
    }

    if ($status === 'active') {
        return 'Approved';
    }

    if ($status === 'blocked') {
        return 'Blocked';
    }

    return ucfirst((string) $status);
}

function instructor_status_class($status)
{
    if ($status === 'inactive' || $status === 'pending') {
        return 'status-pending';
    }

    if ($status === 'active') {
        return 'status-active';
    }

    if ($status === 'blocked') {
        return 'status-blocked';
    }

    return 'status-pending';
}

function safe_detail_fields($instructor)
{
    $hiddenFields = [
        'password',
        'password_hash',
        'remember_token',
        'reset_token',
        'verification_token',
        'email_verified_token'
    ];

    $fields = [];

    foreach ($instructor as $key => $value) {
        if (in_array($key, $hiddenFields, true)) {
            continue;
        }

        $fields[$key] = $value;
    }

    return $fields;
}

function is_image_file($fileName)
{
    $extension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));

    return in_array($extension, ['jpg', 'jpeg', 'png'], true);
}

function is_pdf_file($fileName)
{
    $extension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));

    return $extension === 'pdf';
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/admin_navbar.php';
?>



<main class="admin-instructors-page">
    <section class="admin-instructors-wrapper">

        <div class="admin-instructors-header">
            <div>
                <p class="page-label">Admin Panel</p>
                <h1>Instructor Requests</h1>
                <p>
                    Click an instructor card to view full request data, profile photo, and uploaded document clearly.
                </p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="admin-alert <?php echo h($messageType); ?>">
                <?php echo h($message); ?>
            </div>
        <?php endif; ?>

        <div class="instructor-stats-grid">
            <div class="stat-card">
                <span>Total Instructors</span>
                <strong><?php echo $totalInstructors; ?></strong>
            </div>

            <div class="stat-card pending">
                <span>Pending Requests</span>
                <strong><?php echo $pendingCount; ?></strong>
            </div>

            <div class="stat-card active">
                <span>Approved</span>
                <strong><?php echo $activeCount; ?></strong>
            </div>

            <div class="stat-card blocked">
                <span>Blocked</span>
                <strong><?php echo $blockedCount; ?></strong>
            </div>
        </div>

        <?php if (empty($instructors)): ?>

            <div class="empty-instructors-box">
                <div class="empty-icon">No instructors</div>
                <h2>No instructor requests found</h2>
                <p>When users register as instructors, their requests will appear here.</p>
            </div>

        <?php else: ?>

            <div class="section-title-row">
                <h2>Instructor Request Cards</h2>
                <p>Pending requests appear first. Click any card to view full details.</p>
            </div>

            <div class="instructors-grid">

                <?php foreach ($instructors as $instructor): ?>
                    <?php
                        $instructorId = (int) ($instructor['id'] ?? 0);
                        $status = $instructor['status'] ?? 'inactive';

                        $profileImage = $instructor['profile_image'] ?? '';
                        $identityDocument = $instructor['identity_document'] ?? '';

                        $hasProfileImage = !empty($profileImage);
                        $hasDocument = !empty($identityDocument);

                        $profilePath = 'admin-view-instructor-file.php?type=profile&id=' . $instructorId;
                        $documentPath = 'admin-view-instructor-file.php?type=document&id=' . $instructorId;

                        $modalId = 'instructorModal' . $instructorId;

                        $firstLetter = strtoupper(substr($instructor['full_name'] ?? 'I', 0, 1));
                    ?>

                    <article 
                        class="instructor-request-card open-instructor-modal <?php echo ($status === 'inactive' || $status === 'pending') ? 'pending-card' : ''; ?>"
                        data-target="<?php echo h($modalId); ?>"
                    >

                        <div class="request-card-top">
                            <div class="instructor-avatar">
                                <?php if ($hasProfileImage): ?>
                                    <img 
                                        src="<?php echo h($profilePath); ?>" 
                                        alt="<?php echo h($instructor['full_name'] ?? 'Instructor'); ?>"
                                    >
                                <?php else: ?>
                                    <div class="avatar-letter">
                                        <?php echo h($firstLetter); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="request-main-info">
                                <h2><?php echo h($instructor['full_name'] ?? 'Unknown Instructor'); ?></h2>

                                <span class="status-pill <?php echo instructor_status_class($status); ?>">
                                    <?php echo instructor_status_label($status); ?>
                                </span>
                            </div>
                        </div>

                        <div class="request-basic-info">
                            <div>
                                <span>Email</span>
                                <strong><?php echo h($instructor['email'] ?? 'Not provided'); ?></strong>
                            </div>

                            <div>
                                <span>Phone</span>
                                <strong><?php echo h(display_value($instructor['phone'] ?? '')); ?></strong>
                            </div>

                            <div>
                                <span>Document</span>
                                <strong><?php echo $hasDocument ? 'Submitted' : 'Not submitted'; ?></strong>
                            </div>

                            <div>
                                <span>Registered</span>
                                <strong>
                                    <?php
                                        echo !empty($instructor['created_at'])
                                            ? h(date('M d, Y', strtotime($instructor['created_at'])))
                                            : 'Unknown';
                                    ?>
                                </strong>
                            </div>
                        </div>

                        <div class="click-hint">
                            Click to view all request details
                        </div>

                    </article>

                    <div class="instructor-modal-overlay" id="<?php echo h($modalId); ?>">
                        <div class="instructor-modal-card">

                            <div class="modal-header">
                                <div>
                                    <p class="modal-label">Instructor Request Details</p>
                                    <h2><?php echo h($instructor['full_name'] ?? 'Unknown Instructor'); ?></h2>
                                </div>

                                <button type="button" class="close-modal-btn" data-close-modal>
                                    &times;
                                </button>
                            </div>

                            <div class="modal-profile-row">
                                <div class="modal-avatar">
                                    <?php if ($hasProfileImage): ?>
                                        <img 
                                            src="<?php echo h($profilePath); ?>" 
                                            alt="<?php echo h($instructor['full_name'] ?? 'Instructor'); ?>"
                                        >
                                    <?php else: ?>
                                        <div class="avatar-letter">
                                            <?php echo h($firstLetter); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="modal-profile-info">
                                    <span class="status-pill <?php echo instructor_status_class($status); ?>">
                                        <?php echo instructor_status_label($status); ?>
                                    </span>

                                    <p>
                                        Role:
                                        <strong><?php echo h(ucfirst($instructor['role'] ?? 'instructor')); ?></strong>
                                    </p>

                                    <p>
                                        Email:
                                        <strong><?php echo h($instructor['email'] ?? 'Not provided'); ?></strong>
                                    </p>
                                </div>
                            </div>

                            <div class="document-preview-section">
                                <div class="document-preview-header">
                                    <div>
                                        <h3>Identity Verification Document</h3>
                                        <p>
                                            Admin can clearly review this document before approving the instructor.
                                        </p>
                                    </div>

                                    <?php if ($hasDocument): ?>
                                        <a 
                                            href="<?php echo h($documentPath); ?>" 
                                            target="_blank" 
                                            class="open-document-btn"
                                        >
                                            Open Full Size
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <?php if ($hasDocument): ?>

                                    <?php if (is_image_file($identityDocument)): ?>
                                        <div class="document-preview-box">
                                            <img 
                                                src="<?php echo h($documentPath); ?>" 
                                                alt="Instructor identity document"
                                            >
                                        </div>
                                    <?php elseif (is_pdf_file($identityDocument)): ?>
                                        <div class="document-preview-box document-pdf-box">
                                            <iframe 
                                                src="<?php echo h($documentPath); ?>" 
                                                title="Instructor identity document"
                                            ></iframe>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-document-box">
                                            This document type cannot be previewed here. Please open full size.
                                        </div>
                                    <?php endif; ?>

                                    <div class="document-file-name">
                                        File:
                                        <strong><?php echo h($identityDocument); ?></strong>
                                    </div>

                                <?php else: ?>

                                    <div class="no-document-box">
                                        No identity document uploaded.
                                    </div>

                                <?php endif; ?>
                            </div>

                            <div class="details-section">
                                <h3>All Instructor Data</h3>
                                <p>Password/password hash is hidden for security.</p>
                            </div>

                            <div class="details-grid">

                                <?php foreach (safe_detail_fields($instructor) as $field => $value): ?>
                                    <div class="detail-box">
                                        <span><?php echo h(readable_field_name($field)); ?></span>

                                        <strong>
                                            <?php
                                                if ($field === 'created_at' || $field === 'updated_at') {
                                                    echo !empty($value)
                                                        ? h(date('M d, Y h:i A', strtotime($value)))
                                                        : 'Not provided';
                                                } else {
                                                    echo h(display_value($value));
                                                }
                                            ?>
                                        </strong>
                                    </div>
                                <?php endforeach; ?>

                            </div>

                            <div class="modal-actions">

                                <?php if ($status === 'inactive' || $status === 'pending'): ?>

                                    <form method="POST">
                                          <?php echo csrf_field(); ?>
                                        <input 
                                            type="hidden" 
                                            name="instructor_id" 
                                            value="<?php echo $instructorId; ?>"
                                        >

                                        <button 
                                            type="submit" 
                                            name="approve_instructor" 
                                            class="action-btn approve"
                                        >
                                            Approve Instructor
                                        </button>
                                    </form>

                                    <form method="POST">
                                          <?php echo csrf_field(); ?>
                                        <input 
                                            type="hidden" 
                                            name="instructor_id" 
                                            value="<?php echo $instructorId; ?>"
                                        >

                                        <button 
                                            type="submit" 
                                            name="block_instructor" 
                                            class="action-btn block"
                                        >
                                            Block Request
                                        </button>
                                    </form>

                                <?php elseif ($status === 'active'): ?>

                                    <form method="POST">
                                          <?php echo csrf_field(); ?>
                                        <input 
                                            type="hidden" 
                                            name="instructor_id" 
                                            value="<?php echo $instructorId; ?>"
                                        >

                                        <button 
                                            type="submit" 
                                            name="block_instructor" 
                                            class="action-btn block"
                                        >
                                            Block Instructor
                                        </button>
                                    </form>

                                <?php else: ?>

                                    <form method="POST">
                                          <?php echo csrf_field(); ?>
                                        <input 
                                            type="hidden" 
                                            name="instructor_id" 
                                            value="<?php echo $instructorId; ?>"
                                        >

                                        <button 
                                            type="submit" 
                                            name="approve_instructor" 
                                            class="action-btn approve"
                                        >
                                            Unblock / Approve
                                        </button>
                                    </form>

                                <?php endif; ?>

                            </div>

                        </div>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>
</main>

<script src="assets/js/admin_instructors.js"></script>

</body>
</html>
