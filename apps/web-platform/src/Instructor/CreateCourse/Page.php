<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class CreateCoursePage
{
    public static function render(
        array $categories,
        array $values = [],
        string $message = '',
        bool $success = false,
        string $instructorName = 'CourseHub instructor',
    ): Response {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $listValue = static fn (mixed $value): string => is_array($value) ? implode("\n", array_map('strval', $value)) : (string) $value;
        $selectedCategoryName = 'Choose a category';
        $options = '<option value="">Choose category</option>';
        foreach ($categories as $category) {
            $selected = (string) ($values['category_id'] ?? '') === (string) ($category['id'] ?? '') ? ' selected' : '';
            if ($selected !== '') {
                $selectedCategoryName = (string) ($category['name'] ?? 'Course');
            }
            $options .= '<option value="' . (int) ($category['id'] ?? 0) . '"' . $selected . '>' . $e($category['name'] ?? '') . '</option>';
        }
        $levelOptions = '';
        foreach (['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $value => $label) {
            $selected = ($values['level'] ?? 'beginner') === $value ? ' selected' : '';
            $levelOptions .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }

        $title = trim((string) ($values['title'] ?? ''));
        $shortDescription = trim((string) ($values['short_description'] ?? ''));
        $price = trim((string) ($values['price'] ?? '0'));
        $discountPrice = trim((string) ($values['discount_price'] ?? ''));
        $previewPrice = $discountPrice !== '' ? $discountPrice : $price;
        $previewPriceText = is_numeric($previewPrice) && (float) $previewPrice > 0
            ? 'NPR ' . number_format((float) $previewPrice, 0)
            : 'Free';
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';

        $form = $alert
            . '<div class="course-authoring-layout" data-course-authoring>'
            . '<form class="portal-form course-authoring-form" method="post" action="/instructor/courses/create" enctype="multipart/form-data">' . Csrf::field()
            . '<section class="course-authoring-surface">'
            . '<header class="course-authoring-surface-head"><div><span>COURSE AUTHORING</span><h2>Create the public course information</h2><p>Save a private draft first, then add lessons and submit it for review.</p></div><strong>01</strong></header>'
            . '<div class="course-authoring-section"><div class="course-authoring-section-title"><span>Identity</span><h3>Title and positioning</h3></div>'
            . '<label>Course title<input type="text" name="title" maxlength="180" minlength="3" value="' . $e($values['title'] ?? '') . '" placeholder="Complete Web Application Security" autocomplete="off" data-preview-source="title" required></label>'
            . '<label>Course subtitle<input type="text" name="subtitle" maxlength="240" value="' . $e($values['subtitle'] ?? '') . '" placeholder="Learn practical testing from request mapping to verified findings" autocomplete="off"></label>'
            . '<label>Short description<textarea name="short_description" maxlength="500" minlength="20" rows="3" data-preview-source="description" required>' . $e($values['short_description'] ?? '') . '</textarea><small>Use plain text. This appears on the public course card.</small></label>'
            . '<label>Full course description<textarea name="full_description" maxlength="50000" minlength="50" rows="10" required>' . $e($values['full_description'] ?? '') . '</textarea></label></div>'
            . '<div class="course-authoring-section"><div class="course-authoring-section-title"><span>Learning promise</span><h3>Outcomes and audience</h3></div>'
            . '<div class="form-columns"><label>Learning outcomes<textarea name="learning_outcomes" rows="7" maxlength="9030" placeholder="Write one outcome per line">' . $e($listValue($values['learning_outcomes'] ?? '')) . '</textarea><small>Maximum 30 lines, 300 characters each.</small></label>'
            . '<label>Course requirements<textarea name="requirements" rows="7" maxlength="9030" placeholder="Write one requirement per line">' . $e($listValue($values['requirements'] ?? '')) . '</textarea><small>Maximum 30 lines, 300 characters each.</small></label></div>'
            . '<label>Target audience<textarea name="target_audience" rows="5" maxlength="9030" placeholder="Write one audience group per line">' . $e($listValue($values['target_audience'] ?? '')) . '</textarea></label>'
            . '<label>Tags<input type="text" name="tags" maxlength="500" value="' . $e($values['tags'] ?? '') . '" placeholder="security, web testing, beginner, burp suite" autocomplete="off"></label></div>'
            . '<div class="course-authoring-section"><div class="course-authoring-section-title"><span>Delivery</span><h3>Category, price and media</h3></div>'
            . '<div class="form-columns"><label>Category<select name="category_id" data-preview-source="category" required>' . $options . '</select></label><label>Level<select name="level">' . $levelOptions . '</select></label></div>'
            . '<div class="form-columns"><label>Standard price (NPR)<input type="number" inputmode="decimal" name="price" min="0" max="10000000" step="0.01" value="' . $e($values['price'] ?? '0') . '" data-preview-source="price" required></label><label>Discount price (NPR)<input type="number" inputmode="decimal" name="discount_price" min="0" max="10000000" step="0.01" value="' . $e($values['discount_price'] ?? '') . '" data-preview-source="discount" placeholder="Optional, lower than standard price"></label></div>'
            . '<div class="form-columns"><label>Language<input type="text" inputmode="text" name="language" maxlength="60" minlength="2" value="' . $e($values['language'] ?? 'English') . '" autocomplete="off" required></label><label>Estimated duration in hours<input type="number" inputmode="decimal" name="duration_hours" min="0.25" max="10000" step="0.25" value="' . $e($values['duration_hours'] ?? '') . '" placeholder="12"></label></div>'
            . '<label>Introduction video URL<input type="url" inputmode="url" name="intro_video_url" maxlength="500" value="' . $e($values['intro_video_url'] ?? '') . '" placeholder="https://..."><small>HTTPS only. Do not paste a URL containing a username or password.</small></label>'
            . '<label>Course thumbnail<input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp" data-preview-source="thumbnail"><small>Landscape JPG, PNG or WebP, minimum 640 × 360 pixels, maximum 4 MB.</small></label></div>'
            . '<div class="course-authoring-actions"><button class="portal-button secondary" name="intent" value="draft" type="submit">Save private draft</button><button class="portal-button" name="intent" value="curriculum" type="submit">Save and build curriculum</button></div>'
            . '</section></form>'
            . '<aside class="course-live-preview" aria-label="Live public course card preview"><div class="course-live-preview-head"><span>LIVE PREVIEW</span><strong>Public course card</strong><p>This updates while you type. It does not publish the course.</p></div>'
            . '<article class="course-live-card">'
            . '<div class="course-live-card-media" data-preview-media><span>CourseHub</span></div>'
            . '<div class="course-live-card-body"><span data-preview-category>' . $e($selectedCategoryName) . '</span>'
            . '<h3 data-preview-title>' . $e($title !== '' ? $title : 'Your course title') . '</h3>'
            . '<p data-preview-description>' . $e($shortDescription !== '' ? $shortDescription : 'Your short description will appear here and stay contained inside the course card.') . '</p>'
            . '<footer><small>By ' . $e($instructorName) . '</small><strong data-preview-price>' . $e($previewPriceText) . '</strong></footer></div></article>'
            . '<div class="course-live-preview-note"><strong>Preview checklist</strong><span>Clear title</span><span>Readable thumbnail</span><span>Short benefit-focused description</span><span>Correct price</span></div></aside>'
            . '</div>';

        return PortalPage::render('instructor', 'Create a course', $form, '<a class="portal-link" href="/instructor/courses">View my courses →</a>');
    }
}
