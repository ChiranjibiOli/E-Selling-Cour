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
                ['title' => 'Do I buy every course separately?', 'body' => '<p>Yes. CourseHub uses <strong>course-specific purchases</strong>, not a platform-wide subscription. Buying one course never unlocks another course, and an owned course cannot be bought again.</p>'],
                ['title' => 'How does lifetime access work?', 'body' => '<p>After payment is verified, CourseHub creates an active lifetime enrollment for that Student and course. CourseHub does not provide a Student access-removal request after purchase. Refunds and serious administrative policy actions are handled separately.</p>'],
                ['title' => 'Can I preview a course before buying?', 'body' => '<p>Course pages show the level, language, Instructor, description, price, curriculum, lesson duration, and lessons explicitly marked as public previews.</p>'],
                ['title' => 'How are automatic payments verified?', 'body' => '<p>CourseHub initiates the enabled merchant checkout and then verifies the provider response from the server. The payment status, order reference and exact amount must match before lifetime access is activated.</p>'],
                ['title' => 'Can I still use manual payment?', 'body' => '<p>Yes. Manual payment remains a fallback. The Student submits a transaction reference and private receipt, and Admin verifies the order, amount and proof.</p>'],
                ['title' => 'Are eSewa and Khalti available?', 'body' => '<p>They become available after Admin configures verified merchant credentials and enables the provider. Sandbox credentials are for testing; production requires live merchant approval and live keys.</p>'],
                ['title' => 'Can course videos be downloaded?', 'body' => '<p>Protected lesson media is intended for in-platform streaming. Signed URLs, private storage, and enrollment checks can discourage direct downloading, although no ordinary website can completely prevent screen recording.</p>'],
                ['title' => 'How do instructors join?', 'body' => '<p>Instructors submit identity, teaching, expertise, biography, subject, and professional-profile information. Admin reviews the application before the Instructor studio becomes active.</p>'],
                ['title' => 'How does course approval work?', 'body' => '<p>Instructor drafts stay private. A submitted course enters the Admin review queue. Approval publishes it; rejection returns it with a review note. Editing a published course sends the changed version back for review.</p>'],
                ['title' => 'Who can leave a course review?', 'body' => '<p>Only a Student with an active verified enrollment can create a review. Each Student may keep one review per course and may update or remove their own review.</p>'],
                ['title' => 'Can I remove a purchased course?', 'body' => '<p>No Student access-removal request is provided after purchase. The course remains in My Courses as lifetime access. A financial refund or exceptional policy action follows its own recorded process.</p>'],
                ['title' => 'How do instructors receive earnings?', 'body' => '<p>Every verified paid order item records the platform commission and Instructor net amount. CourseHub then queues the net amount for a configured payout API. Without a provider-approved disbursement connection, the approved payout remains visible for Admin settlement and is not falsely marked paid.</p>'],
                ['title' => 'How do I get support?', 'body' => '<p>Use the <a href="/contact">Contact page</a>. Public support messages enter the Admin support queue and remain linked to an auditable status.</p>'],
            ],
        );
    }
}
