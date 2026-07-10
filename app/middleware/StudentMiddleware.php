<?php

require_once __DIR__ . '/../core/Auth.php';

class StudentMiddleware
{
    public static function handle(): void
    {
        Auth::requireRole('student');
    }
}