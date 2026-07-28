<?php

declare(strict_types=1);
namespace CourseHub\WebPlatform\Shared\Ui;

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;

final class RegistrationPage
{
    public static function render(string $role, array $values = [], string $message = '', bool $success = false, int $status = 200): Response
    {
        $instructor = $role === 'instructor';
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = $instructor ? 'Create an Instructor account' : 'Create your Student account';
        $intro = $instructor
            ? 'Submit your teaching identity and professional details for administrator review. Access opens only after the application is approved.'
            : 'Use a Gmail address. CourseHub sends a six-digit code, and the Student account activates only after that code is verified.';
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';

        $emailLabel = $instructor ? 'Email address' : 'Gmail address';
        $emailPlaceholder = $instructor ? 'name@example.com' : 'yourname@gmail.com';
        $emailHelp = $instructor
            ? '<small>Use the same email when correcting a rejected application. Pending and approved accounts cannot be registered again.</small>'
            : '<small>Student accounts require an address ending in @gmail.com. A verification code will be sent before activation.</small>';
        $emailPattern = $instructor ? '' : ' pattern="[A-Za-z0-9._%+\-]+@[Gg][Mm][Aa][Ii][Ll]\.[Cc][Oo][Mm]"';
        $checked = isset($values['agree_instructor_rules']) ? ' checked' : '';

        $baseFields = '<label>Full name<input type="text" inputmode="text" name="full_name" value="' . $e($values['full_name'] ?? '') . '" minlength="2" maxlength="100" autocomplete="name" required></label>'
            . '<label>' . $emailLabel . '<input type="email" inputmode="email" name="email" value="' . $e($values['email'] ?? '') . '" maxlength="150" autocomplete="email" placeholder="' . $emailPlaceholder . '"' . $emailPattern . ' required>' . $emailHelp . '</label>'
            . '<label>Phone number<input type="tel" inputmode="tel" name="phone" value="' . $e($values['phone'] ?? '') . '" minlength="7" maxlength="20" pattern="[0-9+() -]{7,20}" autocomplete="tel" required></label>';

        $passwordFields = '<div class="form-columns"><label>Password<input type="password" name="password" minlength="8" maxlength="200" autocomplete="new-password" required></label>'
            . '<label>Confirm password<input type="password" name="password_confirmation" minlength="8" maxlength="200" autocomplete="new-password" required></label></div>';

        if ($instructor) {
            $instructorFields = '<div class="form-columns"><label>Professional headline<input type="text" inputmode="text" name="professional_headline" value="' . $e($values['professional_headline'] ?? '') . '" minlength="5" maxlength="160" placeholder="Cybersecurity instructor and web application tester" autocomplete="organization-title" required></label>'
                . '<label>Social or professional profile<input type="url" inputmode="url" name="social_profile_url" value="' . $e($values['social_profile_url'] ?? '') . '" maxlength="500" placeholder="https://linkedin.com/in/..."></label></div>'
                . '<label>Areas of expertise<textarea name="expertise" rows="4" minlength="10" maxlength="1000" placeholder="Web security, networking, PHP, UI design..." required>' . $e($values['expertise'] ?? '') . '</textarea></label>'
                . '<label>Teaching experience<textarea name="teaching_experience" rows="5" minlength="20" maxlength="2000" placeholder="Explain where, what, and how long you have taught or mentored." required>' . $e($values['teaching_experience'] ?? '') . '</textarea></label>'
                . '<label>Professional biography<textarea name="bio" rows="6" minlength="40" maxlength="3000" required>' . $e($values['bio'] ?? '') . '</textarea><small>At least 40 characters. This appears beside approved published courses.</small></label>'
                . '<label>Course subjects<textarea name="course_subjects" rows="3" minlength="3" maxlength="1000" placeholder="Ethical hacking, frontend development, business, design..." required>' . $e($values['course_subjects'] ?? '') . '</textarea></label>'
                . '<div class="form-columns instructor-document-fields"><label>Passport-size profile photo<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required><small>Portrait JPG, PNG or WebP, at least 300 × 400 pixels, maximum 3 MB. You can change the approved profile photo later at any time.</small></label>'
                . '<label>Government identity document<input type="file" name="identity_document" accept="image/jpeg,image/png,image/webp,application/pdf" required><small>Citizenship, passport, licence or another valid ID. Maximum 6 MB and stored privately.</small></label></div>'
                . '<div class="instructor-application-note"><strong>Rejected application?</strong><span>Correct the requested details and submit again with the same email. The rejected record is replaced rather than duplicated.</span></div>'
                . '<label class="check-line"><input type="checkbox" name="agree_instructor_rules" value="1"' . $checked . ' required> I confirm that the information and documents are accurate and agree to the instructor, content-quality, copyright, payment and platform rules.</label>';

            $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#171d27"><meta name="robots" content="noindex,nofollow,noarchive">'
                . '<title>' . $e($title) . ' | CourseHub</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
                . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600&display=swap" rel="stylesheet">'
                . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/assets/css/instructor-registration.css?v=20260728-1"></head><body class="instructor-registration-body">'
                . '<header class="instructor-application-top"><a href="/" class="instructor-application-brand"><img src="/assets/images/coursehub-robot.svg" alt=""><span>CourseHub</span><small>Instructor application</small></a><nav><a href="/learn/sign-in">Student sign in</a><a class="primary" href="/teach/studio-access">Instructor sign in</a></nav></header>'
                . '<main class="instructor-application-shell"><aside class="instructor-application-intro"><div><span>TEACH ON COURSEHUB</span><h1>Apply once.<br><em>Build with confidence.</em></h1><p>' . $e($intro) . '</p></div>'
                . '<ol><li><b>01</b><span><strong>Teaching profile</strong><small>Explain your expertise, experience and planned subjects.</small></span></li><li><b>02</b><span><strong>Private verification</strong><small>Upload a public portrait and a privately stored identity document.</small></span></li><li><b>03</b><span><strong>Administrator review</strong><small>Studio access opens only after the application is approved.</small></span></li></ol>'
                . '<div class="instructor-application-trust"><span>✓ Documents stored privately</span><span>✓ Profile photo changeable anytime</span><span>✓ Reapplication uses the same email</span></div></aside>'
                . '<section class="instructor-application-form"><header><span>INSTRUCTOR ACCOUNT</span><h2>Create your teaching profile</h2><p>Complete every required field so the administrator can review one clear application.</p></header>'
                . $alert . '<form method="post" action="/register/instructor" enctype="multipart/form-data">' . Csrf::field()
                . '<fieldset><legend><b>01</b><span>Account identity</span></legend><div class="form-columns">' . $baseFields . '</div>' . $passwordFields . '</fieldset>'
                . '<fieldset><legend><b>02</b><span>Professional teaching profile</span></legend>' . $instructorFields . '</fieldset>'
                . '<button type="submit">Submit Instructor application</button></form>'
                . '<p class="instructor-application-foot">Already approved? <a href="/teach/studio-access">Open Instructor sign in</a></p></section></main></body></html>';

            return Response::html($html, $status);
        }

        $headExtras = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">'
            . '<link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/assets/css/public-site.css?v=20260728-1">' . PublicNavbar::styles();
        $header = PublicNavbar::render('register');
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f7f0e5">'
            . '<title>' . $e($title) . ' | CourseHub</title>' . $headExtras . '</head><body class="public-form-body">' . $header
            . '<main class="form-shell"><section class="form-intro"><span>STUDENT ACCOUNT</span><h1>' . $e($title) . '</h1><p>' . $e($intro) . '</p></section>'
            . '<section class="form-card">' . $alert . '<form method="post" action="/register/student">' . Csrf::field()
            . $baseFields . $passwordFields
            . '<button type="submit">Send Gmail verification code</button></form>'
            . '<p class="form-foot">Already registered? <a href="/learn/sign-in">Open Student sign in</a></p></section></main>' . PublicNavbar::script() . '</body></html>';

        return Response::html($html, $status);
    }
}
