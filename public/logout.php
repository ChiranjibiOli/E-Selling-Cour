<?php

require_once '../app/core/Auth.php';

Auth::start();
Security::requirePost();
Auth::logout();
