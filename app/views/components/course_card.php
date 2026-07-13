<?php

declare(strict_types=1);

/**
 * Canonical course-card pipeline.
 *
 * Public catalog, student browse/cart/library, instructor course library and
 * admin course review all render this component. Context-specific controls may
 * be added, but the shared card structure remains the same.
 */

$card = is_array($courseCard ?? null) ? $courseCard : [];
$context = (string) ($card['context'] ?? 'marketplace');
$allowedContexts = ['marketplace', 'student', 'cart', 'instructor', 'admin'];

if (!in_array($context, $allowedContexts, true)) {
    $context = 'marketplace';
}

$escape = static fn (mixed $value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$title = trim((string) ($card['title'] ?? 'Untitled course'));
$summary = trim((string) ($card['summary'] ?? ''));
$thumbnail = trim((string) ($card['thumbnail'] ?? 'assets/images/course-placeholder.svg'));
$category = trim((string) ($card['category'] ?? ''));
$badge = trim((string) ($card['badge'] ?? ''));
$eyebrow = trim((string) ($card['eyebrow'] ?? ''));
$language = trim((string) ($card['language'] ?? ''));
$duration = trim((string) ($card['duration'] ?? ''));
$price = trim((string) ($card['price'] ?? ''));
$href = trim((string) ($card['href'] ?? ''));
$metrics = is_array($card['metrics'] ?? null) ? $card['metrics'] : [];
$actions = is_array($card['actions'] ?? null) ? $card['actions'] : [];
$featureHtml = is_string($card['feature_html'] ?? null) ? $card['feature_html'] : '';
$statusClass = preg_replace('/[^a-z0-9_-]/i', '', (string) ($card['status_class'] ?? ''));
$cardId = 'course-card-' . substr(hash('sha256', $context . '|' . $title . '|' . $href), 0, 12);

$courseId = (int) ($card['course_id'] ?? 0);
$ratingValue = max(0.0, min(5.0, (float) ($card['rating'] ?? 0)));
$reviewCount = max(0, (int) ($card['review_count'] ?? 0));

if ($courseId <= 0 && $href !== '') {
    $queryString = (string) parse_url($href, PHP_URL_QUERY);
    $queryValues = [];
    parse_str($queryString, $queryValues);

    if (!empty($queryValues['course_id'])) {
        $courseId = (int) $queryValues['course_id'];
    } elseif (!empty($queryValues['slug']) && isset($conn) && $conn instanceof mysqli) {
        $slug = trim((string) $queryValues['slug']);
        if ($slug !== '') {
            $idStmt = $conn->prepare('SELECT id FROM courses WHERE slug = ? LIMIT 1');
            if ($idStmt) {
                $idStmt->bind_param('s', $slug);
                $idStmt->execute();
                $idRow = $idStmt->get_result()->fetch_assoc();
                $courseId = (int) ($idRow['id'] ?? 0);
                $idStmt->close();
            }
        }
    }
}

if ($courseId > 0 && isset($conn) && $conn instanceof mysqli && !array_key_exists('rating', $card)) {
    if (!isset($GLOBALS['course_card_rating_cache']) || !is_array($GLOBALS['course_card_rating_cache'])) {
        $GLOBALS['course_card_rating_cache'] = [];
    }

    if (!isset($GLOBALS['course_card_rating_cache'][$courseId])) {
        $ratingSummary = ['rating' => 0.0, 'count' => 0];
        $ratingStmt = $conn->prepare("
            SELECT COALESCE(AVG(rating), 0) AS average_rating, COUNT(*) AS review_count
            FROM reviews
            WHERE course_id = ? AND status = 'visible'
        ");

        if ($ratingStmt) {
            $ratingStmt->bind_param('i', $courseId);
            $ratingStmt->execute();
            $ratingRow = $ratingStmt->get_result()->fetch_assoc() ?: [];
            $ratingSummary = [
                'rating' => (float) ($ratingRow['average_rating'] ?? 0),
                'count' => (int) ($ratingRow['review_count'] ?? 0),
            ];
            $ratingStmt->close();
        }

        $GLOBALS['course_card_rating_cache'][$courseId] = $ratingSummary;
    }

    $ratingValue = max(0.0, min(5.0, (float) $GLOBALS['course_card_rating_cache'][$courseId]['rating']));
    $reviewCount = max(0, (int) $GLOBALS['course_card_rating_cache'][$courseId]['count']);
}

$filledStars = $reviewCount > 0 ? (int) round($ratingValue) : 0;
$reviewLabel = $reviewCount === 1 ? '1 review' : number_format($reviewCount) . ' reviews';

static $canonicalLayoutRendered = false;
if (!$canonicalLayoutRendered):
    $canonicalLayoutRendered = true;
?>
<style>
.marketplace-card.course-unit-card{width:100%;height:100%;min-height:100%;align-self:stretch}
.marketplace-card.course-unit-card .marketplace-cover{flex:0 0 auto}
.marketplace-card.course-unit-card .marketplace-content{display:flex!important;min-height:0;flex:1 1 auto;flex-direction:column!important}
.marketplace-card.course-unit-card .course-unit-main{display:flex;min-width:0;flex:1 1 auto;flex-direction:column}
.marketplace-card.course-unit-card .course-unit-workflow{display:flex;flex:0 0 auto;flex-direction:column;margin-top:auto}
.marketplace-card.course-unit-card .preview-price-row{width:100%}
.course-unit-price-spacer{display:block;min-width:1px;min-height:1px}
.course-unit-feature{width:100%;min-width:0}
.course-unit-card--admin .course-unit-feature{margin-top:12px;padding-top:12px;border-top:1px solid #e9edf5}
.course-unit-actions form{display:inline-flex;margin:0}
.course-unit-action--danger{color:#b42318;background:#fee4e2}
.course-unit-action--danger:hover{background:#fecdca}
.course-admin-note{margin:0 0 10px;padding:10px 11px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc;color:#475467;font-size:.74rem;line-height:1.45}
.course-admin-review-actions{display:flex;flex-wrap:wrap;gap:7px;align-items:center}
.course-admin-review-actions form{margin:0}
.course-admin-approve,.course-admin-reject-toggle,.course-admin-reject-form button{min-height:36px;padding:0 11px;border:0;border-radius:9px;font:inherit;font-size:.7rem;font-weight:900;cursor:pointer}
.course-admin-approve{color:#fff;background:#059669}
.course-admin-reject-toggle{color:#b91c1c;background:#fee2e2}
.course-admin-reject-form{display:none;gap:9px;margin-top:10px;padding:10px;border:1px solid #fecaca;border-radius:11px;background:#fef2f2}
.course-admin-reject-form.open{display:grid}
.course-admin-reject-form label{display:grid;gap:5px}
.course-admin-reject-form label span{color:#991b1b;font-size:.7rem;font-weight:900}
.course-admin-reject-form textarea{width:100%;min-height:82px;padding:9px;border:1px solid #fca5a5;border-radius:9px;background:#fff;resize:vertical;font:inherit}
.course-admin-reject-form button{color:#fff;background:#dc2626}
@media(max-width:700px){.course-admin-review-actions,.course-admin-review-actions form,.course-admin-approve,.course-admin-reject-toggle,.course-admin-reject-form button,.course-unit-actions form{width:100%}}
</style>
<?php endif; ?>
<article
    class="marketplace-card course-unit-card course-unit-card--<?php echo $escape($context); ?>"
    data-course-context="<?php echo $escape($context); ?>"
    aria-labelledby="<?php echo $escape($cardId); ?>"
>
    <div class="marketplace-cover course-unit-cover">
        <?php if ($href !== ''): ?>
            <a class="course-unit-cover-link" href="<?php echo $escape($href); ?>" aria-label="View <?php echo $escape($title); ?>">
        <?php endif; ?>

        <img src="<?php echo $escape($thumbnail); ?>" alt="<?php echo $escape($title); ?>" loading="lazy" decoding="async">
        <span class="course-unit-cover-shade" aria-hidden="true"></span>

        <?php if ($category !== ''): ?>
            <span class="preview-category course-unit-chip course-unit-chip--category"><?php echo $escape($category); ?></span>
        <?php endif; ?>

        <?php if ($badge !== ''): ?>
            <span class="preview-level course-unit-chip course-unit-chip--badge <?php echo $statusClass !== '' ? 'is-' . $escape($statusClass) : ''; ?>">
                <?php echo $escape($badge); ?>
            </span>
        <?php endif; ?>

        <?php if ($href !== ''): ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="marketplace-content course-unit-content">
        <div class="course-unit-main">
            <?php if ($eyebrow !== ''): ?>
                <p class="course-unit-eyebrow"><?php echo $escape($eyebrow); ?></p>
            <?php endif; ?>

            <?php if ($language !== '' || $duration !== ''): ?>
                <div class="preview-meta course-unit-meta" aria-label="Course information">
                    <?php if ($language !== ''): ?><span><?php echo $escape($language); ?></span><?php endif; ?>
                    <?php if ($language !== '' && $duration !== ''): ?><span aria-hidden="true">•</span><?php endif; ?>
                    <?php if ($duration !== ''): ?><span><?php echo $escape($duration); ?></span><?php endif; ?>
                </div>
            <?php endif; ?>

            <h3 class="course-unit-title" id="<?php echo $escape($cardId); ?>">
                <?php if ($href !== ''): ?>
                    <a href="<?php echo $escape($href); ?>"><?php echo $escape($title); ?></a>
                <?php else: ?>
                    <?php echo $escape($title); ?>
                <?php endif; ?>
            </h3>

            <?php if ($summary !== ''): ?>
                <p class="course-unit-summary"><?php echo $escape($summary); ?></p>
            <?php endif; ?>

            <div class="preview-rating course-unit-rating" aria-label="<?php echo $reviewCount > 0 ? $escape(number_format($ratingValue, 1) . ' out of 5 from ' . $reviewLabel) : 'No student reviews yet'; ?>">
                <span class="course-rating-stars" aria-hidden="true">
                    <?php for ($star = 1; $star <= 5; $star++): ?>
                        <span class="<?php echo $star <= $filledStars ? 'is-filled' : ''; ?>">★</span>
                    <?php endfor; ?>
                </span>
                <?php if ($reviewCount > 0): ?>
                    <strong><?php echo number_format($ratingValue, 1); ?></strong>
                    <small>(<?php echo $escape($reviewLabel); ?>)</small>
                <?php else: ?>
                    <small>No reviews yet</small>
                <?php endif; ?>
            </div>

            <?php if ($metrics): ?>
                <dl class="course-unit-metrics">
                    <?php foreach ($metrics as $metric): ?>
                        <?php
                        $label = trim((string) ($metric['label'] ?? ''));
                        $value = trim((string) ($metric['value'] ?? ''));
                        if ($label === '' || $value === '') {
                            continue;
                        }
                        ?>
                        <div>
                            <dt><?php echo $escape($label); ?></dt>
                            <dd title="<?php echo $escape($value); ?>"><?php echo $escape($value); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>

        <div class="course-unit-workflow">
            <footer class="preview-price-row course-unit-footer">
                <?php if ($price !== ''): ?>
                    <strong class="course-unit-price"><?php echo $escape($price); ?></strong>
                <?php else: ?>
                    <span class="course-unit-price-spacer" aria-hidden="true"></span>
                <?php endif; ?>

                <?php if ($actions): ?>
                    <div class="course-unit-actions">
                        <?php foreach ($actions as $action): ?>
                            <?php
                            $label = trim((string) ($action['label'] ?? ''));
                            $actionHref = trim((string) ($action['href'] ?? ''));
                            $style = preg_replace('/[^a-z0-9_-]/i', '', (string) ($action['style'] ?? 'secondary'));
                            $disabled = !empty($action['disabled']);
                            $method = strtolower(trim((string) ($action['method'] ?? 'get')));
                            $hidden = is_array($action['hidden'] ?? null) ? $action['hidden'] : [];
                            $confirm = trim((string) ($action['confirm'] ?? ''));

                            if ($label === '') {
                                continue;
                            }
                            ?>
                            <?php if ($disabled): ?>
                                <span class="course-unit-action course-unit-action--<?php echo $escape($style); ?> is-disabled" aria-disabled="true">
                                    <?php echo $escape($label); ?>
                                </span>
                            <?php elseif ($method === 'post' && $actionHref !== ''): ?>
                                <form method="post" action="<?php echo $escape($actionHref); ?>">
                                    <?php echo csrf_field(); ?>
                                    <?php foreach ($hidden as $hiddenName => $hiddenValue): ?>
                                        <input type="hidden" name="<?php echo $escape($hiddenName); ?>" value="<?php echo $escape($hiddenValue); ?>">
                                    <?php endforeach; ?>
                                    <button
                                        type="submit"
                                        class="course-unit-action course-unit-action--<?php echo $escape($style); ?>"
                                        <?php echo $confirm !== '' ? 'data-confirm="' . $escape($confirm) . '"' : ''; ?>
                                    ><?php echo $escape($label); ?></button>
                                </form>
                            <?php elseif ($actionHref !== ''): ?>
                                <a class="course-unit-action course-unit-action--<?php echo $escape($style); ?>" href="<?php echo $escape($actionHref); ?>">
                                    <?php echo $escape($label); ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </footer>

            <?php if ($featureHtml !== ''): ?>
                <div class="course-unit-feature course-unit-feature--<?php echo $escape($context); ?>">
                    <?php echo $featureHtml; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</article>
<?php unset($courseCard, $card, $featureHtml); ?>
