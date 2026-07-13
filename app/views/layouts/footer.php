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
