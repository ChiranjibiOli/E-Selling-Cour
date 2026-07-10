<?php

require_once __DIR__ . '/../core/Auth.php';

class AdminMiddleware
{
    public static function handle()
    {
        Auth::requireRole('admin');
    }
}