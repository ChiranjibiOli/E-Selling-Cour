<?php

require_once __DIR__ . '/../../config/database.php';

$settings = [];
$result = $conn->query("
    SELECT setting_key, setting_value
    FROM site_settings
    WHERE setting_key IN ('site_email', 'site_phone', 'site_address')
");

while ($result && $row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = trim((string) $row['setting_value']);
}

$supportEmail = ($settings['site_email'] ?? '') ?: 'support@example.com';
$supportPhone = ($settings['site_phone'] ?? '') ?: 'Not configured';
$supportAddress = ($settings['site_address'] ?? '') ?: 'Kathmandu, Nepal';
?>
<main class="info-page">
    <section class="container info-hero">
        <p class="page-label">Contact</p>
        <h1>Get help with courses, payments, or your account</h1>
        <p>Include your order number when asking about a payment so the support team can investigate quickly.</p>
    </section>

    <section class="container contact-grid">
        <article class="content-card">
            <h2>Email support</h2>
            <p>Best for payment proof, account access, and course questions.</p>
            <a class="btn btn-primary" href="mailto:<?php echo htmlspecialchars($supportEmail); ?>">
                <?php echo htmlspecialchars($supportEmail); ?>
            </a>
        </article>
        <article class="content-card">
            <h2>Phone</h2>
            <p>Available during the support hours published by the administrator.</p>
            <strong><?php echo htmlspecialchars($supportPhone); ?></strong>
        </article>
        <article class="content-card">
            <h2>Office</h2>
            <p>Administrative and instructor verification correspondence.</p>
            <strong><?php echo htmlspecialchars($supportAddress); ?></strong>
        </article>
    </section>

    <section class="container trust-panel">
        <h2>Before sending payment</h2>
        <p>
            Use only the payment details displayed during checkout. Never send passwords, one-time
            codes, or banking login information to an instructor or support representative.
        </p>
    </section>
</main>
