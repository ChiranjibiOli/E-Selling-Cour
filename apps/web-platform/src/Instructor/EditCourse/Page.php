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
        $status = (string) ($course['status'] ?? 'draft');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        if ($status === 'pending') {
            $content = $alert . '<div class="portal-card"><span class="status-badge pending">Pending approval</span><h2>' . $e($course['title'] ?? 'Course') . '</h2><p>This version is locked while the administrator reviews it. You can continue after it is published or returned with a review note.</p></div>';
            return PortalPage::render('instructor', 'Course review', $content, '<a class="portal-link" href="/instructor/courses">Back to courses →</a>');
        }
        $options = '';
        foreach ($categories as $category) {
            $selected = (int) ($course['category_id'] ?? 0) === (int) ($category['id'] ?? 0) ? ' selected' : '';
            $options .= '<option value="' . (int) ($category['id'] ?? 0) . '"' . $selected . '>' . $e($category['name'] ?? '') . '</option>';
        }
        $levels = '';
        foreach (['beginner', 'intermediate', 'advanced'] as $level) {
            $levels .= '<option value="' . $level . '"' . (($course['level'] ?? '') === $level ? ' selected' : '') . '>' . ucfirst($level) . '</option>';
        }
        $publishedNote = $status === 'published' ? '<div class="form-alert success">Editing a published course sends the new version back for approval.</div>' : '';
        $content = $alert . $publishedNote . '<form class="portal-form" method="post" action="/instructor/courses/edit?id=' . (int) ($course['id'] ?? 0) . '">' . Csrf::field()
            . '<label>Course title<input name="title" maxlength="180" value="' . $e($course['title'] ?? '') . '" required></label>'
            . '<label>Short description<textarea name="short_description" maxlength="500" rows="3" required>' . $e($course['short_description'] ?? '') . '</textarea></label>'
            . '<label>Full description<textarea name="full_description" maxlength="50000" rows="10" required>' . $e($course['full_description'] ?? '') . '</textarea></label>'
            . '<div class="form-columns"><label>Category<select name="category_id" required>' . $options . '</select></label><label>Level<select name="level">' . $levels . '</select></label></div>'
            . '<div class="form-columns"><label>Price (NPR)<input type="number" name="price" min="0" max="10000000" step="0.01" value="' . $e($course['price'] ?? '0') . '" required></label><label>Language<input name="language" maxlength="60" value="' . $e($course['language'] ?? 'English') . '" required></label></div>'
            . '<div class="form-columns"><label>Duration<input name="duration" maxlength="80" value="' . $e($course['duration'] ?? '') . '"></label><label>Thumbnail filename<input name="thumbnail" maxlength="255" value="' . $e($course['thumbnail'] ?? '') . '"></label></div>'
            . '<button class="portal-button" type="submit">Save course changes</button></form>';
        return PortalPage::render('instructor', 'Edit course', $content, '<span class="status-badge ' . $e($status) . '">' . $e($status) . '</span>');
    }
}
