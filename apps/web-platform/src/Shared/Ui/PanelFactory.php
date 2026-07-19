<?php

declare(strict_types=1);

namespace CourseHub\WebPlatform\Shared\Ui;

final class PanelFactory
{
    /** @return array{content:string,action:string} */
    public static function build(array $metadata, array $model): array
    {
        $floor = (string) ($metadata['floor'] ?? 'Student');
        $room = (string) ($metadata['room'] ?? 'Dashboard');
        $role = strtolower($floor);
        $description = self::description($floor, $room);
        $intro = '<section class="panel-intro"><div><span>WORKSPACE</span><h2>' . self::e($description[0]) . '</h2><p>' . self::e($description[1]) . '</p></div><div class="panel-intro-orb"><i></i><strong>' . self::e(substr($room, 0, 2)) . '</strong></div></section>';

        $content = match ($room) {
            'Cart' => self::cart(),
            'Checkout' => self::checkout(),
            'Payment' => self::payment(),
            'CoursePlayer' => self::coursePlayer(),
            'Progress' => self::progress(),
            'CurriculumBuilder', 'Lessons' => self::curriculum($room),
            'Profile' => self::profile($role),
            'BankDetails' => self::bankDetails(),
            'Settings' => self::settings(),
            'Security' => self::security(),
            'Sales', 'Reports' => self::analytics($role, $room),
            'Notifications', 'Messaging', 'ContactMessages' => self::inbox($room),
            'Coupons' => self::coupons($role),
            'Withdrawals' => self::withdrawals($role),
            'MyCourses', 'DraftCourses', 'PendingCourses', 'PublishedCourses' => self::courseCollection($room),
            'Reviews' => self::reviews(),
            'VerificationPending' => self::verificationPending(),
            default => self::managementTable($floor, $room),
        };

        return [
            'content' => $intro . $content,
            'action' => self::primaryAction($floor, $room),
        ];
    }

    /** @return array{string,string} */
    private static function description(string $floor, string $room): array
    {
        $key = $floor . '/' . $room;
        $descriptions = [
            'Student/Cart' => ['Review your learning cart', 'Confirm the courses you want before moving to secure checkout.'],
            'Student/Checkout' => ['Complete your order with confidence', 'Review pricing, discounts and payment details before submitting.'],
            'Student/Payment' => ['Secure payment workspace', 'Choose an available method and keep a clear record of the transaction.'],
            'Student/PaymentHistory' => ['Every payment in one place', 'Track pending, approved and rejected transactions with their order records.'],
            'Student/MyCourses' => ['Your personal course library', 'Open purchased courses and continue from the last completed lesson.'],
            'Student/CoursePlayer' => ['Focused course player', 'Move through the curriculum while keeping your lesson position and progress visible.'],
            'Student/Progress' => ['See your learning momentum', 'Understand completed lessons, active courses and the next useful step.'],
            'Student/Notifications' => ['Updates that matter', 'Payment, enrollment and course activity arrive in one organized inbox.'],
            'Student/Profile' => ['Manage your student identity', 'Keep your contact details, photo and account security current.'],
            'Student/Unsubscribe' => ['Manage course-access requests', 'Request access removal and follow the administrator decision clearly.'],
            'Student/Reviews' => ['Your course feedback', 'Write and manage verified reviews for courses you purchased.'],
            'Instructor/VerificationPending' => ['Application under review', 'Your teaching information is safely waiting for an administrator decision.'],
            'Instructor/CurriculumBuilder' => ['Shape the complete learning journey', 'Organize chapters, lessons, previews and learning materials in the right order.'],
            'Instructor/Lessons' => ['Manage course lessons', 'Create clear learning units and control their format, order and preview access.'],
            'Instructor/DraftCourses' => ['Private course drafts', 'Continue unfinished courses without exposing them to administrators or students.'],
            'Instructor/PendingCourses' => ['Courses awaiting review', 'Track submitted courses while the administrator verifies their quality and details.'],
            'Instructor/PublishedCourses' => ['Your live course catalog', 'Monitor approved courses that students can discover and purchase.'],
            'Instructor/Students' => ['Understand your learners', 'See enrollment and progress information only for students in your courses.'],
            'Instructor/Sales' => ['Course business performance', 'Follow verified sales, platform commission and available instructor earnings.'],
            'Instructor/Coupons' => ['Create controlled promotions', 'Offer course-specific discounts without weakening price validation.'],
            'Instructor/BankDetails' => ['Secure payout destination', 'Manage the verified bank or wallet details used for instructor payments.'],
            'Instructor/Withdrawals' => ['Request instructor payouts', 'Withdraw available earnings and follow each payout decision.'],
            'Instructor/Notifications' => ['Studio activity inbox', 'Course reviews, enrollments and payout updates stay together.'],
            'Instructor/Messaging' => ['Communicate with enrolled learners', 'Keep course questions and instructor responses organized by context.'],
            'Instructor/Profile' => ['Build instructor trust', 'Keep your expertise, biography and public teaching identity professional.'],
            'Admin/Students' => ['Student account management', 'Find students, inspect status and open authorized learning records.'],
            'Admin/Instructors' => ['Instructor operations', 'Monitor active, inactive and blocked instructors across the platform.'],
            'Admin/Users' => ['Complete user directory', 'Search every platform identity without exposing passwords or private secrets.'],
            'Admin/Enrollments' => ['Course-access control', 'Trace each enrollment to its student, course, order and verified payment.'],
            'Admin/Orders' => ['Order operations', 'Review every course order and follow its business state.'],
            'Admin/Payments' => ['Payment verification center', 'Validate manual proof and automatic transaction results before granting access.'],
            'Admin/Refunds' => ['Refund and adjustment control', 'Review financial reversals without silently changing historical records.'],
            'Admin/Withdrawals' => ['Instructor payout queue', 'Approve, reject and record completed withdrawals against available earnings.'],
            'Admin/Categories' => ['Organize course discovery', 'Maintain active categories and protect courses from unsafe deletion.'],
            'Admin/Coupons' => ['Platform promotion control', 'Manage discount limits, validity and course eligibility.'],
            'Admin/Reports' => ['Decision-ready platform reporting', 'Explore revenue, enrollment and course performance with reliable filters.'],
            'Admin/AuditLogs' => ['Accountable administrative activity', 'Trace important platform changes without exposing sensitive payloads.'],
            'Admin/Security' => ['Platform security controls', 'Review access policy, session safeguards and operational risk settings.'],
            'Admin/Settings' => ['Platform configuration', 'Manage commercial and public settings from one controlled workspace.'],
            'Admin/ContactMessages' => ['Support message center', 'Turn public contact requests into organized follow-up work.'],
            'Admin/Notifications' => ['Administrative alerts', 'See approvals, financial activity and platform events that need attention.'],
        ];

        return $descriptions[$key] ?? [
            'A clear ' . strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $room) ?? $room) . ' workspace',
            'Review the information, use focused actions and keep the workflow easy to understand.',
        ];
    }

    private static function cart(): string
    {
        return self::metrics([['0', 'Courses in cart', 'blue'], ['NPR 0', 'Current total', 'violet'], ['0', 'Coupon savings', 'teal'], ['Lifetime', 'Access type', 'orange']])
            . '<div class="panel-split panel-split-wide"><section class="data-card"><div class="data-card-head"><div><span>CART ITEMS</span><h3>Your selected courses</h3></div><button class="text-button" type="button" data-demo-action="Browse courses from the public catalog.">Browse catalog</button></div>'
            . self::emptyState('Your cart is waiting', 'Add a published course to compare pricing and continue to checkout.', 'Explore courses', '/courses') . '</section>'
            . '<aside class="summary-card accent-card"><span>ORDER SUMMARY</span><div class="summary-row"><span>Subtotal</span><strong>NPR 0.00</strong></div><div class="summary-row"><span>Discount</span><strong>− NPR 0.00</strong></div><div class="coupon-line"><input placeholder="Coupon code"><button type="button" data-demo-action="Coupon validation connects here.">Apply</button></div><div class="summary-total"><span>Total</span><strong>NPR 0.00</strong></div><a class="portal-button full" href="/student/checkout">Continue to checkout</a></aside></div>';
    }

    private static function checkout(): string
    {
        return '<div class="checkout-layout"><section class="data-card"><div class="stepper"><span class="done"><i>1</i>Cart</span><b></b><span class="active"><i>2</i>Checkout</span><b></b><span><i>3</i>Payment</span></div><div class="data-card-head"><div><span>BILLING DETAILS</span><h3>Confirm your information</h3></div></div>'
            . '<div class="panel-form"><div class="field-grid"><label>Full name<input value="" placeholder="Your full name"></label><label>Email address<input type="email" placeholder="name@example.com"></label></div><label>Phone number<input placeholder="+977"></label><label class="check-line"><input type="checkbox" checked> Send payment updates to my account inbox</label></div></section>'
            . '<aside class="summary-card"><span>YOUR ORDER</span><div class="order-mini"><i>CH</i><div><strong>No course selected</strong><small>Return to the catalog to choose a course</small></div></div><div class="summary-total"><span>Payable total</span><strong>NPR 0.00</strong></div><a class="portal-button full" href="/student/payment">Choose payment method</a></aside></div>';
    }

    private static function payment(): string
    {
        return self::metrics([['Protected', 'Server pricing', 'blue'], ['Verified', 'Admin approval', 'violet'], ['Lifetime', 'Course access', 'teal'], ['Recorded', 'Transaction history', 'orange']])
            . '<section class="data-card"><div class="data-card-head"><div><span>PAYMENT METHOD</span><h3>Choose how you want to pay</h3></div><span class="secure-pill">Secure workflow</span></div><div class="payment-methods"><button class="payment-method active" type="button"><i>QR</i><span><strong>Manual QR payment</strong><small>Upload proof for admin verification</small></span><b>✓</b></button><button class="payment-method" type="button"><i>eS</i><span><strong>eSewa</strong><small>Gateway integration boundary</small></span></button><button class="payment-method" type="button"><i>Kh</i><span><strong>Khalti</strong><small>Gateway integration boundary</small></span></button></div><div class="payment-note"><span>i</span><p>Course access is granted only after the server verifies a successful payment. A browser success page alone never creates enrollment.</p></div></section>';
    }

    private static function coursePlayer(): string
    {
        return '<section class="player-shell"><div class="player-main"><div class="video-stage"><div class="video-empty"><span>▶</span><strong>Select a lesson to begin</strong><small>Protected learning content loads after enrollment verification.</small></div></div><div class="player-meta"><span>COURSE PLAYER</span><h2>Your active lesson will appear here</h2><div><button type="button" class="portal-button secondary">← Previous</button><button type="button" class="portal-button">Mark complete & continue →</button></div></div></div><aside class="curriculum-panel"><div><span>CURRICULUM</span><strong>Course lessons</strong></div><div class="curriculum-empty"><i>0%</i><p>Choose an enrolled course to load its chapters.</p></div></aside></section>';
    }

    private static function progress(): string
    {
        return self::metrics([['0%', 'Overall progress', 'blue'], ['0', 'Lessons completed', 'violet'], ['0', 'Active courses', 'teal'], ['0h', 'Learning time', 'orange']])
            . '<div class="panel-split"><section class="data-card"><div class="data-card-head"><div><span>LEARNING ACTIVITY</span><h3>Progress across your courses</h3></div><select><option>Last 30 days</option></select></div><div class="chart-empty"><div class="chart-bars"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div><p>Learning activity will build here as lessons are completed.</p></div></section><aside class="data-card"><div class="data-card-head"><div><span>NEXT MILESTONE</span><h3>Keep momentum visible</h3></div></div><div class="ring-progress"><span>0%</span></div><p class="muted-copy">Start an enrolled course to create your first completion milestone.</p></aside></div>';
    }

    private static function curriculum(string $room): string
    {
        $lesson = $room === 'Lessons';
        return self::metrics([['0', 'Course sections', 'blue'], ['0', 'Total lessons', 'violet'], ['0m', 'Total duration', 'teal'], ['0', 'Free previews', 'orange']])
            . '<div class="curriculum-builder"><section class="builder-outline"><div class="data-card-head"><div><span>' . ($lesson ? 'LESSON LIBRARY' : 'COURSE OUTLINE') . '</span><h3>' . ($lesson ? 'Manage learning units' : 'Build the curriculum') . '</h3></div><button class="portal-button" type="button" data-demo-action="Create a course first, then add sections.">+ Add ' . ($lesson ? 'lesson' : 'section') . '</button></div>'
            . self::emptyState($lesson ? 'No lessons created' : 'Your outline is empty', $lesson ? 'Add video, text or link lessons to an owned course.' : 'Create the first section, then arrange lessons inside it.', 'Open my courses', '/instructor/courses') . '</section><aside class="builder-tips"><span>QUALITY GUIDE</span><h3>A strong curriculum stays easy to scan.</h3><ul><li>Use short, outcome-focused lesson titles.</li><li>Keep each section centered on one goal.</li><li>Mark only useful introductory lessons as previews.</li><li>Check duration and ordering before submission.</li></ul></aside></div>';
    }

    private static function profile(string $role): string
    {
        $isInstructor = $role === 'instructor';
        return '<div class="profile-layout"><aside class="profile-card"><div class="profile-photo">CH<button type="button">+</button></div><h3>Your profile</h3><p>' . ($isInstructor ? 'This identity appears beside your published courses.' : 'Keep your learning account recognizable and current.') . '</p><span class="completion-pill">Profile completion · 40%</span></aside><section class="data-card"><div class="data-card-head"><div><span>PERSONAL DETAILS</span><h3>Account information</h3></div></div><div class="panel-form"><div class="field-grid"><label>Full name<input placeholder="Full name"></label><label>Email address<input type="email" placeholder="name@example.com" disabled></label></div><label>Phone number<input placeholder="+977"></label>'
            . ($isInstructor ? '<label>Professional biography<textarea rows="6" placeholder="Explain your expertise and teaching experience"></textarea></label><label>Areas of expertise<input placeholder="Web development, design, business..."></label>' : '<label>Short biography<textarea rows="4" placeholder="Tell us a little about yourself"></textarea></label>')
            . '<div class="form-actions"><button class="portal-button" type="button" data-demo-action="Profile service integration will save these details.">Save profile</button><button class="portal-button secondary" type="button">Change password</button></div></div></section></div>';
    }

    private static function bankDetails(): string
    {
        return '<div class="panel-split"><section class="data-card"><div class="data-card-head"><div><span>PAYOUT DESTINATION</span><h3>Bank and wallet details</h3></div><span class="secure-pill">Private</span></div><div class="panel-form"><div class="field-grid"><label>Bank name<input placeholder="Bank name"></label><label>Branch name<input placeholder="Branch"></label></div><div class="field-grid"><label>Account holder<input placeholder="Account name"></label><label>Account number<input placeholder="Account number"></label></div><div class="field-grid"><label>eSewa number<input placeholder="98XXXXXXXX"></label><label>Khalti number<input placeholder="98XXXXXXXX"></label></div><button class="portal-button" type="button" data-demo-action="Payout details require payment-service integration.">Save payout details</button></div></section><aside class="trust-card"><span>SECURITY NOTE</span><h3>Financial details are never public.</h3><p>Only your instructor account and authorized administrators may access payout information.</p><div><i>✓</i> Ownership checks</div><div><i>✓</i> Private file storage</div><div><i>✓</i> Audited admin access</div></aside></div>';
    }

    private static function settings(): string
    {
        return '<div class="settings-stack"><section class="data-card"><div class="data-card-head"><div><span>PLATFORM IDENTITY</span><h3>General settings</h3></div></div><div class="panel-form"><div class="field-grid"><label>Platform name<input value="CourseHub"></label><label>Support email<input type="email" placeholder="support@example.com"></label></div><div class="field-grid"><label>Default currency<select><option>NPR — Nepalese Rupee</option></select></label><label>Commission rate<input type="number" value="20"><small>Percentage retained by the platform.</small></label></div></div></section><section class="data-card"><div class="data-card-head"><div><span>PAYMENT CONFIGURATION</span><h3>Manual payment instructions</h3></div></div><div class="panel-form"><textarea rows="5" placeholder="Instructions shown during manual payment"></textarea><button class="portal-button" type="button" data-demo-action="Settings service integration will persist this configuration.">Save platform settings</button></div></section></div>';
    }

    private static function security(): string
    {
        return self::metrics([['Active', 'Session policy', 'blue'], ['Enabled', 'Login throttle', 'violet'], ['Required', 'CSRF protection', 'teal'], ['Private', 'Admin entry', 'orange']])
            . '<div class="panel-split"><section class="data-card"><div class="data-card-head"><div><span>ACCESS POLICY</span><h3>Authentication safeguards</h3></div></div><div class="security-list"><article><i>✓</i><div><strong>Role-separated portals</strong><p>Student, instructor and admin credentials are verified against the requested portal.</p></div><span>Active</span></article><article><i>✓</i><div><strong>Opaque bearer sessions</strong><p>Only token hashes are stored by the identity service.</p></div><span>Active</span></article><article><i>✓</i><div><strong>Login throttling</strong><p>Repeated failures produce a temporary lockout.</p></div><span>Active</span></article></div></section><aside class="data-card"><div class="data-card-head"><div><span>ADMIN ENTRY</span><h3>Restricted control room</h3></div></div><p class="muted-copy">Keep the admin path and additional access code private. Replace all example secrets before deployment.</p><button class="portal-button secondary" type="button" data-demo-action="Security configuration is controlled through environment variables.">Review configuration</button></aside></div>';
    }

    private static function analytics(string $role, string $room): string
    {
        $admin = $role === 'admin';
        return self::metrics([
            ['NPR 0', $admin ? 'Verified revenue' : 'Gross sales', 'blue'],
            ['NPR 0', $admin ? 'Platform earnings' : 'Available earnings', 'violet'],
            ['0', 'Paid orders', 'teal'],
            ['0%', 'Conversion', 'orange'],
        ]) . '<div class="panel-split panel-split-wide"><section class="data-card"><div class="data-card-head"><div><span>PERFORMANCE</span><h3>' . self::e($room) . ' trend</h3></div><div class="filter-tabs"><button class="active">30 days</button><button>90 days</button><button>Year</button></div></div><div class="analytics-chart"><div class="chart-grid"><i></i><i></i><i></i><i></i></div><svg viewBox="0 0 800 230" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="area" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#6c63ff" stop-opacity=".45"/><stop offset="1" stop-color="#6c63ff" stop-opacity="0"/></linearGradient></defs><path d="M0 205 C80 190 120 210 190 155 S310 175 390 112 S510 142 590 70 S720 98 800 28 L800 230 L0 230Z" fill="url(#area)"/><path d="M0 205 C80 190 120 210 190 155 S310 175 390 112 S510 142 590 70 S720 98 800 28" fill="none" stroke="#6c63ff" stroke-width="5"/></svg><div class="chart-no-data">Live values appear after verified sales</div></div></section><aside class="data-card"><div class="data-card-head"><div><span>BREAKDOWN</span><h3>Revenue quality</h3></div></div><div class="donut"><span>0<small>orders</small></span></div><div class="legend"><span><i class="blue"></i>Paid</span><span><i class="violet"></i>Pending</span><span><i class="orange"></i>Refunded</span></div></aside></div>';
    }

    private static function inbox(string $room): string
    {
        $label = $room === 'Messaging' ? 'Conversations' : ($room === 'ContactMessages' ? 'Support requests' : 'Activity updates');
        return self::metrics([['0', 'Unread', 'blue'], ['0', 'Today', 'violet'], ['0', 'Needs action', 'teal'], ['0', 'Archived', 'orange']])
            . '<section class="inbox-shell"><aside class="inbox-folders"><button class="active"><span>Inbox</span><b>0</b></button><button><span>Unread</span><b>0</b></button><button><span>Archived</span></button><button><span>All activity</span></button></aside><div class="inbox-list"><div class="data-card-head"><div><span>INBOX</span><h3>' . self::e($label) . '</h3></div><label class="table-search">⌕ <input placeholder="Search messages"></label></div>' . self::emptyState('Nothing waiting for you', 'New messages and role-specific notifications will appear here automatically.', '', '') . '</div></section>';
    }

    private static function coupons(string $role): string
    {
        return self::metrics([['0', 'Active coupons', 'blue'], ['0', 'Redemptions', 'violet'], ['NPR 0', 'Discount given', 'teal'], ['0', 'Expired', 'orange']])
            . '<div class="panel-split panel-split-wide"><section class="data-card"><div class="data-card-head"><div><span>COUPON LIBRARY</span><h3>' . ($role === 'admin' ? 'Platform promotions' : 'My course promotions') . '</h3></div><button class="portal-button" type="button" data-demo-action="Coupon creation connects to the commerce service.">+ Create coupon</button></div>' . self::emptyState('No coupons created', 'Create a controlled fixed or percentage discount with dates and usage limits.', '', '') . '</section><aside class="data-card"><div class="data-card-head"><div><span>GUARDRAILS</span><h3>Discount rules</h3></div></div><ul class="clean-list"><li>Server-calculated totals</li><li>Course ownership validation</li><li>Usage and expiry enforcement</li><li>Maximum discount protection</li></ul></aside></div>';
    }

    private static function withdrawals(string $role): string
    {
        $admin = $role === 'admin';
        return self::metrics([['NPR 0', $admin ? 'Pending requests' : 'Available balance', 'blue'], ['NPR 0', $admin ? 'Approved value' : 'Reserved balance', 'violet'], ['0', 'Requests', 'teal'], ['NPR 0', 'Paid total', 'orange']])
            . '<section class="data-card"><div class="data-card-head"><div><span>PAYOUT WORKFLOW</span><h3>' . ($admin ? 'Instructor withdrawal queue' : 'Your withdrawal requests') . '</h3></div>' . ($admin ? '' : '<button class="portal-button" type="button" data-demo-action="A request can be created after verified earnings become available.">Request withdrawal</button>') . '</div>' . self::emptyState($admin ? 'No payout requests need review' : 'No withdrawals requested', $admin ? 'New instructor requests will appear with reserved earnings and payout details.' : 'Available verified earnings can be requested through your saved payout method.', '', '') . '</section>';
    }

    private static function courseCollection(string $room): string
    {
        $status = match ($room) {
            'DraftCourses' => 'draft',
            'PendingCourses' => 'pending',
            'PublishedCourses' => 'published',
            default => 'all',
        };
        return self::metrics([['0', 'All courses', 'blue'], ['0', 'Draft', 'violet'], ['0', 'Pending', 'teal'], ['0', 'Published', 'orange']])
            . '<section class="data-card"><div class="data-card-head"><div><span>' . strtoupper($status) . ' COURSES</span><h3>' . ($status === 'all' ? 'Your course portfolio' : ucfirst($status) . ' course workspace') . '</h3></div><div class="data-card-actions"><label class="table-search">⌕ <input placeholder="Search courses"></label><a class="portal-button" href="/instructor/courses/create">+ Create course</a></div></div>'
            . self::emptyState('No ' . ($status === 'all' ? '' : $status . ' ') . 'courses yet', $status === 'draft' ? 'Save unfinished work privately and return whenever you are ready.' : 'Courses matching this status will appear in a consistent card grid.', 'Create course', '/instructor/courses/create') . '</section>';
    }

    private static function reviews(): string
    {
        return self::metrics([['0', 'Reviews written', 'blue'], ['—', 'Average rating', 'violet'], ['0', 'Visible', 'teal'], ['0', 'Courses to review', 'orange']])
            . '<section class="data-card"><div class="data-card-head"><div><span>VERIFIED FEEDBACK</span><h3>Your course reviews</h3></div></div>' . self::emptyState('You have not reviewed a course', 'After purchasing and learning from a course, share one verified review to help other students.', 'Open my courses', '/student/my-courses') . '</section>';
    }

    private static function verificationPending(): string
    {
        return '<section class="verification-card"><div class="verification-visual"><span>✓</span><i></i><i></i></div><div><span>APPLICATION RECEIVED</span><h2>Your instructor profile is in the review queue.</h2><p>An administrator will verify the submitted identity and teaching information. Your studio becomes available only after approval.</p><div class="verification-steps"><span class="done"><i>1</i>Application submitted</span><b></b><span class="active"><i>2</i>Admin review</span><b></b><span><i>3</i>Studio access</span></div><a class="portal-button secondary" href="/contact">Contact support</a></div></section>';
    }

    private static function managementTable(string $floor, string $room): string
    {
        $labels = preg_split('/(?=[A-Z])/', $room, -1, PREG_SPLIT_NO_EMPTY) ?: [$room];
        $subject = strtolower(implode(' ', $labels));
        $columns = match (true) {
            $room === 'Students' && $floor === 'Instructor' => ['Student', 'Course', 'Enrolled', 'Progress', 'Status', 'Action'],
            $room === 'Students' => ['Student', 'Email', 'Enrollments', 'Progress', 'Status', 'Action'],
            $room === 'Instructors' => ['Instructor', 'Courses', 'Students', 'Earnings', 'Status', 'Action'],
            $room === 'Users' => ['User', 'Role', 'Joined', 'Last active', 'Status', 'Action'],
            $room === 'Enrollments' => ['Enrollment', 'Student', 'Course', 'Payment', 'Status', 'Granted'],
            $room === 'Orders' => ['Order', 'Student', 'Items', 'Total', 'Status', 'Created'],
            $room === 'Payments' => ['Payment', 'Order', 'Method', 'Amount', 'Status', 'Action'],
            $room === 'Refunds' => ['Request', 'Student', 'Order', 'Amount', 'Status', 'Action'],
            $room === 'Categories' => ['Category', 'Slug', 'Courses', 'Visibility', 'Updated', 'Action'],
            $room === 'AuditLogs' => ['Event', 'Actor', 'Resource', 'IP context', 'Date', 'Details'],
            $room === 'Unsubscribe' => ['Request', 'Course', 'Reason', 'Deadline', 'Status', 'Action'],
            $room === 'PaymentHistory' => ['Transaction', 'Order', 'Method', 'Amount', 'Status', 'Date'],
            default => ['Record', 'Owner', 'Type', 'Updated', 'Status', 'Action'],
        };
        $head = '';
        foreach ($columns as $column) {
            $head .= '<th>' . self::e($column) . '</th>';
        }

        $metrics = match ($room) {
            'Students' => [['0', 'Total students', 'blue'], ['0', 'Active', 'violet'], ['0', 'New this month', 'teal'], ['0', 'Blocked', 'orange']],
            'Instructors' => [['0', 'Total instructors', 'blue'], ['0', 'Active', 'violet'], ['0', 'Published courses', 'teal'], ['0', 'Blocked', 'orange']],
            'Users' => [['0', 'All users', 'blue'], ['0', 'Students', 'violet'], ['0', 'Instructors', 'teal'], ['0', 'Blocked', 'orange']],
            'Enrollments' => [['0', 'Enrollments', 'blue'], ['0', 'Active access', 'violet'], ['0', 'Revoked', 'teal'], ['0', 'Today', 'orange']],
            'Orders' => [['0', 'All orders', 'blue'], ['0', 'Paid', 'violet'], ['0', 'Pending', 'teal'], ['NPR 0', 'Order value', 'orange']],
            'Payments' => [['0', 'All payments', 'blue'], ['0', 'Needs verification', 'violet'], ['0', 'Paid', 'teal'], ['NPR 0', 'Verified value', 'orange']],
            'Categories' => [['0', 'Categories', 'blue'], ['0', 'Active', 'violet'], ['0', 'Courses', 'teal'], ['0', 'Inactive', 'orange']],
            default => [['0', 'Total records', 'blue'], ['0', 'Active', 'violet'], ['0', 'Needs action', 'teal'], ['0', 'This month', 'orange']],
        };

        return self::metrics($metrics) . '<section class="data-card"><div class="data-card-head"><div><span>' . strtoupper(self::e($subject)) . '</span><h3>Manage ' . self::e($subject) . '</h3></div><div class="data-card-actions"><label class="table-search">⌕ <input placeholder="Search records"></label><button class="filter-button" type="button">Filters <b>0</b></button></div></div><div class="table-wrap"><table><thead><tr>' . $head . '</tr></thead><tbody><tr class="empty-row"><td colspan="' . count($columns) . '"><div><span>⌁</span><strong>No ' . self::e($subject) . ' to display</strong><small>Connected records will appear here with secure role and ownership filtering.</small></div></td></tr></tbody></table></div><div class="table-foot"><span>Showing 0 records</span><div><button disabled>←</button><button class="active">1</button><button disabled>→</button></div></div></section>';
    }

    /** @param array<int, array{string,string,string}> $items */
    private static function metrics(array $items): string
    {
        $html = '<section class="metric-grid">';
        foreach ($items as [$value, $label, $color]) {
            $html .= '<article class="metric-card ' . self::e($color) . '"><div class="metric-top"><span>' . self::e($label) . '</span><i></i></div><strong>' . self::e($value) . '</strong><small><b>•</b> Live business data</small></article>';
        }
        return $html . '</section>';
    }

    private static function emptyState(string $title, string $text, string $label, string $href): string
    {
        $action = $label !== '' && $href !== '' ? '<a class="portal-button secondary" href="' . self::e($href) . '">' . self::e($label) . '</a>' : '';
        return '<div class="rich-empty"><div class="empty-art"><i></i><i></i><span>CH</span></div><h3>' . self::e($title) . '</h3><p>' . self::e($text) . '</p>' . $action . '</div>';
    }

    private static function primaryAction(string $floor, string $room): string
    {
        if ($floor === 'Instructor' && in_array($room, ['DraftCourses', 'PendingCourses', 'PublishedCourses', 'Lessons', 'CurriculumBuilder'], true)) {
            return '<a class="portal-button" href="/instructor/courses/create">+ Create course</a>';
        }
        if ($floor === 'Admin' && $room === 'Categories') {
            return '<button class="portal-button" type="button" data-demo-action="Category creation connects to the catalog service.">+ New category</button>';
        }
        if ($floor === 'Admin' && $room === 'Reports') {
            return '<button class="portal-button" type="button" data-demo-action="Report export will use the reporting service.">Export report</button>';
        }
        return '<span class="live-status"><i></i> Workspace ready</span>';
    }

    private static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
