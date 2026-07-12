<?php

declare(strict_types=1);

/**
 * Canonical course-card layout used across the platform.
 *
 * Expected input in $courseCard:
 * - context: marketplace|student|instructor|admin
 * - title, summary, thumbnail, category, badge, eyebrow, language, duration, price
 * - href: optional primary course URL
 * - metrics: [['label' => 'Lessons', 'value' => '12'], ...]
 * - actions: [['label' => 'View course', 'href' => '...', 'style' => 'primary'], ...]
 * - feature_html: trusted server-rendered workflow controls placed after the common footer
 */

$card = is_array($courseCard ?? null) ? $courseCard : [];
$context = (string) ($card['context'] ?? 'marketplace');
$allowedContexts = ['marketplace', 'student', 'instructor', 'admin'];

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

static $canonicalLayoutRendered = false;
if (!$canonicalLayoutRendered):
    $canonicalLayoutRendered = true;
?>
<style>
.course-unit-card{width:100%;height:100%;min-height:100%;align-self:stretch}
.course-unit-cover{flex:0 0 auto}
.course-unit-content{display:flex!important;min-height:0;flex:1 1 auto;flex-direction:column!important}
.course-unit-main{display:flex;min-width:0;flex:1 1 auto;flex-direction:column}
.course-unit-workflow{display:flex;flex:0 0 auto;flex-direction:column;margin-top:auto}
.course-unit-footer{width:100%;margin-top:0!important}
.course-unit-price-spacer{display:block;min-width:1px;min-height:1px}
.course-unit-feature{width:100%;min-width:0}
.course-unit-card--admin .course-unit-feature{margin-top:14px;padding-top:14px;border-top:1px solid #e9edf5}
.course-admin-note{margin:0 0 12px;padding:11px 12px;border:1px solid #e5e7eb;border-radius:11px;background:#f8fafc;color:#475467;font-size:.78rem;line-height:1.5}
.course-admin-review-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.course-admin-review-actions form{margin:0}
.course-admin-approve,.course-admin-reject-toggle,.course-admin-reject-form button{min-height:39px;padding:0 13px;border:0;border-radius:10px;font:inherit;font-size:.74rem;font-weight:900;cursor:pointer}
.course-admin-approve{color:#fff;background:#059669}
.course-admin-reject-toggle{color:#b91c1c;background:#fee2e2}
.course-admin-reject-form{display:none;gap:10px;margin-top:12px;padding:12px;border:1px solid #fecaca;border-radius:12px;background:#fef2f2}
.course-admin-reject-form.open{display:grid}
.course-admin-reject-form label{display:grid;gap:6px}
.course-admin-reject-form label span{color:#991b1b;font-size:.72rem;font-weight:900}
.course-admin-reject-form textarea{width:100%;min-height:90px;padding:10px;border:1px solid #fca5a5;border-radius:10px;background:#fff;resize:vertical;font:inherit}
.course-admin-reject-form button{color:#fff;background:#dc2626}
.editorial-course-list,.student-course-grid,.my-courses-grid,.course-library-grid,.review-grid{align-items:stretch}
.editorial-course-list>.course-unit-card,.student-course-grid>.course-unit-card,.my-courses-grid>.course-unit-card,.course-library-grid>.course-unit-card,.review-grid>.course-unit-card{height:100%}
@media(max-width:700px){.course-admin-review-actions,.course-admin-review-actions form,.course-admin-approve,.course-admin-reject-toggle,.course-admin-reject-form button{width:100%}.course-unit-workflow{width:100%}}
</style>
<?php endif; ?>
<article
    class="course-unit-card course-unit-card--<?php echo $escape($context); ?>"
    data-course-context="<?php echo $escape($context); ?>"
    aria-labelledby="<?php echo $escape($cardId); ?>"
>
    <div class="course-unit-cover">
        <?php if ($href !== ''): ?>
            <a class="course-unit-cover-link" href="<?php echo $escape($href); ?>" aria-label="View <?php echo $escape($title); ?>">
        <?php endif; ?>

        <img src="<?php echo $escape($thumbnail); ?>" alt="<?php echo $escape($title); ?>" loading="lazy" decoding="async">
        <span class="course-unit-cover-shade" aria-hidden="true"></span>

        <?php if ($category !== ''): ?>
            <span class="course-unit-chip course-unit-chip--category"><?php echo $escape($category); ?></span>
        <?php endif; ?>

        <?php if ($badge !== ''): ?>
            <span class="course-unit-chip course-unit-chip--badge <?php echo $statusClass !== '' ? 'is-' . $escape($statusClass) : ''; ?>">
                <?php echo $escape($badge); ?>
            </span>
        <?php endif; ?>

        <?php if ($href !== ''): ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="course-unit-content">
        <div class="course-unit-main">
            <?php if ($eyebrow !== ''): ?>
                <p class="course-unit-eyebrow"><?php echo $escape($eyebrow); ?></p>
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

            <?php if ($language !== '' || $duration !== ''): ?>
                <div class="course-unit-meta" aria-label="Course information">
                    <?php if ($language !== ''): ?><span><?php echo $escape($language); ?></span><?php endif; ?>
                    <?php if ($duration !== ''): ?><span><?php echo $escape($duration); ?></span><?php endif; ?>
                </div>
            <?php endif; ?>

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
            <footer class="course-unit-footer">
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

                            if ($label === '') {
                                continue;
                            }
                            ?>
                            <?php if ($disabled || $actionHref === ''): ?>
                                <span class="course-unit-action course-unit-action--<?php echo $escape($style); ?> is-disabled" aria-disabled="true">
                                    <?php echo $escape($label); ?>
                                </span>
                            <?php else: ?>
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