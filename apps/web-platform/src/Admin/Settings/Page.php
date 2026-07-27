<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Security\Csrf;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class AdminSettingsPage
{
    public static function render(array $settings, array $gateways, string $message = '', bool $success = true): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alert = $message !== '' ? '<div class="form-alert ' . ($success ? 'success' : 'error') . '">' . $e($message) . '</div>' : '';

        $esewa = is_array($gateways['esewa'] ?? null) ? $gateways['esewa'] : [];
        $khalti = is_array($gateways['khalti'] ?? null) ? $gateways['khalti'] : [];
        $gatewayCard = static function (string $name, array $state, string $accent, string $field) use ($e): string {
            $configured = ($state['configured'] ?? false) === true;
            $enabled = ($state['enabled'] ?? false) === true;
            $available = ($state['available'] ?? false) === true;
            $mode = ucfirst((string) ($state['mode'] ?? 'sandbox'));
            $identifier = trim((string) ($state['merchant_identifier'] ?? ''));
            $product = trim((string) ($state['product_code'] ?? ''));
            $status = $available ? 'Available to students' : ($configured ? 'Configured but disabled' : 'Credentials missing');
            $checked = $enabled ? ' checked' : '';
            $disabled = $configured ? '' : ' disabled';
            $meta = $identifier !== '' ? $identifier : ($product !== '' ? $product : 'Not supplied');

            return '<article class="metric-card ' . $e($accent) . '"><div class="metric-top"><span>' . $e($name) . '</span><i></i></div>'
                . '<strong>' . $e($status) . '</strong><small>' . $e($mode) . ' · Merchant ' . $e($meta) . '</small>'
                . '<label class="check-line"><input type="checkbox" name="' . $e($field) . '" value="1"' . $checked . $disabled . '> Enable for student checkout</label></article>';
        };

        $commission = $settings['platform_commission_rate'] ?? '20.00';
        $platformForm = '<form class="admin-settings-form" method="post">' . Csrf::field() . '<input type="hidden" name="action" value="save_platform">'
            . '<section class="data-card"><div class="data-card-head"><div><span>PLATFORM BUSINESS</span><h3>Identity and commission</h3></div><span class="secure-pill">Admin controlled</span></div>'
            . '<div class="admin-settings-grid"><label>Platform name<input name="values[site_name]" maxlength="100" value="' . $e($settings['site_name'] ?? '') . '"></label>'
            . '<label>Support email<input type="email" name="values[site_email]" maxlength="150" value="' . $e($settings['site_email'] ?? '') . '"></label>'
            . '<label>Support phone<input name="values[site_phone]" maxlength="30" value="' . $e($settings['site_phone'] ?? '') . '"></label>'
            . '<label>Platform address<input name="values[site_address]" maxlength="500" value="' . $e($settings['site_address'] ?? '') . '"></label>'
            . '<label>Platform commission (%)<input type="number" min="0" max="100" step="0.01" name="values[platform_commission_rate]" value="' . $e($commission) . '" required><small>Deducted before instructor earnings become available.</small></label>'
            . '<label>eSewa merchant identifier<input name="values[esewa_id]" maxlength="150" value="' . $e($settings['esewa_id'] ?? '') . '" placeholder="Merchant wallet or account label"></label>'
            . '<label>Khalti merchant identifier<input name="values[khalti_id]" maxlength="150" value="' . $e($settings['khalti_id'] ?? '') . '" placeholder="Merchant dashboard label"></label>'
            . '<label>Bank name<input name="values[bank_name]" maxlength="150" value="' . $e($settings['bank_name'] ?? '') . '"></label>'
            . '<label>Bank account name<input name="values[bank_account_name]" maxlength="150" value="' . $e($settings['bank_account_name'] ?? '') . '"></label>'
            . '<label>Bank account number<input name="values[bank_account_number]" maxlength="100" value="' . $e($settings['bank_account_number'] ?? '') . '"></label>'
            . '<label class="wide">Manual payment instructions<textarea name="values[payment_instructions]" rows="4" maxlength="3000">' . $e($settings['payment_instructions'] ?? '') . '</textarea></label></div>'
            . '<div class="admin-settings-submit"><button class="portal-button" type="submit">Save platform settings</button></div></section></form>';

        $gatewayForm = '<form method="post">' . Csrf::field() . '<input type="hidden" name="action" value="save_gateways">'
            . '<section class="data-card"><div class="data-card-head"><div><span>MERCHANT CONNECTIONS</span><h3>Payment gateway availability</h3></div><span class="secure-pill">Secrets remain in .env</span></div>'
            . '<div class="metric-grid">' . $gatewayCard('eSewa', $esewa, 'teal', 'esewa_enabled') . $gatewayCard('Khalti', $khalti, 'violet', 'khalti_enabled') . '</div>'
            . '<div class="payment-note"><span>i</span><p>Register and complete business KYC with each provider first. Put the issued merchant credentials in the server environment, restart the payment service, then enable the gateway here. Secret keys are never stored in this page or the database.</p></div>'
            . '<button class="portal-button" type="submit">Save gateway availability</button></section></form>';

        $flow = '<section class="data-card"><div class="data-card-head"><div><span>SETTLEMENT MODEL</span><h3>How instructor money moves</h3></div></div>'
            . '<div class="security-list"><article><i>1</i><div><strong>Student pays the platform merchant</strong><p>The verified payment is credited to the Admin merchant account.</p></div><span>Gateway</span></article>'
            . '<article><i>2</i><div><strong>Commission is split in the internal ledger</strong><p>The platform commission and each instructor amount are calculated from verified order items.</p></div><span>Automatic</span></article>'
            . '<article><i>3</i><div><strong>Instructor requests withdrawal</strong><p>Available earnings are reserved so the same sale cannot be withdrawn twice.</p></div><span>Protected</span></article>'
            . '<article><i>4</i><div><strong>Admin transfers and records the reference</strong><p>Use the merchant dashboard or bank transfer, then mark the withdrawal paid with the real transaction reference.</p></div><span>Audited</span></article></div>'
            . '<div class="payment-note"><span>!</span><p>The standard public eSewa and Khalti checkout APIs do not provide a general marketplace split-payout endpoint. Fully automatic instructor transfers require a separate provider-approved disbursement contract and API credentials.</p></div></section>';

        return PortalPage::render('admin', 'Settings', $alert . '<div class="settings-stack">' . $gatewayForm . $platformForm . $flow . '</div>');
    }
}
