<?php

declare(strict_types=1);

return [
'identity-service'=>['route'=>'/api/v1/auth','implemented'=>['StudentLogin','InstructorLogin','AdminLogin','StudentRegistration','InstructorRegistration','SessionManagement','InstructorApprovalStatus'],'features'=>['StudentLogin','InstructorLogin','AdminLogin','StudentRegistration','InstructorRegistration','GoogleOAuth','ForgotPassword','ResetPassword','VerifyOtp','SessionManagement','AdminMfa','InstructorApprovalStatus']],
'catalog-service'=>['route'=>'/api/v1/courses','implemented'=>['Categories','ListCourses','CourseDetails','SearchCourses','CreateCourse','UpdateCourse','SubmitCourse','ApproveCourse','RejectCourse','PublishCourse','InstructorCourses'],'features'=>['Categories','ListCourses','CourseDetails','SearchCourses','CreateCourse','UpdateCourse','SubmitCourse','ApproveCourse','RejectCourse','PublishCourse','ArchiveCourse','InstructorCourses']],
'learning-service'=>['route'=>'/api/v1/learning','implemented'=>['ManageSections','ManageLessons','CoursePlayer','TrackProgress','CourseCompletion'],'features'=>['ManageSections','ManageLessons','CoursePlayer','PreviewLesson','TrackProgress','CourseCompletion']],
'commerce-service'=>['route'=>'/api/v1/commerce','implemented'=>['Cart','Checkout','CreateOrder','OrderHistory','PriceCalculation'],'features'=>['Cart','Checkout','CreateOrder','OrderHistory','Coupons','PriceCalculation']],
'payment-service'=>['route'=>'/api/v1/payments','implemented'=>['ManualPayment','VerifyPayment','PaymentHistory'],'features'=>['ManualPayment','UploadProof','VerifyPayment','KhaltiPayment','EsewaPayment','PaymentHistory','RefundPayment','WebhookValidation']],
'enrollment-service'=>['route'=>'/api/v1/enrollments','implemented'=>['GrantAccess','VerifyAccess','ListEnrollments'],'features'=>['GrantAccess','VerifyAccess','ListEnrollments','RevokeAccess','UnsubscribeRequest']],
'media-service'=>['route'=>'/api/v1/media','implemented'=>[],'features'=>['UploadThumbnail','UploadProfilePhoto','UploadIdentityDocument','UploadPaymentProof','UploadLessonMedia','SecureDownload']],
'notification-service'=>['route'=>'/api/v1/notifications','implemented'=>['InAppNotifications','ContactMessages'],'features'=>['InAppNotifications','EmailDelivery','OtpDelivery','InstructorAnnouncements','ContactMessages']],
'review-service'=>['route'=>'/api/v1/reviews','implemented'=>['CreateReview','UpdateReview','DeleteReview','ModerateReview','CourseRating'],'features'=>['CreateReview','UpdateReview','DeleteReview','ModerateReview','CourseRating']],
'reporting-service'=>['route'=>'/api/v1/reports','implemented'=>['AdminDashboard','InstructorReports','SalesReports','Earnings','Withdrawals'],'features'=>['AdminDashboard','StudentReports','InstructorReports','SalesReports','Earnings','Withdrawals','AuditLogs']],
];
