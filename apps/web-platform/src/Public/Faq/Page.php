<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PublicInformationPage;

final class FaqPage
{
    public static function render(): Response
    {
        return PublicInformationPage::render(
            'Frequently asked questions',
            'COURSEHUB FAQ',
            'Clear answers about purchases, lifetime access, instructor approval, payments, lessons, reviews, and support.',
            [
                ['title' => 'Do I buy every course separately?', 'body' => '<p>Yes. CourseHub uses <strong>course-specific purchases</strong>, not a platform-wide subscription. Buying one course never unlocks another course.</p>'],
                ['title' => 'How does lifetime access work?', 'body' => '<p>After payment is verified, CourseHub creates an active lifetime enrollment for that student and that course. Access may later change only through a recorded refund, lawful revocation, or approved access-removal workflow.</p>'],
                ['title' => 'Can I preview a course before buying?', 'body' => '<p>Course pages show the level, language, instructor, description, price, curriculum, lesson duration, and lessons explicitly marked as public previews.</p>'],
                ['title' => 'How are manual payments verified?', 'body' => '<p>The student submits a transaction reference and private payment proof. An administrator checks the order, amount, reference, and proof before approving the payment. A browser success message alone never grants course access.</p>'],
                ['title' => 'Are eSewa and Khalti available?', 'body' => '<p>The platform has integration boundaries for both. Automatic payment should be enabled only after merchant credentials, callback URLs, signature verification, transaction lookup, and webhook protection are configured.</p>'],
                ['title' => 'Can course videos be downloaded?', 'body' => '<p>Protected lesson media is intended for in-platform streaming. Signed URLs, private storage, and enrollment checks can discourage direct downloading, although no ordinary website can completely prevent screen recording.</p>'],
                ['title' => 'How do instructors join?', 'body' => '<p>Instructors submit identity, teaching, expertise, biography, subject, and professional-profile information. An administrator reviews the application before the instructor studio becomes active.</p>'],
                ['title' => 'How does course approval work?', 'body' => '<p>Instructor drafts stay private. A submitted course enters the admin review queue. Approval publishes it; rejection returns it with a review note. Editing a published course sends the changed version back for review.</p>'],
                ['title' => 'Who can leave a course review?', 'body' => '<p>Only a student with an active verified enrollment can create a review. Each student may keep one review per course and may update or remove their own review.</p>'],
                ['title' => 'Can I request course-access removal?', 'body' => '<p>A student may submit an access-removal request during the first twelve hours after access is granted. Admin approval revokes access but does not automatically create a refund. Refund decisions follow the financial policy separately.</p>'],
                ['title' => 'How do instructors receive earnings?', 'body' => '<p>Verified paid order items create instructor-earning records after platform commission. Available earnings can be reserved in a withdrawal request and paid only after an administrator records the payout.</p>'],
                ['title' => 'How do I get support?', 'body' => '<p>Use the <a href="/contact">Contact page</a>. Public support messages enter the administrator support queue and remain linked to an auditable status.</p>'],
            ],
        );
    }
}
