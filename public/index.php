<?php

require_once '../app/config/database.php';
$conn = database_connection();

require_once '../app/views/layouts/header.php';
require_once '../app/views/layouts/navbar.php';
require_once '../app/views/home/landing.php';
require_once '../app/views/layouts/footer.php';
