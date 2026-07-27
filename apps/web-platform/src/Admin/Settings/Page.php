<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class AdminSettingsPage
{
    public static function render(array $settings, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';
        $commission = $settings['platform_commission_rate'] ?? '20.00';

        $platformForm = '<form class="admin-settings-form" method="post">' . Csrf::field() . '<input type="hidden" name="action" value="save_platform">'
            . '<section class="data-card"><div class="data-card-head"><div><span>PLATFORM BUSINESS</span><h3>Identity, commission and manual payment</h3></div><span class="secure-pill">Admin controlled</span></div>'
            . '<div class="admin-settings-grid"><label>Platform name<input name="values[site_name]" maxlength="100" value="' . $e($settings['site_name'] ?? '') . '"></label>'
            . '<label>Support email<input type="email" name="values[site_email]" maxlength="150" value="' . $e($settings['site_email'] ?? '') . '"></label>'
            . '<label>Support phone<input name="values[site_phone]" maxlength="30" value="' . $e($settings['site_phone'] ?? '') . '"></label>'
            . '<label>Platform address<input name="values[site_address]" maxlength="500" value="' . $e($settings['site_address'] ?? '') . '"></label>'
            . '<label>Platform commission (%)<input type="number" min="0" max="100" step="0.01" name="values[platform_commission_rate]" value="' . $e($commission) . '" required><small>Calculated after Admin verifies a manual payment.</small></label>'
            . '<label>Manual eSewa account<input name="values[esewa_id]" maxlength="150" value="' . $e($settings['esewa_id'] ?? '') . '" placeholder="Wallet number or account label"></label>'
            . '<label>Manual Khalti account<input name="values[khalti_id]" maxlength="150" value="' . $e($settings['khalti_id'] ?? '') . '" placeholder="Wallet number or account label"></label>'
            . '<label>Bank name<input name="values[bank_name]" maxlength="150" value="' . $e($settings['bank_name'] ?? '') . '"></label>'
            . '<label>Bank account name<input name="values[bank_account_name]" maxlength="150" value="' . $e($settings['bank_account_name'] ?? '') . '"></label>'
            . '<label>Bank account number<input name="values[bank_account_number]" maxlength="100" value="' . $e($settings['bank_account_number'] ?? '') . '"></label>'
            . '<label class="wide">Manual payment instructions<textarea name="values[payment_instructions]" rows="5" maxlength="3000" placeholder="Explain where the Student should pay and which details must appear in the receipt.">' . $e($settings['payment_instructions'] ?? '') . '</textarea></label></div>'
            . '<div class="payment-note"><span>i</span><p>Automatic eSewa and Khalti checkout is disabled. Students pay manually, submit the real transaction reference and upload a receipt for Admin verification.</p></div>'
            . '<div class="admin-settings-submit"><button class="portal-button" type="submit">Save platform settings</button></div></section></form>';

        $flow = '<section class="data-card"><div class="data-card-head"><div><span>MANUAL PAYMENT WORKFLOW</span><h3>How course access and instructor earnings are activated</h3></div></div>'
            . '<div class="security-list"><article><i>1</i><div><strong>Student creates an order</strong><p>Only courses not already owned can enter the cart and order.</p></div><span>Order</span></article>'
            . '<article><i>2</i><div><strong>Student pays outside CourseHub</strong><p>The Student uses the displayed wallet or bank information and keeps the real receipt.</p></div><span>Manual</span></article>'
            . '<article><i>3</i><div><strong>Admin verifies the proof</strong><p>The amount, transaction reference and uploaded receipt are checked before approval.</p></div><span>Review</span></article>'
            . '<article><i>4</i><div><strong>Course access and earnings activate</strong><p>Approval creates the lifetime enrollment and records the instructor amount after commission.</p></div><span>Audited</span></article></div>'
            . '<div class="payment-note"><span>!</span><p>No automatic gateway callback can activate enrollment. Only a verified manual payment or a recorded free order can create course access.</p></div></section>';

        return PortalPage::render('admin', 'Settings', $alert . '<div class="settings-stack">' . $platformForm . $flow . '</div>');
    }
}
