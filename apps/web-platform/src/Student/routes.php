<?php

declare(strict_types=1);

return [
    ['method' => 'GET', 'path' => '/student/login', 'controller' => CourseHub\WebPlatform\Student\Login\LoginController::class, 'middleware' => CourseHub\WebPlatform\Student\Login\LoginMiddleware::class],
    ['method' => 'GET', 'path' => '/student/register', 'controller' => CourseHub\WebPlatform\Student\Registration\RegistrationController::class, 'middleware' => CourseHub\WebPlatform\Student\Registration\RegistrationMiddleware::class],
    ['method' => 'GET', 'path' => '/student/dashboard', 'controller' => CourseHub\WebPlatform\Student\Dashboard\DashboardController::class, 'middleware' => CourseHub\WebPlatform\Student\Dashboard\DashboardMiddleware::class],
    ['method' => 'GET', 'path' => '/student/cart', 'controller' => CourseHub\WebPlatform\Student\Cart\CartController::class, 'middleware' => CourseHub\WebPlatform\Student\Cart\CartMiddleware::class],
    ['method' => 'GET', 'path' => '/student/checkout', 'controller' => CourseHub\WebPlatform\Student\Checkout\CheckoutController::class, 'middleware' => CourseHub\WebPlatform\Student\Checkout\CheckoutMiddleware::class],
    ['method' => 'GET', 'path' => '/student/payment', 'controller' => CourseHub\WebPlatform\Student\Payment\PaymentController::class, 'middleware' => CourseHub\WebPlatform\Student\Payment\PaymentMiddleware::class],
    ['method' => 'GET', 'path' => '/student/payment-history', 'controller' => CourseHub\WebPlatform\Student\PaymentHistory\PaymentHistoryController::class, 'middleware' => CourseHub\WebPlatform\Student\PaymentHistory\PaymentHistoryMiddleware::class],
    ['method' => 'GET', 'path' => '/student/my-courses', 'controller' => CourseHub\WebPlatform\Student\MyCourses\MyCoursesController::class, 'middleware' => CourseHub\WebPlatform\Student\MyCourses\MyCoursesMiddleware::class],
    ['method' => 'GET', 'path' => '/student/course-player', 'controller' => CourseHub\WebPlatform\Student\CoursePlayer\CoursePlayerController::class, 'middleware' => CourseHub\WebPlatform\Student\CoursePlayer\CoursePlayerMiddleware::class],
    ['method' => 'GET', 'path' => '/student/progress', 'controller' => CourseHub\WebPlatform\Student\Progress\ProgressController::class, 'middleware' => CourseHub\WebPlatform\Student\Progress\ProgressMiddleware::class],
    ['method' => 'GET', 'path' => '/student/notifications', 'controller' => CourseHub\WebPlatform\Student\Notifications\NotificationsController::class, 'middleware' => CourseHub\WebPlatform\Student\Notifications\NotificationsMiddleware::class],
    ['method' => 'GET', 'path' => '/student/profile', 'controller' => CourseHub\WebPlatform\Student\Profile\ProfileController::class, 'middleware' => CourseHub\WebPlatform\Student\Profile\ProfileMiddleware::class],
    ['method' => 'GET', 'path' => '/student/unsubscribe', 'controller' => CourseHub\WebPlatform\Student\Unsubscribe\UnsubscribeController::class, 'middleware' => CourseHub\WebPlatform\Student\Unsubscribe\UnsubscribeMiddleware::class],
];
