<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class InstructorBankDetailsPage
{
    public static function render(array $details, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $content = $alert . '<div class="panel-split"><section class="data-card"><div class="data-card-head"><div><span>PAYOUT DESTINATION</span><h3>Bank and wallet details</h3></div><span class="secure-pill">Private</span></div><form class="panel-form" method="post" action="/instructor/bank-details">' . Csrf::field()
            . '<div class="field-grid"><label>Bank name<input name="bank_name" maxlength="150" value="' . $e($details['bank_name'] ?? '') . '" placeholder="Bank name"></label><label>Branch name<input name="branch_name" maxlength="150" value="' . $e($details['branch_name'] ?? '') . '" placeholder="Branch"></label></div>'
            . '<div class="field-grid"><label>Account holder<input name="account_name" maxlength="150" value="' . $e($details['account_name'] ?? '') . '" placeholder="Account name"></label><label>Account number<input name="account_number" maxlength="100" value="' . $e($details['account_number'] ?? '') . '" placeholder="Account number"></label></div>'
            . '<div class="field-grid"><label>eSewa number<input name="esewa_number" maxlength="30" value="' . $e($details['esewa_number'] ?? '') . '" placeholder="98XXXXXXXX"></label><label>Khalti number<input name="khalti_number" maxlength="30" value="' . $e($details['khalti_number'] ?? '') . '" placeholder="98XXXXXXXX"></label></div>'
            . '<label>Stored QR filename<input name="qr_image" maxlength="255" value="' . $e($details['qr_image'] ?? '') . '" placeholder="payout-qr.png"><small>The private media uploader must create this filename in production.</small></label><button class="portal-button" type="submit">Save payout details</button></form></section>'
            . '<aside class="trust-card"><span>SECURITY NOTE</span><h3>Financial details are never public.</h3><p>Only your instructor account and authorized administrators may access these payout destinations.</p><div><i>✓</i> Instructor ownership checks</div><div><i>✓</i> Stored outside public course data</div><div><i>✓</i> Snapshotted into withdrawal requests</div><a class="portal-button secondary full" href="/instructor/withdrawals">Open withdrawals</a></aside></div>';
        return PortalPage::render('instructor', 'Payout details', $content);
    }
}
