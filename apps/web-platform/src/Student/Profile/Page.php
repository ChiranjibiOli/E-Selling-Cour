<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\AccountProfilePage;

final class StudentProfilePage
{
    public static function render(array $profile, string $message = '', bool $success = true): Response
    {
        return AccountProfilePage::render('student', $profile, $message, $success);
    }
}
