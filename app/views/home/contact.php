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
<style>
.public-contact{padding:56px 0 110px;background:linear-gradient(180deg,#f5eee2 0%,#eee4d6 100%);color:#171511}.public-contact .contact-shell{width:min(1180px,calc(100% - 32px));margin:0 auto}.public-contact .contact-hero{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(300px,.9fr);gap:42px;align-items:end;padding:54px;border:1px solid rgba(93,70,35,.12);border-radius:34px;background:rgba(255,252,246,.76);box-shadow:0 26px 70px rgba(45,34,21,.10)}.public-contact .contact-kicker{display:inline-flex;align-items:center;gap:10px;color:#9a6e23;font-size:.72rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.public-contact .contact-kicker::before{content:"";width:36px;height:1px;background:#b88735}.public-contact h1,.public-contact h2{font-family:Georgia,"Times New Roman",serif;font-weight:500;letter-spacing:-.045em}.public-contact h1{margin:18px 0 18px;font-size:clamp(3rem,6vw,5.8rem);line-height:.92}.public-contact .contact-lead{max-width:690px;color:#6d655b;font-size:1.02rem;line-height:1.8}.public-contact .response-card{padding:26px;border-radius:24px;background:#171511;color:#fff8ed}.public-contact .response-card strong{display:block;margin-bottom:8px}.public-contact .response-card p{margin:0;color:#cabfaf;line-height:1.65}.public-contact .contact-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-top:26px}.public-contact .contact-card{display:flex;min-height:260px;flex-direction:column;padding:28px;border:1px solid rgba(93,70,35,.12);border-radius:24px;background:rgba(255,252,246,.72);box-shadow:0 14px 34px rgba(45,34,21,.06)}.public-contact .contact-card span{display:grid;place-items:center;width:42px;height:42px;margin-bottom:24px;border-radius:14px;background:#171511;color:#d3a04a;font-weight:900}.public-contact .contact-card h2{margin:0 0 10px;font-size:1.7rem}.public-contact .contact-card p{margin:0 0 24px;color:#70685f;line-height:1.65}.public-contact .contact-card a,.public-contact .contact-card strong{margin-top:auto;color:#171511;font-size:.92rem;font-weight:900;word-break:break-word}.public-contact .contact-card a{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 16px;border-radius:999px;background:#c9973b;text-decoration:none}.public-contact .safety-panel{display:grid;grid-template-columns:minmax(0,.75fr) minmax(0,1.25fr);gap:34px;margin-top:72px;padding:38px;border-radius:28px;background:#ded0b9}.public-contact .safety-panel h2{margin:0;font-size:clamp(2.3rem,4vw,4rem);line-height:.95}.public-contact .safety-panel p{margin:0;color:#645c52;line-height:1.75}@media(max-width:850px){.public-contact .contact-hero,.public-contact .safety-panel{grid-template-columns:1fr}.public-contact .contact-grid{grid-template-columns:1fr}.public-contact .contact-hero{padding:34px}.public-contact .contact-card{min-height:auto}}@media(max-width:520px){.public-contact{padding:30px 0 70px}.public-contact .contact-shell{width:min(100% - 20px,1180px)}.public-contact .contact-hero{padding:26px;border-radius:24px}.public-contact h1{font-size:clamp(2.8rem,15vw,4.4rem)}.public-contact .safety-panel{padding:24px}}
</style>
<main class="public-contact">
    <div class="contact-shell">
        <section class="contact-hero">
            <div>
                <p class="contact-kicker">Contact support</p>
                <h1>Get help with courses, payments, or your account.</h1>
                <p class="contact-lead">Include your order number when asking about a payment so the support team can investigate quickly and avoid the ancient ritual of twelve follow-up emails.</p>
            </div>
            <aside class="response-card">
                <strong>Send useful details</strong>
                <p>Include your registered email, order number, course title, and a clear description of the issue.</p>
            </aside>
        </section>

        <section class="contact-grid" aria-label="Contact methods">
            <article class="contact-card">
                <span>01</span>
                <h2>Email support</h2>
                <p>Best for payment proof, account access, and course questions.</p>
                <a href="mailto:<?php echo htmlspecialchars($supportEmail); ?>"><?php echo htmlspecialchars($supportEmail); ?></a>
            </article>
            <article class="contact-card">
                <span>02</span>
                <h2>Phone</h2>
                <p>Available during the support hours published by the administrator.</p>
                <strong><?php echo htmlspecialchars($supportPhone); ?></strong>
            </article>
            <article class="contact-card">
                <span>03</span>
                <h2>Office</h2>
                <p>Administrative and instructor verification correspondence.</p>
                <strong><?php echo htmlspecialchars($supportAddress); ?></strong>
            </article>
        </section>

        <section class="safety-panel">
            <h2>Before sending payment.</h2>
            <p>Use only the payment details displayed during checkout. Never send passwords, one-time codes, banking login information, or remote-access credentials to an instructor or support representative.</p>
        </section>
    </div>
</main>