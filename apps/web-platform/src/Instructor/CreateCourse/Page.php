<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class CreateCoursePage
{
    public static function render(array $categories, array $values = [], string $message = '', bool $success = false): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $options = '<option value="">Choose category</option>';
        foreach ($categories as $category) {
            $selected = (string) ($values['category_id'] ?? '') === (string) ($category['id'] ?? '') ? ' selected' : '';
            $options .= '<option value="' . (int) ($category['id'] ?? 0) . '"' . $selected . '>' . $e($category['name'] ?? '') . '</option>';
        }
        $levelOptions = '';
        foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $value => $label) {
            $selected = ($values['level'] ?? 'beginner') === $value ? ' selected' : '';
            $levelOptions .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $form = $alert . '<form class="portal-form" method="post" action="/instructor/courses/create">' . Csrf::field()
            . '<label>Course title<input name="title" maxlength="180" value="' . $e($values['title'] ?? '') . '" required></label>'
            . '<label>Short description<textarea name="short_description" maxlength="500" rows="3" required>' . $e($values['short_description'] ?? '') . '</textarea></label>'
            . '<label>Full course description<textarea name="full_description" maxlength="50000" rows="10" required>' . $e($values['full_description'] ?? '') . '</textarea></label>'
            . '<div class="form-columns"><label>Category<select name="category_id" required>' . $options . '</select></label><label>Level<select name="level">' . $levelOptions . '</select></label></div>'
            . '<div class="form-columns"><label>Price (NPR)<input type="number" name="price" min="0" max="10000000" step="0.01" value="' . $e($values['price'] ?? '0') . '" required></label><label>Language<input name="language" maxlength="60" value="' . $e($values['language'] ?? 'English') . '" required></label></div>'
            . '<div class="form-columns"><label>Estimated duration<input name="duration" maxlength="80" value="' . $e($values['duration'] ?? '') . '" placeholder="12 hours"></label><label>Thumbnail filename<input name="thumbnail" maxlength="255" value="' . $e($values['thumbnail'] ?? '') . '" placeholder="course-cover.jpg"><small>Secure media upload is handled by the media service.</small></label></div>'
            . '<div class="actions"><button class="portal-button secondary" name="intent" value="draft" type="submit">Save as draft</button><button class="portal-button" name="intent" value="submit" type="submit">Save and submit for approval</button></div></form>';

        return PortalPage::render('instructor', 'Create a course', $form, '<a class="portal-link" href="/instructor/courses">View my courses →</a>');
    }
}
