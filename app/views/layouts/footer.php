
<style>
    .editorial-stats {
        margin: 0 !important;
        padding: 34px 0 !important;
        background: #171511 !important;
        color: #fffaf0 !important;
    }

    .editorial-stats-grid strong {
        display: block !important;
        color: #fffaf0 !important;
        font-family: Georgia, "Times New Roman", serif !important;
        font-size: 2rem !important;
        font-weight: 500 !important;
        line-height: 1 !important;
    }

    .editorial-stats-grid span {
        display: block !important;
        margin-top: 8px !important;
        color: #b9afa1 !important;
    }

    .site-footer {
        margin-top: 0 !important;
        padding: 64px 0 24px !important;
        border-top: 1px solid rgba(255,255,255,.08) !important;
        background: #171511 !important;
        color: #d7cec0 !important;
    }

    .site-footer .container,
    .footer-bottom {
        width: min(1160px, calc(100% - 36px)) !important;
        margin-left: auto !important;
        margin-right: auto !important;
    }

    .footer-content {
        display: grid !important;
        grid-template-columns: 1.5fr .7fr .8fr !important;
        gap: 72px !important;
        align-items: start !important;
    }

    .footer-brand {
        color: #fffaf0 !important;
        font-family: Georgia, "Times New Roman", serif !important;
    }

    .footer-brand::before {
        content: "C" !important;
        border-radius: 50% !important;
        background: #f2eadc !important;
        color: #9a6e23 !important;
    }

    .site-footer h2 {
        color: #c89537 !important;
        font-family: inherit !important;
        font-size: .66rem !important;
        font-weight: 900 !important;
        letter-spacing: .15em !important;
        text-transform: uppercase !important;
    }

    .site-footer p,
    .site-footer a {
        color: #aaa093 !important;
    }

    .site-footer a:hover {
        color: #fff !important;
    }

    .footer-bottom {
        margin-top: 44px !important;
        padding-top: 18px !important;
        border-top: 1px solid rgba(255,255,255,.1) !important;
        text-align: left !important;
    }

    @media (max-width: 768px) {
        .footer-content {
            grid-template-columns: 1fr 1fr !important;
            gap: 34px 24px !important;
        }

        .footer-content > div:first-child {
            grid-column: 1 / -1 !important;
        }
    }

    @media (max-width: 480px) {
        .footer-content {
            grid-template-columns: 1fr !important;
        }

        .footer-content > div:first-child {
            grid-column: auto !important;
        }
    }
</style>

<footer class="site-footer">
    <div class="container footer-content">
        <div>
            <a class="footer-brand" href="index.php"><?php echo htmlspecialchars(APP_NAME); ?></a>
            <p>Practical learning, clear payment review, and lifetime access to approved purchases.</p>
        </div>

        <div>
            <h2>Explore</h2>
            <ul>
                <li><a href="courses.php">Courses</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </div>

        <div>
            <h2>For creators</h2>
            <ul>
                <li><a href="register.php?role=instructor">Become an instructor</a></li>
                <li><a href="login.php">Instructor login</a></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. All rights reserved.</p>
    </div>
</footer>
</body>
</html>