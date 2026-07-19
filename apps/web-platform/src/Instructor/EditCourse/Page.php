<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class EditCoursePage
{
    public static function render(array $course, array $categories, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $listValue = static function (mixed $value): string {
            if (is_array($value)) {
                return implode("\n", array_map('strval', $value));
            }
            if (is_string($value) && $value !== '') {
                try {
                    $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
                    return is_array($decoded) ? implode("\n", array_map('strval', $decoded)) : $value;
                } catch (JsonException) {
                    return $value;
                }
            }
            return '';
        };
        $status = (string) ($course['status'] ?? 'draft');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        if ($status === 'pending') {
            $content = $alert . '<div class="portal-card"><span class="status-badge pending">Pending approval</span><h2>' . $e($course['title'] ?? 'Course') . '</h2><p>This submitted version is locked while an administrator reviews its details and curriculum.</p></div>';
            return PortalPage::render('instructor', 'Course review', $content, '<a class="portal-link" href="/instructor/courses">Back to courses →</a>');
        }
        $options = '<option value="">Choose category</option>';
        foreach ($categories as $category) {
            $selected = (int) ($course['category_id'] ?? 0) === (int) ($category['id'] ?? 0) ? ' selected' : '';
            $options .= '<option value="' . (int) ($category['id'] ?? 0) . '"' . $selected . '>' . $e($category['name'] ?? '') . '</option>';
        }
        $levels = '';
        foreach (['beginner', 'intermediate', 'advanced'] as $level) {
            $levels .= '<option value="' . $level . '"' . (($course['level'] ?? '') === $level ? ' selected' : '') . '>' . ucfirst($level) . '</option>';
        }
        $publishedNote = $status === 'published' ? '<div class="form-alert success">Editing a published course submits the updated version for renewed approval.</div>' : '';
        $content = $alert . $publishedNote . '<form class="portal-form course-authoring-form" method="post" action="/instructor/courses/edit?id=' . (int) ($course['id'] ?? 0) . '">' . Csrf::field()
            . '<section class="data-card"><div class="data-card-head"><div><span>COURSE IDENTITY</span><h3>Public title and positioning</h3></div></div>'
            . '<label>Course title<input name="title" maxlength="180" value="' . $e($course['title'] ?? '') . '" required></label>'
            . '<label>Course subtitle<input name="subtitle" maxlength="240" value="' . $e($course['subtitle'] ?? '') . '"></label>'
            . '<label>Short description<textarea name="short_description" maxlength="500" rows="3" required>' . $e($course['short_description'] ?? '') . '</textarea></label>'
            . '<label>Full description<textarea name="full_description" maxlength="50000" rows="10" required>' . $e($course['full_description'] ?? '') . '</textarea></label></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>LEARNING PROMISE</span><h3>Outcomes, requirements, and audience</h3></div></div>'
            . '<div class="form-columns"><label>Learning outcomes<textarea name="learning_outcomes" rows="7" maxlength="9000" placeholder="One outcome per line">' . $e($listValue($course['learning_outcomes'] ?? '')) . '</textarea></label>'
            . '<label>Course requirements<textarea name="requirements" rows="7" maxlength="9000" placeholder="One requirement per line">' . $e($listValue($course['requirements'] ?? '')) . '</textarea></label></div>'
            . '<label>Target audience<textarea name="target_audience" rows="5" maxlength="9000" placeholder="One audience group per line">' . $e($listValue($course['target_audience'] ?? '')) . '</textarea></label>'
            . '<label>Tags<input name="tags" maxlength="500" value="' . $e($course['tags'] ?? '') . '"></label></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>PRICE AND DELIVERY</span><h3>Commercial and course information</h3></div></div>'
            . '<div class="form-columns"><label>Category<select name="category_id" required>' . $options . '</select></label><label>Level<select name="level">' . $levels . '</select></label></div>'
            . '<div class="form-columns"><label>Standard price (NPR)<input type="number" name="price" min="0" max="10000000" step="0.01" value="' . $e($course['price'] ?? '0') . '" required></label><label>Discount price (NPR)<input type="number" name="discount_price" min="0" max="10000000" step="0.01" value="' . $e($course['discount_price'] ?? '') . '" placeholder="Optional"></label></div>'
            . '<div class="form-columns"><label>Language<input name="language" maxlength="60" value="' . $e($course['language'] ?? 'English') . '" required></label><label>Duration<input name="duration" maxlength="80" value="' . $e($course['duration'] ?? '') . '"></label></div>'
            . '<label>Introduction video URL<input type="url" name="intro_video_url" maxlength="500" value="' . $e($course['intro_video_url'] ?? '') . '"></label>'
            . '<label>Thumbnail filename<input name="thumbnail" maxlength="255" value="' . $e($course['thumbnail'] ?? '') . '"><small>Protected binary media upload remains owned by the media service.</small></label></section>'
            . '<div class="actions course-authoring-actions"><button class="portal-button" type="submit">Save course changes</button><a class="portal-button secondary" href="/instructor/lessons?course=' . (int) ($course['id'] ?? 0) . '">Manage curriculum</a></div></form>';
        return PortalPage::render('instructor', 'Edit course', $content, '<span class="status-badge ' . $e($status) . '">' . $e($status) . '</span>');
    }
}
