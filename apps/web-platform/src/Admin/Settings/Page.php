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
            $status = $available ? 'Available to Students' : ($configured ? 'Configured but disabled' : 'Credentials missing');
            $checked = $enabled ? ' checked' : '';
            $disabled = $configured ? '' : ' disabled';
            $meta = $identifier !== '' ? $identifier : ($product !== '' ? $product : 'Not supplied');

            return '<article class="metric-card ' . $e($accent) . '"><div class="metric-top"><span>' . $e($name) . '</span><i></i></div>'
                . '<strong>' . $e($status) . '</strong><small>' . $e($mode) . ' · Merchant ' . $e($meta) . '</small>'
                . '<label class="check-line"><input type="checkbox" name="' . $e($field) . '" value="1"' . $checked . $disabled . '> Enable for Student checkout</label></article>';
        };

        $commission = $settings['platform_commission_rate'] ?? '20.00';
        $platformForm = '<form class="admin-settings-form" method="post">' . Csrf::field() . '<input type="hidden" name="action" value="save_platform">'
            . '<section class="data-card"><div class="data-card-head"><div><span>PLATFORM BUSINESS</span><h3>Identity, commission and merchant accounts</h3></div><span class="secure-pill">Admin controlled</span></div>'
            . '<div class="admin-settings-grid"><label>Platform name<input name="values[site_name]" maxlength="100" value="' . $e($settings['site_name'] ?? '') . '"></label>'
            . '<label>Support email<input type="email" name="values[site_email]" maxlength="150" value="' . $e($settings['site_email'] ?? '') . '"></label>'
            . '<label>Support phone<input name="values[site_phone]" maxlength="30" value="' . $e($settings['site_phone'] ?? '') . '"></label>'
            . '<label>Platform address<input name="values[site_address]" maxlength="500" value="' . $e($settings['site_address'] ?? '') . '"></label>'
            . '<label>Platform commission (%)<input type="number" min="0" max="100" step="0.01" name="values[platform_commission_rate]" value="' . $e($commission) . '" required><small>Deducted before the Instructor amount is queued for payout.</small></label>'
            . '<label>eSewa merchant identifier<input name="values[esewa_id]" maxlength="150" value="' . $e($settings['esewa_id'] ?? '') . '" placeholder="Merchant wallet or account label"></label>'
            . '<label>Khalti merchant identifier<input name="values[khalti_id]" maxlength="150" value="' . $e($settings['khalti_id'] ?? '') . '" placeholder="Merchant dashboard label"></label>'
            . '<label>Bank name<input name="values[bank_name]" maxlength="150" value="' . $e($settings['bank_name'] ?? '') . '"></label>'
            . '<label>Bank account name<input name="values[bank_account_name]" maxlength="150" value="' . $e($settings['bank_account_name'] ?? '') . '"></label>'
            . '<label>Bank account number<input name="values[bank_account_number]" maxlength="100" value="' . $e($settings['bank_account_number'] ?? '') . '"></label>'
            . '<label class="wide">Manual payment instructions<textarea name="values[payment_instructions]" rows="4" maxlength="3000">' . $e($settings['payment_instructions'] ?? '') . '</textarea></label></div>'
            . '<div class="admin-settings-submit"><button class="portal-button" type="submit">Save platform settings</button></div></section></form>';

        $gatewayForm = '<form method="post">' . Csrf::field() . '<input type="hidden" name="action" value="save_gateways">'
            . '<section class="data-card"><div class="data-card-head"><div><span>MERCHANT CONNECTIONS</span><h3>Automatic Student payment gateways</h3></div><span class="secure-pill">Secrets remain in .env</span></div>'
            . '<div class="metric-grid">' . $gatewayCard('eSewa', $esewa, 'teal', 'esewa_enabled') . $gatewayCard('Khalti', $khalti, 'violet', 'khalti_enabled') . '</div>'
            . '<div class="payment-note"><span>i</span><p>Register a verified merchant account first. Put the live credentials in the server environment, restart payment-service, then enable the provider here.</p></div>'
            . '<button class="portal-button" type="submit">Save gateway availability</button></section></form>';

        $flow = '<section class="data-card"><div class="data-card-head"><div><span>AUTOMATIC SETTLEMENT MODEL</span><h3>How Student money becomes Instructor earnings</h3></div></div>'
            . '<div class="security-list"><article><i>1</i><div><strong>Student pays the platform merchant</strong><p>eSewa or Khalti credits the CourseHub Admin merchant account after a verified checkout.</p></div><span>Gateway</span></article>'
            . '<article><i>2</i><div><strong>CourseHub verifies the provider response</strong><p>The amount, order reference and provider transaction status must all match before enrollment.</p></div><span>Verified</span></article>'
            . '<article><i>3</i><div><strong>Commission is split automatically</strong><p>CourseHub records the platform commission and the related Instructor amount for every order item.</p></div><span>Ledger</span></article>'
            . '<article><i>4</i><div><strong>Instructor payout is queued automatically</strong><p>A configured payout/disbursement API receives the Instructor destination and net amount. Without that separate provider API, the payout remains approved for Admin settlement.</p></div><span>Payout</span></article></div>'
            . '<div class="payment-note"><span>!</span><p>eSewa/Khalti checkout credentials collect Student payments. Automatic transfer from the Admin merchant account to Instructor accounts requires a separate provider-approved payout or disbursement API configured through AUTO_PAYOUT_ENABLED and PAYOUT_API_URL.</p></div></section>';

        return PortalPage::render('admin', 'Settings', $alert . '<div class="settings-stack">' . $gatewayForm . $platformForm . $flow . '</div>');
    }
}
