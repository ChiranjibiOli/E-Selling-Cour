<?php

declare(strict_types=1);

use CourseHub\Services\Shared\Database;
use CourseHub\Services\Shared\ServiceAuth;
use CourseHub\Services\Shared\ServiceAuthenticationException;
use CourseHub\Services\Shared\ServiceAuthorizationException;

require_once dirname(__DIR__, 2) . '/_shared/Database.php';
require_once dirname(__DIR__, 2) . '/_shared/ServiceAuth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

$respond = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

$jsonInput = static function (): array {
    $raw = (string) file_get_contents('php://input');
    $decoded = json_decode($raw !== '' ? $raw : '{}', true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body must be a JSON object.');
    }
    return $decoded;
};

$cleanText = static function (mixed $value, string $label, int $min, int $max, bool $required = true, bool $multiline = false): string {
    if (!is_scalar($value) && $value !== null) {
        throw new InvalidArgumentException($label . ' must be a single text value.');
    }
    $text = (string) $value;
    $text = $multiline
        ? str_replace(["\r\n", "\r"], "\n", trim($text))
        : trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if ($text === '') {
        if ($required) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return '';
    }
    if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
        throw new InvalidArgumentException($label . ' contains invalid text encoding.');
    }
    $controlPattern = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
    if (preg_match($controlPattern, $text) === 1) {
        throw new InvalidArgumentException($label . ' contains unsupported control characters.');
    }
    $length = mb_strlen($text);
    if ($length < $min || $length > $max) {
        throw new InvalidArgumentException(sprintf('%s must contain between %d and %d characters.', $label, $min, $max));
    }
    return $text;
};

$listValues = static function (mixed $value, string $label): array {
    $source = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string) $value);
    $items = [];
    foreach (is_array($source) ? $source : [] as $item) {
        if (!is_scalar($item) && $item !== null) {
            throw new InvalidArgumentException($label . ' entries must be text.');
        }
        $text = trim(preg_replace('/\s+/u', ' ', (string) $item) ?? (string) $item);
        if ($text === '') {
            continue;
        }
        if (mb_strlen($text) > 300 || preg_match('/[\x00-\x1F\x7F]/u', $text) === 1) {
            throw new InvalidArgumentException($label . ' entries must be plain text of 300 characters or fewer.');
        }
        $items[$text] = true;
    }
    if (count($items) > 30) {
        throw new InvalidArgumentException($label . ' may contain at most 30 entries.');
    }
    return array_keys($items);
};

$httpsUrl = static function (mixed $value, string $label, bool $required = false): string {
    if (!is_scalar($value) && $value !== null) {
        throw new InvalidArgumentException($label . ' must be a single URL.');
    }
    $url = trim((string) $value);
    if ($url === '') {
        if ($required) {
            throw new InvalidArgumentException($label . ' is required.');
        }
        return '';
    }
    $parts = parse_url($url);
    if (mb_strlen($url) > 500
        || filter_var($url, FILTER_VALIDATE_URL) === false
        || !is_array($parts)
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || trim((string) ($parts['host'] ?? '')) === ''
        || isset($parts['user'])
        || isset($parts['pass'])
    ) {
        throw new InvalidArgumentException($label . ' must be a normal HTTPS address without embedded credentials.');
    }
    return $url;
};

$normaliseSnapshot = static function (array $input, bool $submission = false) use ($cleanText, $listValues, $httpsUrl): array {
    $courseInput = is_array($input['course'] ?? null) ? $input['course'] : $input;
    $title = $cleanText($courseInput['title'] ?? '', 'Course title', 3, 180);
    $subtitle = $cleanText($courseInput['subtitle'] ?? '', 'Course subtitle', 0, 240, false);
    $shortDescription = $cleanText($courseInput['short_description'] ?? '', 'Short description', 20, 500, true, true);
    $fullDescription = $cleanText($courseInput['full_description'] ?? '', 'Full course description', 50, 50_000, true, true);
    $categoryId = filter_var($courseInput['category_id'] ?? null, FILTER_VALIDATE_INT);
    if ($categoryId === false || $categoryId < 1) {
        throw new InvalidArgumentException('Choose a valid course category.');
    }
    $priceRaw = trim((string) ($courseInput['price'] ?? ''));
    $discountRaw = trim((string) ($courseInput['discount_price'] ?? ''));
    if (preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/', $priceRaw) !== 1) {
        throw new InvalidArgumentException('Standard price must be a normal number with at most two decimal places.');
    }
    $price = (float) $priceRaw;
    if ($price < 0 || $price > 10_000_000) {
        throw new InvalidArgumentException('Standard price is outside the permitted range.');
    }
    $discount = null;
    if ($discountRaw !== '') {
        if (preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/', $discountRaw) !== 1) {
            throw new InvalidArgumentException('Discount price must be a normal number with at most two decimal places.');
        }
        $discount = (float) $discountRaw;
        if ($discount < 0 || $discount >= $price) {
            throw new InvalidArgumentException('Discount price must be lower than the standard price.');
        }
    }
    $level = strtolower(trim((string) ($courseInput['level'] ?? 'beginner')));
    if (!in_array($level, ['beginner', 'intermediate', 'advanced'], true)) {
        throw new InvalidArgumentException('Choose a valid course level.');
    }
    $language = $cleanText($courseInput['language'] ?? 'English', 'Course language', 2, 60);
    if (preg_match("/^[\\p{L}\\p{M}\\p{N} .,'()&\\/-]+$/u", $language) !== 1) {
        throw new InvalidArgumentException('Course language contains unsupported characters.');
    }
    $duration = $cleanText($courseInput['duration'] ?? '', 'Course duration', 0, 80, false);
    $thumbnail = trim((string) ($courseInput['thumbnail'] ?? ''));
    if ($thumbnail !== '' && preg_match('#^media/course-thumbnails/[a-f0-9]{40}\.(?:jpg|png|webp)$#', $thumbnail) !== 1) {
        throw new InvalidArgumentException('The course thumbnail reference is invalid.');
    }
    $introVideo = $httpsUrl($courseInput['intro_video_url'] ?? '', 'Introduction video URL');
    $outcomes = $listValues($courseInput['learning_outcomes'] ?? [], 'Learning outcomes');
    $requirements = $listValues($courseInput['requirements'] ?? [], 'Course requirements');
    $audience = $listValues($courseInput['target_audience'] ?? [], 'Target audience');
    $tags = $cleanText($courseInput['tags'] ?? '', 'Tags', 0, 500, false);

    $sectionsInput = $input['sections'] ?? [];
    if (!is_array($sectionsInput)) {
        throw new InvalidArgumentException('Course sections must be a list.');
    }
    if (count($sectionsInput) > 100) {
        throw new InvalidArgumentException('A course may contain at most 100 sections.');
    }
    $sections = [];
    $totalLessons = 0;
    foreach (array_values($sectionsInput) as $sectionIndex => $sectionInput) {
        if (!is_array($sectionInput)) {
            throw new InvalidArgumentException('Each course section must be an object.');
        }
        $sectionTitle = $cleanText($sectionInput['title'] ?? '', 'Section ' . ($sectionIndex + 1) . ' title', 2, 180);
        $lessonsInput = $sectionInput['lessons'] ?? [];
        if (!is_array($lessonsInput)) {
            throw new InvalidArgumentException('Lessons inside section ' . ($sectionIndex + 1) . ' must be a list.');
        }
        if (count($lessonsInput) > 100) {
            throw new InvalidArgumentException('Each section may contain at most 100 lessons.');
        }
        $lessons = [];
        foreach (array_values($lessonsInput) as $lessonIndex => $lessonInput) {
            $totalLessons++;
            if ($totalLessons > 500) {
                throw new InvalidArgumentException('A course may contain at most 500 lessons.');
            }
            if (!is_array($lessonInput)) {
                throw new InvalidArgumentException('Each lesson must be an object.');
            }
            $label = 'Section ' . ($sectionIndex + 1) . ', lesson ' . ($lessonIndex + 1);
            $lessonTitle = $cleanText($lessonInput['title'] ?? '', $label . ' title', 2, 180);
            $contentType = strtolower(trim((string) ($lessonInput['content_type'] ?? 'text')));
            if (!in_array($contentType, ['text', 'word', 'video', 'pdf', 'audio', 'image', 'link'], true)) {
                throw new InvalidArgumentException($label . ' has an unsupported content type.');
            }
            $duration = filter_var($lessonInput['duration_minutes'] ?? 0, FILTER_VALIDATE_INT);
            if ($duration === false || $duration < 0 || $duration > 10_000) {
                throw new InvalidArgumentException($label . ' duration must be between 0 and 10000 minutes.');
            }
            $contentText = $cleanText($lessonInput['content_text'] ?? '', $label . ' content', 0, 200_000, false, true);
            $contentUrl = trim((string) ($lessonInput['content_url'] ?? ''));
            $contentName = $cleanText($lessonInput['content_name'] ?? '', $label . ' resource name', 0, 255, false);
            if (in_array($contentType, ['text', 'word'], true)) {
                if ($submission && $contentText === '') {
                    throw new InvalidArgumentException($label . ' needs written lesson content.');
                }
                $contentUrl = '';
            } elseif ($contentType === 'link') {
                $contentUrl = $httpsUrl($contentUrl, $label . ' resource URL', $submission);
                $contentText = '';
            } else {
                $patterns = [
                    'video' => '#^private/course-content/[a-f0-9]{40}\.(?:mp4|webm|mov)$#',
                    'pdf' => '#^private/course-content/[a-f0-9]{40}\.pdf$#',
                    'audio' => '#^private/course-content/[a-f0-9]{40}\.(?:mp3|wav|ogg|m4a)$#',
                    'image' => '#^private/course-content/[a-f0-9]{40}\.(?:jpg|png|webp)$#',
                ];
                if ($contentUrl !== '' && preg_match($patterns[$contentType], $contentUrl) !== 1) {
                    throw new InvalidArgumentException($label . ' contains an invalid uploaded resource.');
                }
                if ($submission && $contentUrl === '') {
                    throw new InvalidArgumentException($label . ' requires the selected ' . $contentType . ' file.');
                }
                $contentText = '';
            }
            $lessons[] = [
                'title' => $lessonTitle,
                'content_type' => $contentType,
                'content_url' => $contentUrl,
                'content_name' => $contentName,
                'content_text' => $contentText,
                'duration_minutes' => (int) $duration,
                'is_preview' => filter_var($lessonInput['is_preview'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => $lessonIndex + 1,
            ];
        }
        $sections[] = [
            'title' => $sectionTitle,
            'sort_order' => $sectionIndex + 1,
            'lessons' => $lessons,
        ];
    }

    if ($submission) {
        if ($subtitle === '') {
            throw new InvalidArgumentException('Add a course subtitle before submitting for review.');
        }
        if ($outcomes === []) {
            throw new InvalidArgumentException('Add at least one learning outcome before submitting for review.');
        }
        if ($requirements === []) {
            throw new InvalidArgumentException('Add at least one course requirement before submitting for review.');
        }
        if ($audience === []) {
            throw new InvalidArgumentException('Add at least one target audience group before submitting for review.');
        }
        if ($thumbnail === '') {
            throw new InvalidArgumentException('Upload a course thumbnail before submitting for review.');
        }
        if ($sections === [] || $totalLessons < 1) {
            throw new InvalidArgumentException('Add at least one section and one lesson before submitting for review.');
        }
    }

    return [
        'course' => [
            'title' => $title,
            'subtitle' => $subtitle,
            'short_description' => $shortDescription,
            'full_description' => $fullDescription,
            'learning_outcomes' => $outcomes,
            'requirements' => $requirements,
            'target_audience' => $audience,
            'tags' => $tags,
            'thumbnail' => $thumbnail,
            'intro_video_url' => $introVideo,
            'category_id' => (int) $categoryId,
            'price' => number_format($price, 2, '.', ''),
            'discount_price' => $discount !== null ? number_format($discount, 2, '.', '') : null,
            'level' => $level,
            'language' => $language,
            'duration' => $duration,
        ],
        'sections' => $sections,
    ];
};

$snapshot = static function (PDO $database, int $courseId): array {
    $courseStatement = $database->prepare(
        'SELECT id,instructor_id,category_id,title,subtitle,short_description,full_description,learning_outcomes,requirements,target_audience,tags,thumbnail,intro_video_url,price,discount_price,level,language,duration,status,content_version,edit_permission_status,review_note '
        . 'FROM courses WHERE id=:id LIMIT 1'
    );
    $courseStatement->execute(['id' => $courseId]);
    $course = $courseStatement->fetch();
    if (!is_array($course)) {
        throw new InvalidArgumentException('Course not found.');
    }
    foreach (['learning_outcomes', 'requirements', 'target_audience'] as $field) {
        $decoded = json_decode((string) ($course[$field] ?? '[]'), true);
        $course[$field] = is_array($decoded) ? array_values($decoded) : [];
    }
    $sectionsStatement = $database->prepare('SELECT id,title,sort_order FROM course_sections WHERE course_id=:course_id ORDER BY sort_order,id');
    $sectionsStatement->execute(['course_id' => $courseId]);
    $sections = $sectionsStatement->fetchAll();
    $lessonsStatement = $database->prepare('SELECT id,title,content_type,content_url,content_name,content_text,duration_minutes,is_preview,sort_order FROM course_lessons WHERE section_id=:section_id ORDER BY sort_order,id');
    foreach ($sections as &$section) {
        $lessonsStatement->execute(['section_id' => (int) $section['id']]);
        $section['lessons'] = $lessonsStatement->fetchAll();
    }
    unset($section);
    return ['course' => $course, 'sections' => $sections];
};

$publicSnapshot = static function (array $snapshot): array {
    $course = (array) ($snapshot['course'] ?? []);
    unset($course['id'], $course['instructor_id'], $course['status'], $course['content_version'], $course['edit_permission_status'], $course['review_note']);
    $sections = [];
    foreach ((array) ($snapshot['sections'] ?? []) as $sectionIndex => $section) {
        $lessons = [];
        foreach ((array) ($section['lessons'] ?? []) as $lessonIndex => $lesson) {
            $lessons[] = [
                'title' => (string) ($lesson['title'] ?? ''),
                'content_type' => (string) ($lesson['content_type'] ?? 'text'),
                'content_url' => (string) ($lesson['content_url'] ?? ''),
                'content_name' => (string) ($lesson['content_name'] ?? ''),
                'content_text' => (string) ($lesson['content_text'] ?? ''),
                'duration_minutes' => (int) ($lesson['duration_minutes'] ?? 0),
                'is_preview' => (bool) ($lesson['is_preview'] ?? false),
                'sort_order' => $lessonIndex + 1,
            ];
        }
        $sections[] = ['title' => (string) ($section['title'] ?? ''), 'sort_order' => $sectionIndex + 1, 'lessons' => $lessons];
    }
    return ['course' => $course, 'sections' => $sections];
};

$storedFiles = static function (array $snapshot): array {
    $files = [];
    $thumbnail = trim((string) ($snapshot['course']['thumbnail'] ?? ''));
    if ($thumbnail !== '') {
        $files[$thumbnail] = true;
    }
    foreach ((array) ($snapshot['sections'] ?? []) as $section) {
        foreach ((array) ($section['lessons'] ?? []) as $lesson) {
            $pathValue = trim((string) ($lesson['content_url'] ?? ''));
            if (str_starts_with($pathValue, 'private/course-content/')) {
                $files[$pathValue] = true;
            }
        }
    }
    return array_keys($files);
};

$diffSnapshots = static function (array $before, array $after): array {
    $changes = [];
    $labels = [
        'title' => 'Course title', 'subtitle' => 'Course subtitle', 'short_description' => 'Short description',
        'full_description' => 'Full description', 'learning_outcomes' => 'Learning outcomes',
        'requirements' => 'Requirements', 'target_audience' => 'Target audience', 'tags' => 'Tags',
        'thumbnail' => 'Course thumbnail', 'intro_video_url' => 'Introduction video', 'category_id' => 'Category',
        'price' => 'Standard price', 'discount_price' => 'Discount price', 'level' => 'Course level',
        'language' => 'Course language', 'duration' => 'Course duration',
    ];
    foreach ($labels as $field => $label) {
        $left = $before['course'][$field] ?? null;
        $right = $after['course'][$field] ?? null;
        if (json_encode($left) !== json_encode($right)) {
            $changes[] = ['path' => $label, 'before' => $left, 'after' => $right];
        }
    }
    $beforeSections = array_values((array) ($before['sections'] ?? []));
    $afterSections = array_values((array) ($after['sections'] ?? []));
    $sectionCount = max(count($beforeSections), count($afterSections));
    for ($sectionIndex = 0; $sectionIndex < $sectionCount; $sectionIndex++) {
        $oldSection = $beforeSections[$sectionIndex] ?? null;
        $newSection = $afterSections[$sectionIndex] ?? null;
        $sectionPath = 'Section ' . ($sectionIndex + 1);
        if ($oldSection === null || $newSection === null) {
            $changes[] = ['path' => $sectionPath, 'before' => $oldSection, 'after' => $newSection];
            continue;
        }
        if ((string) ($oldSection['title'] ?? '') !== (string) ($newSection['title'] ?? '')) {
            $changes[] = ['path' => $sectionPath . ' title', 'before' => $oldSection['title'] ?? '', 'after' => $newSection['title'] ?? ''];
        }
        $oldLessons = array_values((array) ($oldSection['lessons'] ?? []));
        $newLessons = array_values((array) ($newSection['lessons'] ?? []));
        $lessonCount = max(count($oldLessons), count($newLessons));
        for ($lessonIndex = 0; $lessonIndex < $lessonCount; $lessonIndex++) {
            $oldLesson = $oldLessons[$lessonIndex] ?? null;
            $newLesson = $newLessons[$lessonIndex] ?? null;
            $lessonPath = $sectionPath . ' / Lesson ' . ($lessonIndex + 1);
            if ($oldLesson === null || $newLesson === null) {
                $changes[] = ['path' => $lessonPath, 'before' => $oldLesson, 'after' => $newLesson];
                continue;
            }
            foreach (['title', 'content_type', 'content_url', 'content_name', 'content_text', 'duration_minutes', 'is_preview'] as $field) {
                if (json_encode($oldLesson[$field] ?? null) !== json_encode($newLesson[$field] ?? null)) {
                    $changes[] = ['path' => $lessonPath . ' ' . str_replace('_', ' ', $field), 'before' => $oldLesson[$field] ?? null, 'after' => $newLesson[$field] ?? null];
                }
            }
        }
    }
    return array_slice($changes, 0, 500);
};

$studentSummary = static function (array $changes): string {
    if ($changes === []) {
        return 'No visible course content changed.';
    }
    $paths = array_values(array_unique(array_map(static fn (array $change): string => (string) ($change['path'] ?? 'Course content'), $changes)));
    $visible = array_slice($paths, 0, 4);
    $summary = 'Updated: ' . implode(', ', $visible);
    if (count($paths) > count($visible)) {
        $summary .= ' and ' . (count($paths) - count($visible)) . ' more item(s)';
    }
    return mb_substr($summary . '.', 0, 1000);
};

$assertCategory = static function (PDO $database, int $categoryId): void {
    $category = $database->prepare('SELECT id FROM categories WHERE id=:id AND status=\'active\' LIMIT 1');
    $category->execute(['id' => $categoryId]);
    if ($category->fetch() === false) {
        throw new InvalidArgumentException('The selected category is unavailable.');
    }
};

$slugify = static function (string $title): string {
    $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
    return substr($slug !== '' ? $slug : 'course', 0, 170);
};

$uniqueSlug = static function (PDO $database, string $title, int $excludeId = 0) use ($slugify): string {
    $base = $slugify($title);
    $slug = $base;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $statement = $database->prepare('SELECT id FROM courses WHERE slug=:slug AND id<>:exclude_id LIMIT 1');
        $statement->execute(['slug' => $slug, 'exclude_id' => $excludeId]);
        if ($statement->fetch() === false) {
            return $slug;
        }
        $slug = $base . '-' . bin2hex(random_bytes(2));
    }
    throw new RuntimeException('A unique course URL could not be created.');
};

$replaceCurriculum = static function (PDO $database, int $courseId, array $sections): void {
    $database->prepare('DELETE FROM course_sections WHERE course_id=:course_id')->execute(['course_id' => $courseId]);
    $sectionInsert = $database->prepare('INSERT INTO course_sections (course_id,title,sort_order) VALUES (:course_id,:title,:sort_order)');
    $lessonInsert = $database->prepare(
        'INSERT INTO course_lessons (section_id,title,content_type,content_url,content_name,content_text,duration_minutes,is_preview,sort_order) '
        . 'VALUES (:section_id,:title,:content_type,:content_url,:content_name,:content_text,:duration_minutes,:is_preview,:sort_order)'
    );
    foreach (array_values($sections) as $sectionIndex => $section) {
        $sectionInsert->execute(['course_id' => $courseId, 'title' => $section['title'], 'sort_order' => $sectionIndex + 1]);
        $sectionId = (int) $database->lastInsertId();
        foreach (array_values((array) ($section['lessons'] ?? [])) as $lessonIndex => $lesson) {
            $lessonInsert->execute([
                'section_id' => $sectionId,
                'title' => $lesson['title'],
                'content_type' => $lesson['content_type'],
                'content_url' => $lesson['content_url'] !== '' ? $lesson['content_url'] : null,
                'content_name' => $lesson['content_name'] !== '' ? $lesson['content_name'] : null,
                'content_text' => $lesson['content_text'] !== '' ? $lesson['content_text'] : null,
                'duration_minutes' => (int) $lesson['duration_minutes'],
                'is_preview' => !empty($lesson['is_preview']) ? 1 : 0,
                'sort_order' => $lessonIndex + 1,
            ]);
        }
    }
};

$updateLiveCourse = static function (PDO $database, int $courseId, int $instructorId, array $snapshotData, string $status, string $slug) use ($replaceCurriculum): void {
    $course = $snapshotData['course'];
    $statement = $database->prepare(
        'UPDATE courses SET category_id=:category_id,title=:title,subtitle=:subtitle,slug=:slug,short_description=:short_description,full_description=:full_description,'
        . 'learning_outcomes=:learning_outcomes,requirements=:requirements,target_audience=:target_audience,tags=:tags,thumbnail=:thumbnail,intro_video_url=:intro_video_url,'
        . 'price=:price,discount_price=:discount_price,level=:level,language=:language,duration=:duration,status=:status,'
        . 'submitted_at=IF(:submitted_status=\'pending\',NOW(),NULL),reviewed_at=NULL,reviewed_by=NULL,review_note=NULL '
        . 'WHERE id=:id AND instructor_id=:instructor_id'
    );
    $statement->execute([
        'category_id' => $course['category_id'], 'title' => $course['title'], 'subtitle' => $course['subtitle'] !== '' ? $course['subtitle'] : null,
        'slug' => $slug, 'short_description' => $course['short_description'], 'full_description' => $course['full_description'],
        'learning_outcomes' => json_encode($course['learning_outcomes'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        'requirements' => json_encode($course['requirements'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        'target_audience' => json_encode($course['target_audience'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        'tags' => $course['tags'] !== '' ? $course['tags'] : null, 'thumbnail' => $course['thumbnail'] !== '' ? $course['thumbnail'] : null,
        'intro_video_url' => $course['intro_video_url'] !== '' ? $course['intro_video_url'] : null,
        'price' => $course['price'], 'discount_price' => $course['discount_price'], 'level' => $course['level'], 'language' => $course['language'],
        'duration' => $course['duration'] !== '' ? $course['duration'] : null, 'status' => $status, 'submitted_status' => $status,
        'id' => $courseId, 'instructor_id' => $instructorId,
    ]);
    $replaceCurriculum($database, $courseId, $snapshotData['sections']);
};

$notifyAdmins = static function (PDO $database, string $title, string $message, string $type): void {
    $statement = $database->prepare(
        'INSERT INTO notifications (user_id,title,message,notification_type) '
        . 'SELECT id,:title,:message,:type FROM users WHERE role=\'admin\' AND status=\'active\''
    );
    $statement->execute(['title' => $title, 'message' => mb_substr($message, 0, 1000), 'type' => $type]);
};

$notifyUser = static function (PDO $database, int $userId, string $title, string $message, string $type): void {
    $statement = $database->prepare('INSERT INTO notifications (user_id,title,message,notification_type) VALUES (:user_id,:title,:message,:type)');
    $statement->execute(['user_id' => $userId, 'title' => $title, 'message' => mb_substr($message, 0, 1000), 'type' => $type]);
};

$database = null;
try {
    $database = Database::connect();

    if ($path === '/api/v1/courses/mine' && $method === 'GET') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $statement = $database->prepare(
            'SELECT c.id,c.title,c.subtitle,c.slug,c.short_description,c.price,c.discount_price,c.level,c.language,c.duration,c.status,c.review_note,c.thumbnail,'
            . 'c.edit_permission_status,c.edit_permission_reason,c.edit_permission_note,c.content_version,c.updated_at,cat.name AS category_name,'
            . '(SELECT cr.revision_status FROM course_revisions cr WHERE cr.course_id=c.id AND cr.revision_status IN (\'draft\',\'pending\') ORDER BY cr.id DESC LIMIT 1) AS revision_status '
            . 'FROM courses c LEFT JOIN categories cat ON cat.id=c.category_id WHERE c.instructor_id=:instructor_id ORDER BY c.updated_at DESC'
        );
        $statement->execute(['instructor_id' => $instructor['id']]);
        $respond(['data' => $statement->fetchAll()]);
    }

    if ($path === '/api/v1/courses/authoring' && $method === 'POST') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $input = $jsonInput();
        $action = strtolower(trim((string) ($input['action'] ?? 'draft')));
        if (!in_array($action, ['draft', 'submit'], true)) {
            throw new InvalidArgumentException('Choose Save draft or Submit for review.');
        }
        $snapshotData = $normaliseSnapshot($input, $action === 'submit');
        $assertCategory($database, (int) $snapshotData['course']['category_id']);
        $database->beginTransaction();
        $slug = $uniqueSlug($database, (string) $snapshotData['course']['title']);
        $course = $snapshotData['course'];
        $status = $action === 'submit' ? 'pending' : 'draft';
        $insert = $database->prepare(
            'INSERT INTO courses (instructor_id,category_id,title,subtitle,slug,short_description,full_description,learning_outcomes,requirements,target_audience,tags,thumbnail,intro_video_url,price,discount_price,level,language,duration,status,submitted_at) '
            . 'VALUES (:instructor_id,:category_id,:title,:subtitle,:slug,:short_description,:full_description,:learning_outcomes,:requirements,:target_audience,:tags,:thumbnail,:intro_video_url,:price,:discount_price,:level,:language,:duration,:status,IF(:submitted_status=\'pending\',NOW(),NULL))'
        );
        $insert->execute([
            'instructor_id' => $instructor['id'], 'category_id' => $course['category_id'], 'title' => $course['title'], 'subtitle' => $course['subtitle'] !== '' ? $course['subtitle'] : null,
            'slug' => $slug, 'short_description' => $course['short_description'], 'full_description' => $course['full_description'],
            'learning_outcomes' => json_encode($course['learning_outcomes'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'requirements' => json_encode($course['requirements'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'target_audience' => json_encode($course['target_audience'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'tags' => $course['tags'] !== '' ? $course['tags'] : null, 'thumbnail' => $course['thumbnail'] !== '' ? $course['thumbnail'] : null,
            'intro_video_url' => $course['intro_video_url'] !== '' ? $course['intro_video_url'] : null,
            'price' => $course['price'], 'discount_price' => $course['discount_price'], 'level' => $course['level'], 'language' => $course['language'],
            'duration' => $course['duration'] !== '' ? $course['duration'] : null, 'status' => $status, 'submitted_status' => $status,
        ]);
        $courseId = (int) $database->lastInsertId();
        $replaceCurriculum($database, $courseId, $snapshotData['sections']);
        if ($status === 'pending') {
            $notifyAdmins($database, 'Course awaiting approval', $instructor['name'] . ' submitted "' . $course['title'] . '" for review.', 'course_review');
        }
        $database->commit();
        $respond(['message' => $status === 'pending' ? 'Complete course submitted for Admin review.' : 'Complete course saved as a private draft.', 'id' => $courseId, 'status' => $status], 201);
    }

    if (preg_match('#^/api/v1/courses/(\d+)/authoring$#', $path, $matches) === 1 && $method === 'GET') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $courseId = (int) $matches[1];
        $live = $snapshot($database, $courseId);
        if ((int) ($live['course']['instructor_id'] ?? 0) !== (int) $instructor['id']) {
            throw new ServiceAuthorizationException('That course is not available in your Instructor studio.');
        }
        $payload = $publicSnapshot($live);
        $payload['meta'] = [
            'id' => $courseId,
            'status' => (string) $live['course']['status'],
            'content_version' => (int) ($live['course']['content_version'] ?? 1),
            'edit_permission_status' => (string) ($live['course']['edit_permission_status'] ?? 'none'),
            'review_note' => (string) ($live['course']['review_note'] ?? ''),
            'revision_status' => '',
        ];
        if ((string) $live['course']['status'] === 'published') {
            $revision = $database->prepare('SELECT id,revision_snapshot,revision_status,review_note FROM course_revisions WHERE course_id=:course_id AND revision_status IN (\'draft\',\'pending\') ORDER BY id DESC LIMIT 1');
            $revision->execute(['course_id' => $courseId]);
            $record = $revision->fetch();
            if (is_array($record)) {
                $decoded = json_decode((string) $record['revision_snapshot'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
                $payload['meta'] = [
                    'id' => $courseId, 'status' => 'published', 'content_version' => (int) ($live['course']['content_version'] ?? 1),
                    'edit_permission_status' => (string) ($live['course']['edit_permission_status'] ?? 'none'),
                    'revision_id' => (int) $record['id'], 'revision_status' => (string) $record['revision_status'], 'review_note' => (string) ($record['review_note'] ?? ''),
                ];
            }
        }
        $respond(['data' => $payload]);
    }

    if (preg_match('#^/api/v1/courses/(\d+)/authoring$#', $path, $matches) === 1 && $method === 'POST') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $courseId = (int) $matches[1];
        $input = $jsonInput();
        $action = strtolower(trim((string) ($input['action'] ?? 'draft')));
        if (!in_array($action, ['draft', 'submit'], true)) {
            throw new InvalidArgumentException('Choose Save draft or Submit for review.');
        }
        $snapshotData = $normaliseSnapshot($input, $action === 'submit');
        $assertCategory($database, (int) $snapshotData['course']['category_id']);
        $database->beginTransaction();
        $lock = $database->prepare('SELECT id,instructor_id,title,status,edit_permission_status FROM courses WHERE id=:id FOR UPDATE');
        $lock->execute(['id' => $courseId]);
        $courseRecord = $lock->fetch();
        if (!is_array($courseRecord) || (int) $courseRecord['instructor_id'] !== (int) $instructor['id']) {
            throw new ServiceAuthorizationException('That course is not available in your Instructor studio.');
        }
        $status = (string) $courseRecord['status'];
        if ($status === 'pending') {
            throw new ServiceAuthorizationException('A course under Admin review is locked.');
        }
        if ($status === 'archived') {
            throw new ServiceAuthorizationException('An archived course cannot be edited.');
        }
        $live = $publicSnapshot($snapshot($database, $courseId));
        $changes = $diffSnapshots($live, $snapshotData);
        if ($changes === []) {
            throw new InvalidArgumentException('No course changes were detected.');
        }

        if ($status === 'published') {
            if ((string) $courseRecord['edit_permission_status'] !== 'granted') {
                throw new ServiceAuthorizationException('Admin edit permission is required before changing a published course.');
            }
            $existing = $database->prepare('SELECT id,revision_status FROM course_revisions WHERE course_id=:course_id AND revision_status IN (\'draft\',\'pending\') ORDER BY id DESC LIMIT 1 FOR UPDATE');
            $existing->execute(['course_id' => $courseId]);
            $revision = $existing->fetch();
            if (is_array($revision) && (string) $revision['revision_status'] === 'pending') {
                throw new ServiceAuthorizationException('The submitted course revision is locked until Admin reviews it.');
            }
            $revisionStatus = $action === 'submit' ? 'pending' : 'draft';
            $summary = $studentSummary($changes);
            if (is_array($revision)) {
                $updateRevision = $database->prepare('UPDATE course_revisions SET revision_snapshot=:snapshot,change_summary=:changes,student_summary=:student_summary,revision_status=:status,review_note=NULL,reviewed_by=NULL,reviewed_at=NULL WHERE id=:id AND revision_status=\'draft\'');
                $updateRevision->execute([
                    'snapshot' => json_encode($snapshotData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'changes' => json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'student_summary' => $summary, 'status' => $revisionStatus, 'id' => (int) $revision['id'],
                ]);
                $revisionId = (int) $revision['id'];
            } else {
                $insertRevision = $database->prepare('INSERT INTO course_revisions (course_id,instructor_id,revision_snapshot,change_summary,student_summary,revision_status) VALUES (:course_id,:instructor_id,:snapshot,:changes,:student_summary,:status)');
                $insertRevision->execute([
                    'course_id' => $courseId, 'instructor_id' => $instructor['id'],
                    'snapshot' => json_encode($snapshotData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'changes' => json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'student_summary' => $summary, 'status' => $revisionStatus,
                ]);
                $revisionId = (int) $database->lastInsertId();
            }
            if ($revisionStatus === 'pending') {
                $database->prepare('UPDATE courses SET edit_permission_status=\'none\' WHERE id=:id')->execute(['id' => $courseId]);
                $notifyAdmins($database, 'Published course revision awaiting review', $instructor['name'] . ' submitted changes to "' . $courseRecord['title'] . '".', 'course_revision');
            }
            $database->commit();
            $respond([
                'message' => $revisionStatus === 'pending' ? 'Published-course changes submitted without altering the live course.' : 'Published-course changes saved as a private revision draft.',
                'id' => $courseId, 'revision_id' => $revisionId, 'status' => 'published', 'revision_status' => $revisionStatus,
            ]);
        }

        if (!in_array($status, ['draft', 'rejected'], true)) {
            throw new ServiceAuthorizationException('This course state cannot be edited.');
        }
        $beforeFiles = $storedFiles($live);
        $afterFiles = $storedFiles($snapshotData);
        $newStatus = $action === 'submit' ? 'pending' : 'draft';
        $slug = $uniqueSlug($database, (string) $snapshotData['course']['title'], $courseId);
        $updateLiveCourse($database, $courseId, (int) $instructor['id'], $snapshotData, $newStatus, $slug);
        if ($newStatus === 'pending') {
            $notifyAdmins($database, 'Course awaiting approval', $instructor['name'] . ' submitted "' . $snapshotData['course']['title'] . '" for review.', 'course_review');
        }
        $database->commit();
        $respond([
            'message' => $newStatus === 'pending' ? 'Complete course submitted for Admin review.' : 'Complete course draft updated.',
            'id' => $courseId, 'status' => $newStatus, 'retired_files' => array_values(array_diff($beforeFiles, $afterFiles)),
        ]);
    }

    if (preg_match('#^/api/v1/courses/(\d+)/edit-permission/request$#', $path, $matches) === 1 && $method === 'POST') {
        $instructor = ServiceAuth::requireUser($database, $authorization, 'instructor');
        $input = $jsonInput();
        $reason = $cleanText($input['reason'] ?? '', 'Edit-permission reason', 20, 1000, true, true);
        $statement = $database->prepare(
            'UPDATE courses SET edit_permission_status=\'requested\',edit_permission_reason=:reason,edit_permission_requested_at=NOW(),edit_permission_reviewed_by=NULL,edit_permission_reviewed_at=NULL,edit_permission_note=NULL '
            . 'WHERE id=:id AND instructor_id=:instructor_id AND status=\'published\' AND edit_permission_status IN (\'none\',\'denied\')'
        );
        $statement->execute(['reason' => $reason, 'id' => (int) $matches[1], 'instructor_id' => $instructor['id']]);
        if ($statement->rowCount() !== 1) {
            throw new ServiceAuthorizationException('This published course cannot receive another edit request right now.');
        }
        $notifyAdmins($database, 'Published course edit requested', $instructor['name'] . ' requested permission to edit a published course.', 'course_edit_permission');
        $respond(['message' => 'Edit permission requested. The live course remains locked.']);
    }

    if ($path === '/api/v1/courses/edit-permissions' && $method === 'GET') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $statement = $database->query(
            'SELECT c.id,c.title,c.edit_permission_reason,c.edit_permission_requested_at,u.full_name AS instructor_name,u.email AS instructor_email '
            . 'FROM courses c INNER JOIN users u ON u.id=c.instructor_id WHERE c.status=\'published\' AND c.edit_permission_status=\'requested\' ORDER BY c.edit_permission_requested_at ASC'
        );
        $respond(['data' => $statement->fetchAll()]);
    }

    if (preg_match('#^/api/v1/courses/(\d+)/edit-permission/(grant|deny)$#', $path, $matches) === 1 && $method === 'POST') {
        $admin = ServiceAuth::requireUser($database, $authorization, 'admin');
        $input = $jsonInput();
        $note = $cleanText($input['note'] ?? '', 'Edit-permission note', 0, 1000, false, true);
        $decision = $matches[2];
        $database->beginTransaction();
        $course = $database->prepare('SELECT id,title,instructor_id FROM courses WHERE id=:id AND status=\'published\' AND edit_permission_status=\'requested\' FOR UPDATE');
        $course->execute(['id' => (int) $matches[1]]);
        $record = $course->fetch();
        if (!is_array($record)) {
            throw new ServiceAuthorizationException('The edit-permission request is no longer pending.');
        }
        $statusValue = $decision === 'grant' ? 'granted' : 'denied';
        $database->prepare('UPDATE courses SET edit_permission_status=:status,edit_permission_reviewed_by=:admin_id,edit_permission_reviewed_at=NOW(),edit_permission_note=:note WHERE id=:id')->execute([
            'status' => $statusValue, 'admin_id' => $admin['id'], 'note' => $note !== '' ? $note : null, 'id' => (int) $record['id'],
        ]);
        $notifyUser($database, (int) $record['instructor_id'], $decision === 'grant' ? 'Course editing approved' : 'Course editing request denied',
            $decision === 'grant'
                ? 'You may now create a private revision for "' . $record['title'] . '". The live version stays unchanged until the revision is approved.'
                : 'Your request to edit "' . $record['title'] . '" was denied. ' . $note,
            'course_edit_permission');
        $database->commit();
        $respond(['message' => $decision === 'grant' ? 'Instructor may now prepare a private revision.' : 'Edit request denied.']);
    }

    if ($path === '/api/v1/courses/revisions/pending' && $method === 'GET') {
        ServiceAuth::requireUser($database, $authorization, 'admin');
        $statement = $database->query(
            'SELECT cr.id,cr.course_id,cr.revision_snapshot,cr.change_summary,cr.student_summary,cr.created_at,c.title,u.full_name AS instructor_name,u.email AS instructor_email,c.content_version '
            . 'FROM course_revisions cr INNER JOIN courses c ON c.id=cr.course_id INNER JOIN users u ON u.id=cr.instructor_id '
            . 'WHERE cr.revision_status=\'pending\' ORDER BY cr.created_at ASC'
        );
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['revision_snapshot'] = json_decode((string) $row['revision_snapshot'], true) ?: [];
            $row['change_summary'] = json_decode((string) $row['change_summary'], true) ?: [];
        }
        unset($row);
        $respond(['data' => $rows]);
    }

    if (preg_match('#^/api/v1/courses/revisions/(\d+)/(approve|reject)$#', $path, $matches) === 1 && $method === 'POST') {
        $admin = ServiceAuth::requireUser($database, $authorization, 'admin');
        $input = $jsonInput();
        $note = $cleanText($input['note'] ?? '', 'Revision review note', 0, 1000, false, true);
        $decision = $matches[2];
        if ($decision === 'reject' && $note === '') {
            throw new InvalidArgumentException('A revision rejection reason is required.');
        }
        $database->beginTransaction();
        $statement = $database->prepare('SELECT * FROM course_revisions WHERE id=:id AND revision_status=\'pending\' FOR UPDATE');
        $statement->execute(['id' => (int) $matches[1]]);
        $revision = $statement->fetch();
        if (!is_array($revision)) {
            throw new ServiceAuthorizationException('The course revision is no longer pending.');
        }
        $liveFull = $snapshot($database, (int) $revision['course_id']);
        $live = $publicSnapshot($liveFull);
        $revisionSnapshot = json_decode((string) $revision['revision_snapshot'], true);
        $changes = json_decode((string) $revision['change_summary'], true);
        if (!is_array($revisionSnapshot) || !is_array($changes)) {
            throw new RuntimeException('The stored course revision is unreadable.');
        }
        $liveFiles = $storedFiles($live);
        $revisionFiles = $storedFiles($revisionSnapshot);

        if ($decision === 'reject') {
            $database->prepare('UPDATE course_revisions SET revision_status=\'rejected\',review_note=:note,reviewed_by=:admin_id,reviewed_at=NOW() WHERE id=:id')->execute([
                'note' => $note, 'admin_id' => $admin['id'], 'id' => (int) $revision['id'],
            ]);
            $database->prepare('UPDATE courses SET edit_permission_status=\'denied\',edit_permission_note=:note WHERE id=:id')->execute(['note' => $note, 'id' => (int) $revision['course_id']]);
            $notifyUser($database, (int) $revision['instructor_id'], 'Course revision needs changes', 'Your published-course revision was rejected. ' . $note, 'course_revision');
            $database->commit();
            $respond(['message' => 'Revision rejected. The live course was not changed.', 'discarded_files' => array_values(array_diff($revisionFiles, $liveFiles))]);
        }

        $courseRecord = $liveFull['course'];
        $assertCategory($database, (int) $revisionSnapshot['course']['category_id']);
        $slug = $uniqueSlug($database, (string) $revisionSnapshot['course']['title'], (int) $revision['course_id']);
        $updateLiveCourse($database, (int) $revision['course_id'], (int) $revision['instructor_id'], $revisionSnapshot, 'published', $slug);
        $version = (int) ($courseRecord['content_version'] ?? 1) + 1;
        $database->prepare('UPDATE courses SET status=\'published\',content_version=:version,edit_permission_status=\'none\',edit_permission_reason=NULL,edit_permission_note=NULL,reviewed_by=:admin_id,reviewed_at=NOW(),review_note=:note WHERE id=:id')->execute([
            'version' => $version, 'admin_id' => $admin['id'], 'note' => $note !== '' ? $note : null, 'id' => (int) $revision['course_id'],
        ]);
        $database->prepare('UPDATE course_revisions SET revision_status=\'approved\',review_note=:note,reviewed_by=:admin_id,reviewed_at=NOW() WHERE id=:id')->execute([
            'note' => $note !== '' ? $note : null, 'admin_id' => $admin['id'], 'id' => (int) $revision['id'],
        ]);
        $database->prepare(
            'INSERT INTO course_change_logs (course_id,instructor_id,change_type,version_number,before_snapshot,after_snapshot,change_summary,student_summary,previous_status,new_status,reviewed_by,reviewed_at) '
            . 'VALUES (:course_id,:instructor_id,\'approved_revision\',:version,:before_snapshot,:after_snapshot,:change_summary,:student_summary,\'published\',\'published\',:reviewed_by,NOW())'
        )->execute([
            'course_id' => (int) $revision['course_id'], 'instructor_id' => (int) $revision['instructor_id'], 'version' => $version,
            'before_snapshot' => json_encode($live, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'after_snapshot' => json_encode($revisionSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'change_summary' => json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'student_summary' => (string) $revision['student_summary'], 'reviewed_by' => $admin['id'],
        ]);
        $notifyUser($database, (int) $revision['instructor_id'], 'Course revision published', 'Your approved changes are now live in "' . $courseRecord['title'] . '".', 'course_revision');
        $studentNotify = $database->prepare(
            'INSERT INTO notifications (user_id,title,message,notification_type) '
            . 'SELECT e.student_id,:title,:message,\'course_updated\' FROM enrollments e WHERE e.course_id=:course_id AND e.status=\'active\''
        );
        $studentNotify->execute([
            'title' => 'A purchased course was updated',
            'message' => '"' . $revisionSnapshot['course']['title'] . '" was updated. Open the course information button to review what changed.',
            'course_id' => (int) $revision['course_id'],
        ]);
        $database->commit();
        $respond(['message' => 'Revision approved and published as version ' . $version . '.', 'retired_files' => array_values(array_diff($liveFiles, $revisionFiles)), 'version' => $version]);
    }

    if (preg_match('#^/api/v1/courses/(\d+)/change-log$#', $path, $matches) === 1 && $method === 'GET') {
        $student = ServiceAuth::requireUser($database, $authorization, 'student');
        $courseId = (int) $matches[1];
        $access = $database->prepare('SELECT id FROM enrollments WHERE student_id=:student_id AND course_id=:course_id AND status=\'active\' LIMIT 1');
        $access->execute(['student_id' => $student['id'], 'course_id' => $courseId]);
        if ($access->fetch() === false) {
            throw new ServiceAuthorizationException('Active enrollment is required to view the course change history.');
        }
        $statement = $database->prepare('SELECT version_number,change_summary,student_summary,reviewed_at,created_at FROM course_change_logs WHERE course_id=:course_id AND change_type=\'approved_revision\' ORDER BY version_number DESC,id DESC LIMIT 50');
        $statement->execute(['course_id' => $courseId]);
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['change_summary'] = json_decode((string) ($row['change_summary'] ?? '[]'), true) ?: [];
        }
        unset($row);
        $respond(['data' => $rows]);
    }

    if (preg_match('#^/api/v1/courses/(\d+)$#', $path) === 1 && in_array($method, ['PUT', 'PATCH'], true)) {
        throw new ServiceAuthorizationException('Use the complete course authoring page. Partial published-course edits are disabled.');
    }

    $respond(['error' => 'Course authoring route not found.'], 404);
} catch (ServiceAuthenticationException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    $respond(['error' => $exception->getMessage()], 401);
} catch (ServiceAuthorizationException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    $respond(['error' => $exception->getMessage()], 403);
} catch (InvalidArgumentException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    $respond(['error' => $exception->getMessage()], 422);
} catch (JsonException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    $respond(['error' => 'The course data contains malformed JSON.'], 400);
} catch (PDOException $exception) {
    if ($database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    error_log('Course authoring database failure: ' . $exception->getMessage());
    $respond(['error' => 'Course authoring could not be completed. Apply the latest database migration and try again.'], 409);
} catch (Throwable $exception) {
    if ($database instanceof PDO && $database->inTransaction()) { $database->rollBack(); }
    error_log('Course authoring failure: ' . $exception->getMessage());
    $respond(['error' => 'The course authoring service is unavailable.'], 503);
}
