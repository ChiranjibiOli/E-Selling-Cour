<?php

declare(strict_types=1);

namespace CourseHub\PublicWeb\Pages\Registration;

use CourseHub\SharedUi\PortalShell;

final class RegistrationPage
{
    public function render(): string
    {
        $studentUrl = (string) (getenv('STUDENT_PORTAL_URL') ?: 'http://localhost:9002/register');
        $instructorUrl = (string) (getenv('INSTRUCTOR_PORTAL_URL') ?: 'http://localhost:9003/register');

        $body = '<span class="eyebrow">Create account</span><h1>Choose your portal.</h1>'
            . '<p>Student and instructor registration are intentionally separated because their verification and approval rules are different.</p>'
            . '<div class="links">'
            . '<a class="button" href="' . htmlspecialchars($studentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Student account</a>'
            . '<a class="button secondary" href="' . htmlspecialchars($instructorUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Instructor account</a>'
            . '<a class="button secondary" href="/">Back home</a>'
            . '</div>';

        return PortalShell::page('Create a CourseHub account', $body);
    }
}
