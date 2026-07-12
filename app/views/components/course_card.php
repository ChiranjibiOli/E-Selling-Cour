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
?>
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