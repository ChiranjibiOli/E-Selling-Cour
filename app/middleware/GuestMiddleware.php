<?php

require_once __DIR__ . '/../core/Auth.php';

class GuestMiddleware
{
    public static function handle(): void
    {
        Auth::guestOnly();
    }
}