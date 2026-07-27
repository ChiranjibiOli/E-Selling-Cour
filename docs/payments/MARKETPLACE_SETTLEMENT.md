# Marketplace payment and instructor settlement

## Supported payment flow

1. The platform owner completes merchant onboarding and business KYC with eSewa and/or Khalti.
2. Issued merchant secrets are stored in the server environment, never in the database or browser.
3. An administrator enables each configured gateway from **Admin → Settings**.
4. A student pays the platform merchant account.
5. The payment service verifies the gateway response and status with the provider.
6. The platform records, per order item:
   - gross course sale;
   - platform commission;
   - instructor net earning.
7. The instructor requests a withdrawal to a saved bank, eSewa or Khalti destination.
8. The administrator approves the request, performs the actual transfer through the provider dashboard or bank, and records the real transaction reference.

## Why payment is not sent directly to the instructor

The standard public eSewa ePay and Khalti KPG checkout APIs credit the registered platform merchant. They do not expose a general marketplace split-payment or instructor-disbursement endpoint in their public gateway documentation.

Do not simulate an automatic payout by marking a withdrawal paid before funds are transferred. The payout record must be created only after the external transfer succeeds.

## Future automatic payouts

Automatic instructor transfers may be added only when the provider approves the platform for a disbursement or marketplace product and supplies a private API contract, credentials, limits, KYC rules, reconciliation fields and webhook requirements. Add that capability as a separate payout adapter rather than reusing customer checkout credentials.
