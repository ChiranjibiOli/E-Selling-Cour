<?php

declare(strict_types=1);

return [
    ['method' => 'GET', 'path' => '/admin/login', 'controller' => CourseHub\WebPlatform\Admin\Login\LoginController::class, 'middleware' => CourseHub\WebPlatform\Admin\Login\LoginMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/dashboard', 'controller' => CourseHub\WebPlatform\Admin\Dashboard\DashboardController::class, 'middleware' => CourseHub\WebPlatform\Admin\Dashboard\DashboardMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/instructor-approvals', 'controller' => CourseHub\WebPlatform\Admin\InstructorApprovals\InstructorApprovalsController::class, 'middleware' => CourseHub\WebPlatform\Admin\InstructorApprovals\InstructorApprovalsMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/course-approvals', 'controller' => CourseHub\WebPlatform\Admin\CourseApprovals\CourseApprovalsController::class, 'middleware' => CourseHub\WebPlatform\Admin\CourseApprovals\CourseApprovalsMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/categories', 'controller' => CourseHub\WebPlatform\Admin\Categories\CategoriesController::class, 'middleware' => CourseHub\WebPlatform\Admin\Categories\CategoriesMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/students', 'controller' => CourseHub\WebPlatform\Admin\Students\StudentsController::class, 'middleware' => CourseHub\WebPlatform\Admin\Students\StudentsMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/instructors', 'controller' => CourseHub\WebPlatform\Admin\Instructors\InstructorsController::class, 'middleware' => CourseHub\WebPlatform\Admin\Instructors\InstructorsMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/users', 'controller' => CourseHub\WebPlatform\Admin\Users\UsersController::class, 'middleware' => CourseHub\WebPlatform\Admin\Users\UsersMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/enrollments', 'controller' => CourseHub\WebPlatform\Admin\Enrollments\EnrollmentsController::class, 'middleware' => CourseHub\WebPlatform\Admin\Enrollments\EnrollmentsMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/orders', 'controller' => CourseHub\WebPlatform\Admin\Orders\OrdersController::class, 'middleware' => CourseHub\WebPlatform\Admin\Orders\OrdersMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/payments', 'controller' => CourseHub\WebPlatform\Admin\Payments\PaymentsController::class, 'middleware' => CourseHub\WebPlatform\Admin\Payments\PaymentsMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/coupons', 'controller' => CourseHub\WebPlatform\Admin\Coupons\CouponsController::class, 'middleware' => CourseHub\WebPlatform\Admin\Coupons\CouponsMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/reports', 'controller' => CourseHub\WebPlatform\Admin\Reports\ReportsController::class, 'middleware' => CourseHub\WebPlatform\Admin\Reports\ReportsMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/settings', 'controller' => CourseHub\WebPlatform\Admin\Settings\SettingsController::class, 'middleware' => CourseHub\WebPlatform\Admin\Settings\SettingsMiddleware::class],
    ['method' => 'GET', 'path' => '/admin/contact-messages', 'controller' => CourseHub\WebPlatform\Admin\ContactMessages\ContactMessagesController::class, 'middleware' => CourseHub\WebPlatform\Admin\ContactMessages\ContactMessagesMiddleware::class],
];
