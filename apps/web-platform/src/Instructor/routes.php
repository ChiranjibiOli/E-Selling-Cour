<?php

declare(strict_types=1);

return [
    ['method' => 'GET', 'path' => '/instructor/login', 'controller' => CourseHub\WebPlatform\Instructor\Login\LoginController::class, 'middleware' => CourseHub\WebPlatform\Instructor\Login\LoginMiddleware::class],
    ['method' => 'GET', 'path' => '/instructor/register', 'controller' => CourseHub\WebPlatform\Instructor\Registration\RegistrationController::class, 'middleware' => CourseHub\WebPlatform\Instructor\Registration\RegistrationMiddleware::class],
    ['method' => 'GET', 'path' => '/instructor/verification-pending', 'controller' => CourseHub\WebPlatform\Instructor\VerificationPending\VerificationPendingController::class, 'middleware' => CourseHub\WebPlatform\Instructor\VerificationPending\VerificationPendingMiddleware::class],
    ['method' => 'GET', 'path' => '/instructor/dashboard', 'controller' => CourseHub\WebPlatform\Instructor\Dashboard\DashboardController::class, 'middleware' => CourseHub\WebPlatform\Instructor\Dashboard\DashboardMiddleware::class],
    ['method' => 'GET', 'path' => '/instructor/courses', 'controller' => CourseHub\WebPlatform\Instructor\MyCourses\MyCoursesController::class, 'middleware' => CourseHub\WebPlatform\Instructor\MyCourses\MyCoursesMiddleware::class],
    ['method' => 'GET', 'path' => '/instructor/courses/create', 'controller' => CourseHub\WebPlatform\Instructor\CreateCourse\CreateCourseController::class, 'middleware' => CourseHub\WebPlatform\Instructor\CreateCourse\CreateCourseMiddleware::class],
    ['method' => 'GET', 'path' => '/instructor/courses/edit', 'controller' => CourseHub\WebPlatform\Instructor\EditCourse\EditCourseController::class, 'middleware' => CourseHub\WebPlatform\Instructor\EditCourse\EditCourseMiddleware::class],
    ['method' => 'GET', 'path' => '/instructor/lessons', 'controller' => CourseHub\WebPlatform\Instructor\Lessons\LessonsController::class, 'middleware' => CourseHub\WebPlatform\Instructor\Lessons\LessonsMiddleware::class],
    ['method' => 'GET', 'path' => '/instructor/students', 'controller' => CourseHub\WebPlatform\Instructor\Students\StudentsController::class, 'middleware' => CourseHub\WebPlatform\Instructor\Students\StudentsMiddleware::class],
    ['method' => 'GET', 'path' => '/instructor/coupons', 'controller' => CourseHub\WebPlatform\Instructor\Coupons\CouponsController::class, 'middleware' => CourseHub\WebPlatform\Instructor\Coupons\CouponsMiddleware::class],
    ['method' => 'GET', 'path' => '/instructor/bank-details', 'controller' => CourseHub\WebPlatform\Instructor\BankDetails\BankDetailsController::class, 'middleware' => CourseHub\WebPlatform\Instructor\BankDetails\BankDetailsMiddleware::class],
    ['method' => 'GET', 'path' => '/instructor/withdrawals', 'controller' => CourseHub\WebPlatform\Instructor\Withdrawals\WithdrawalsController::class, 'middleware' => CourseHub\WebPlatform\Instructor\Withdrawals\WithdrawalsMiddleware::class],
    ['method' => 'GET', 'path' => '/instructor/profile', 'controller' => CourseHub\WebPlatform\Instructor\Profile\ProfileController::class, 'middleware' => CourseHub\WebPlatform\Instructor\Profile\ProfileMiddleware::class],
];
