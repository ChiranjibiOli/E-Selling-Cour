<?php

declare(strict_types=1);

return [
    ['method' => 'GET', 'path' => '/', 'controller' => CourseHub\WebPlatform\Public\Landing\LandingController::class, 'middleware' => CourseHub\WebPlatform\Public\Landing\LandingMiddleware::class],
    ['method' => 'GET', 'path' => '/about', 'controller' => CourseHub\WebPlatform\Public\About\AboutController::class, 'middleware' => CourseHub\WebPlatform\Public\About\AboutMiddleware::class],
    ['method' => 'GET', 'path' => '/contact', 'controller' => CourseHub\WebPlatform\Public\Contact\ContactController::class, 'middleware' => CourseHub\WebPlatform\Public\Contact\ContactMiddleware::class],
    ['method' => 'GET', 'path' => '/faq', 'controller' => CourseHub\WebPlatform\Public\Faq\FaqController::class, 'middleware' => CourseHub\WebPlatform\Public\Faq\FaqMiddleware::class],
    ['method' => 'GET', 'path' => '/courses', 'controller' => CourseHub\WebPlatform\Public\Courses\CoursesController::class, 'middleware' => CourseHub\WebPlatform\Public\Courses\CoursesMiddleware::class],
    ['method' => 'GET', 'path' => '/course', 'controller' => CourseHub\WebPlatform\Public\CourseDetails\CourseDetailsController::class, 'middleware' => CourseHub\WebPlatform\Public\CourseDetails\CourseDetailsMiddleware::class],
    ['method' => 'GET', 'path' => '/search', 'controller' => CourseHub\WebPlatform\Public\CourseSearch\CourseSearchController::class, 'middleware' => CourseHub\WebPlatform\Public\CourseSearch\CourseSearchMiddleware::class],
    ['method' => 'GET', 'path' => '/login', 'controller' => CourseHub\WebPlatform\Public\Login\LoginController::class, 'middleware' => CourseHub\WebPlatform\Public\Login\LoginMiddleware::class],
    ['method' => 'GET', 'path' => '/register/student', 'controller' => CourseHub\WebPlatform\Public\StudentRegistration\StudentRegistrationController::class, 'middleware' => CourseHub\WebPlatform\Public\StudentRegistration\StudentRegistrationMiddleware::class],
    ['method' => 'GET', 'path' => '/register/instructor', 'controller' => CourseHub\WebPlatform\Public\InstructorRegistration\InstructorRegistrationController::class, 'middleware' => CourseHub\WebPlatform\Public\InstructorRegistration\InstructorRegistrationMiddleware::class],
    ['method' => 'GET', 'path' => '/forgot-password', 'controller' => CourseHub\WebPlatform\Public\ForgotPassword\ForgotPasswordController::class, 'middleware' => CourseHub\WebPlatform\Public\ForgotPassword\ForgotPasswordMiddleware::class],
    ['method' => 'GET', 'path' => '/reset-password', 'controller' => CourseHub\WebPlatform\Public\ResetPassword\ResetPasswordController::class, 'middleware' => CourseHub\WebPlatform\Public\ResetPassword\ResetPasswordMiddleware::class],
    ['method' => 'GET', 'path' => '/verify-otp', 'controller' => CourseHub\WebPlatform\Public\VerifyOtp\VerifyOtpController::class, 'middleware' => CourseHub\WebPlatform\Public\VerifyOtp\VerifyOtpMiddleware::class],
];
