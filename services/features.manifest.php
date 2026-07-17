<?php

declare(strict_types=1);

return [
'identity-service'=>['route'=>'/api/v1/auth','implemented'=>['StudentLogin','InstructorLogin','AdminLogin','SessionManagement'],'features'=>['StudentLogin','InstructorLogin','AdminLogin','StudentRegistration','InstructorRegistration','GoogleOAuth','ForgotPassword','ResetPassword','VerifyOtp','SessionManagement','AdminMfa','InstructorApprovalStatus']],
'catalog-service'=>['route'=>'/api/v1/courses','implemented'=>[],'features'=>['Categories','ListCourses','CourseDetails','SearchCourses','CreateCourse','UpdateCourse','SubmitCourse','ApproveCourse','RejectCourse','PublishCourse','ArchiveCourse','InstructorCourses']],
'learning-service'=>['route'=>'/api/v1/learning','implemented'=>[],'features'=>['ManageSections','ManageLessons','CoursePlayer','PreviewLesson','TrackProgress','CourseCompletion']],
'commerce-service'=>['route'=>'/api/v1/commerce','implemented'=>[],'features'=>['Cart','Checkout','CreateOrder','OrderHistory','Coupons','PriceCalculation']],
'payment-service'=>['route'=>'/api/v1/payments','implemented'=>[],'features'=>['ManualPayment','UploadProof','VerifyPayment','KhaltiPayment','EsewaPayment','PaymentHistory','RefundPayment','WebhookValidation']],
'enrollment-service'=>['route'=>'/api/v1/enrollments','implemented'=>[],'features'=>['GrantAccess','VerifyAccess','ListEnrollments','RevokeAccess','UnsubscribeRequest']],
'media-service'=>['route'=>'/api/v1/media','implemented'=>[],'features'=>['UploadThumbnail','UploadProfilePhoto','UploadIdentityDocument','UploadPaymentProof','UploadLessonMedia','SecureDownload']],
'notification-service'=>['route'=>'/api/v1/notifications','implemented'=>[],'features'=>['InAppNotifications','EmailDelivery','OtpDelivery','InstructorAnnouncements','ContactMessages']],
'review-service'=>['route'=>'/api/v1/reviews','implemented'=>[],'features'=>['CreateReview','UpdateReview','DeleteReview','ModerateReview','CourseRating']],
'reporting-service'=>['route'=>'/api/v1/reports','implemented'=>[],'features'=>['AdminDashboard','StudentReports','InstructorReports','SalesReports','Earnings','Withdrawals','AuditLogs']],
];
