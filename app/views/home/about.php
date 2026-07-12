<style>
.public-about{padding:56px 0 110px;background:linear-gradient(180deg,#f5eee2 0%,#eee4d6 100%);color:#171511}.public-about .about-shell{width:min(1180px,calc(100% - 32px));margin:0 auto}.public-about .about-hero{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr);gap:48px;align-items:end;padding:56px;border:1px solid rgba(93,70,35,.12);border-radius:34px;background:rgba(255,252,246,.76);box-shadow:0 26px 70px rgba(45,34,21,.10)}.public-about .about-kicker{display:inline-flex;align-items:center;gap:10px;color:#9a6e23;font-size:.72rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase}.public-about .about-kicker::before{content:"";width:36px;height:1px;background:#b88735}.public-about h1,.public-about h2{font-family:Georgia,"Times New Roman",serif;font-weight:500;letter-spacing:-.045em}.public-about h1{margin:18px 0 20px;font-size:clamp(3rem,6vw,6rem);line-height:.92}.public-about .about-lead{max-width:720px;color:#6d655b;font-size:1.02rem;line-height:1.8}.public-about .about-note{padding:28px;border-radius:24px;background:#171511;color:#fff8ed}.public-about .about-note strong{display:block;margin-bottom:10px;font-size:1.15rem}.public-about .about-note p{margin:0;color:#cabfaf;line-height:1.7}.public-about .about-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-top:26px}.public-about .about-card{padding:28px;border:1px solid rgba(93,70,35,.12);border-radius:24px;background:rgba(255,252,246,.72);box-shadow:0 14px 34px rgba(45,34,21,.06)}.public-about .about-card span{display:grid;place-items:center;width:42px;height:42px;margin-bottom:24px;border-radius:14px;background:#171511;color:#d3a04a;font-weight:900}.public-about .about-card h2{margin:0 0 12px;font-size:1.75rem}.public-about .about-card p{margin:0;color:#70685f;line-height:1.7}.public-about .about-principles{display:grid;grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr);gap:30px;align-items:start;margin-top:72px;padding-top:40px;border-top:1px solid rgba(51,40,27,.18)}.public-about .about-principles h2{margin:0;font-size:clamp(2.4rem,4vw,4.4rem);line-height:.95}.public-about .principle-list{display:grid;gap:14px}.public-about .principle{display:grid;grid-template-columns:42px 1fr;gap:16px;padding:18px 0;border-bottom:1px solid rgba(51,40,27,.14)}.public-about .principle b{color:#9a6e23}.public-about .principle strong{display:block;margin-bottom:5px}.public-about .principle p{margin:0;color:#70685f;line-height:1.6}@media(max-width:850px){.public-about .about-hero,.public-about .about-principles{grid-template-columns:1fr}.public-about .about-grid{grid-template-columns:1fr}.public-about .about-hero{padding:34px}}@media(max-width:520px){.public-about{padding:30px 0 70px}.public-about .about-shell{width:min(100% - 20px,1180px)}.public-about .about-hero{padding:26px;border-radius:24px}.public-about h1{font-size:clamp(2.8rem,15vw,4.4rem)}.public-about .about-card{padding:22px}}
</style>
<main class="public-about">
    <div class="about-shell">
        <section class="about-hero">
            <div>
                <p class="about-kicker">About <?php echo htmlspecialchars(APP_NAME); ?></p>
                <h1>A course marketplace built around trust, review, and real access.</h1>
                <p class="about-lead">
                    <?php echo htmlspecialchars(APP_NAME); ?> connects students with approved instructors and keeps every important step visible, from course review and payment verification to enrollment, earnings, and payouts.
                </p>
            </div>
            <aside class="about-note">
                <strong>Clear responsibility at every step</strong>
                <p>Students know what they are buying, instructors know what must be approved, and administrators can verify the records that matter.</p>
            </aside>
        </section>

        <section class="about-grid" aria-label="Who the platform serves">
            <article class="about-card">
                <span>01</span>
                <h2>For students</h2>
                <p>Compare course details, track payment review, and access approved purchases from one organized dashboard.</p>
            </article>
            <article class="about-card">
                <span>02</span>
                <h2>For instructors</h2>
                <p>Create structured lessons, submit courses for review, monitor verified sales, and request recorded payouts.</p>
            </article>
            <article class="about-card">
                <span>03</span>
                <h2>For administrators</h2>
                <p>Review instructor documents, moderate courses, verify payments, manage enrollments, and reconcile payouts.</p>
            </article>
        </section>

        <section class="about-principles">
            <div>
                <p class="about-kicker">Our principles</p>
                <h2>Useful promises, not marketing fog.</h2>
            </div>
            <div class="principle-list">
                <article class="principle"><b>01</b><div><strong>Transparent approval</strong><p>Course approval confirms that required information and resources were reviewed.</p></div></article>
                <article class="principle"><b>02</b><div><strong>Verified access</strong><p>Paid access is activated only after payment records are checked and approved.</p></div></article>
                <article class="principle"><b>03</b><div><strong>No false guarantees</strong><p>The platform does not promise employment, income, certification, or a specific learning result.</p></div></article>
            </div>
        </section>
    </div>
</main>