<?php

declare(strict_types=1);

require_once '../app/config/database.php';
$conn = database_connection();

function course_builder_schema_missing(mysqli $conn): array
{
    $required = [
        'courses' => ['submitted_at', 'reviewed_at', 'reviewed_by', 'review_note'],
        'notifications' => ['notification_type', 'read_at'],
        'course_change_logs' => ['id', 'course_id', 'instructor_id', 'after_snapshot', 'previous_status', 'new_status'],
    ];

    $missing = [];

    foreach ($required as $table => $columns) {
        $tableStmt = $conn->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $tableStmt->bind_param('s', $table);
        $tableStmt->execute();
        $tableExists = (int) ($tableStmt->get_result()->fetch_assoc()['total'] ?? 0) === 1;
        $tableStmt->close();

        if (!$tableExists) {
            $missing[] = $table;
            continue;
        }

        foreach ($columns as $column) {
            $columnStmt = $conn->prepare(
                'SELECT COUNT(*) AS total
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND column_name = ?'
            );
            $columnStmt->bind_param('ss', $table, $column);
            $columnStmt->execute();
            $columnExists = (int) ($columnStmt->get_result()->fetch_assoc()['total'] ?? 0) === 1;
            $columnStmt->close();

            if (!$columnExists) {
                $missing[] = $table . '.' . $column;
            }
        }
    }

    return $missing;
}

$missingSchema = course_builder_schema_missing($conn);

if ($missingSchema !== []) {
    http_response_code(503);
    $missingText = htmlspecialchars(implode(', ', $missingSchema), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Course builder database update required</title>
        <style>
            body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#eee6d9;color:#171511;font-family:Arial,sans-serif}.box{width:min(720px,100%);padding:34px;border:1px solid rgba(72,58,39,.18);border-radius:24px;background:#fffaf0;box-shadow:0 20px 55px rgba(39,31,21,.12)}h1{margin:0 0 12px;font-family:Georgia,"Times New Roman",serif;font-size:2.2rem;font-weight:500}p{line-height:1.7;color:#62594f}code{display:block;margin:18px 0;padding:14px;border-radius:12px;background:#171511;color:#fffaf0;overflow:auto}.missing{font-size:.84rem;color:#7b5a22}a{display:inline-flex;margin-top:10px;padding:11px 16px;border-radius:999px;background:#171511;color:#fffaf0;text-decoration:none;font-weight:700}
        </style>
    </head>
    <body>
        <main class="box">
            <h1>Course builder database update required</h1>
            <p>The connection to <strong>coursehub</strong> on port <strong>3307</strong> is working, but the course workflow schema is incomplete.</p>
            <p class="missing">Missing: <?php echo $missingText; ?></p>
            <code>database/migrations/20260712_coursehub_compatibility.sql</code>
            <p>Import that migration into <strong>coursehub</strong>, then refresh this page.</p>
            <a href="instructor-dashboard.php">Back to dashboard</a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

require_once '../app/views/instructor/create_course.php';
