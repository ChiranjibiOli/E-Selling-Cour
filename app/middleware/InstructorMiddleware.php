<?php

require_once __DIR__ . '/../core/Auth.php';

class InstructorMiddleware
{
    public static function handle()
    {
        Auth::requireLogin();

        $user = Auth::user();

        if (!$user || ($user['role'] ?? '') !== 'instructor') {
            Auth::redirect('login.php');
        }

        if (($user['status'] ?? '') !== 'active') {
            Auth::logout();
        }
    }
}