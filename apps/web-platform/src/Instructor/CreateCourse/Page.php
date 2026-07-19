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
        $listValue = static fn (mixed $value): string => is_array($value) ? implode("\n", array_map('strval', $value)) : (string) $value;
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
        $form = $alert . '<section class="panel-intro"><div><span>COURSE AUTHORING</span><h2>Describe the complete learning promise before building lessons.</h2><p>You may save an incomplete private draft. Submission requires a subtitle, outcomes, requirements, audience, and at least one curriculum lesson.</p></div><div class="panel-intro-orb"><i></i><strong>01</strong></div></section>'
            . '<form class="portal-form course-authoring-form" method="post" action="/instructor/courses/create">' . Csrf::field()
            . '<section class="data-card"><div class="data-card-head"><div><span>COURSE IDENTITY</span><h3>Public title and positioning</h3></div></div>'
            . '<label>Course title<input name="title" maxlength="180" value="' . $e($values['title'] ?? '') . '" placeholder="Complete Web Application Security" required></label>'
            . '<label>Course subtitle<input name="subtitle" maxlength="240" value="' . $e($values['subtitle'] ?? '') . '" placeholder="Learn practical testing from request mapping to verified findings"></label>'
            . '<label>Short description<textarea name="short_description" maxlength="500" rows="3" required>' . $e($values['short_description'] ?? '') . '</textarea></label>'
            . '<label>Full course description<textarea name="full_description" maxlength="50000" rows="10" required>' . $e($values['full_description'] ?? '') . '</textarea></label></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>LEARNING PROMISE</span><h3>Outcomes, requirements, and audience</h3></div></div>'
            . '<div class="form-columns"><label>Learning outcomes<textarea name="learning_outcomes" rows="7" maxlength="9000" placeholder="Write one outcome per line">' . $e($listValue($values['learning_outcomes'] ?? '')) . '</textarea><small>Example: Map an application request and response flow.</small></label>'
            . '<label>Course requirements<textarea name="requirements" rows="7" maxlength="9000" placeholder="Write one requirement per line">' . $e($listValue($values['requirements'] ?? '')) . '</textarea><small>Example: Basic understanding of HTTP.</small></label></div>'
            . '<label>Target audience<textarea name="target_audience" rows="5" maxlength="9000" placeholder="Write one audience group per line">' . $e($listValue($values['target_audience'] ?? '')) . '</textarea></label>'
            . '<label>Tags<input name="tags" maxlength="500" value="' . $e($values['tags'] ?? '') . '" placeholder="security, web testing, beginner, burp suite"></label></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>PRICE AND DELIVERY</span><h3>Commercial and course information</h3></div></div>'
            . '<div class="form-columns"><label>Category<select name="category_id" required>' . $options . '</select></label><label>Level<select name="level">' . $levelOptions . '</select></label></div>'
            . '<div class="form-columns"><label>Standard price (NPR)<input type="number" name="price" min="0" max="10000000" step="0.01" value="' . $e($values['price'] ?? '0') . '" required></label><label>Discount price (NPR)<input type="number" name="discount_price" min="0" max="10000000" step="0.01" value="' . $e($values['discount_price'] ?? '') . '" placeholder="Optional, must be lower"></label></div>'
            . '<div class="form-columns"><label>Language<input name="language" maxlength="60" value="' . $e($values['language'] ?? 'English') . '" required></label><label>Estimated duration<input name="duration" maxlength="80" value="' . $e($values['duration'] ?? '') . '" placeholder="12 hours"></label></div>'
            . '<label>Introduction video URL<input type="url" name="intro_video_url" maxlength="500" value="' . $e($values['intro_video_url'] ?? '') . '" placeholder="https://..."><small>Use only an instructor-owned or authorized introduction video.</small></label>'
            . '<label>Thumbnail filename<input name="thumbnail" maxlength="255" value="' . $e($values['thumbnail'] ?? '') . '" placeholder="course-cover.jpg"><small>Binary course-media upload still belongs to the protected media service.</small></label></section>'
            . '<div class="actions course-authoring-actions"><button class="portal-button secondary" name="intent" value="draft" type="submit">Save private draft</button><button class="portal-button" name="intent" value="submit" type="submit">Save and submit for approval</button></div></form>';

        return PortalPage::render('instructor', 'Create a course', $form, '<a class="portal-link" href="/instructor/courses">View my courses →</a>');
    }
}
