# CourseHub automatic Instructor payout API

CourseHub treats Student collection and Instructor payout as two separate money movements.

1. eSewa or Khalti collects the Student payment into the CourseHub platform merchant account.
2. CourseHub verifies the provider response and exact amount.
3. CourseHub creates lifetime enrollment and calculates platform commission per order item.
4. The Instructor net amount is reserved in an approved payout request.
5. When a provider-approved payout/disbursement API is configured, CourseHub submits the payout automatically.
6. A payout is marked `paid` only after the payout API returns a valid completed status and transaction reference.
7. A failed or unavailable payout remains approved for Admin settlement. Student enrollment is not rolled back.

## Environment

```dotenv
AUTO_PAYOUT_ENABLED=true
PAYOUT_API_URL=https://your-payout-provider.example/api/v1/transfers
PAYOUT_API_TOKEN=replace-with-provider-issued-bearer-token
PAYOUT_HMAC_SECRET=replace-with-a-separate-signing-secret
PAYOUT_METHOD_PRIORITY=bank,esewa,khalti
```

Keep `AUTO_PAYOUT_ENABLED=false` until the payout provider has approved the merchant account and issued a real HTTPS disbursement endpoint and credentials.

## Request

CourseHub sends an HTTPS `POST` request with JSON:

```json
{
  "reference": "COURSEHUB-PAYOUT-184",
  "amount": "1280.00",
  "currency": "NPR",
  "method": "bank",
  "destination": {
    "method": "bank",
    "account_name": "Approved Instructor Name",
    "account_number": "012345678901",
    "bank_name": "Example Bank",
    "branch_name": "Kathmandu",
    "esewa_number": null,
    "khalti_number": null
  },
  "instructor_id": 42,
  "order_id": 301,
  "payment_id": 217,
  "withdrawal_request_id": 184
}
```

Headers:

```http
Authorization: Bearer <PAYOUT_API_TOKEN>
Idempotency-Key: coursehub-payout-184
Content-Type: application/json
X-CourseHub-Signature: sha256=<HMAC_SHA256_OF_EXACT_JSON_BODY>
```

`X-CourseHub-Signature` is sent only when `PAYOUT_HMAC_SECRET` is configured.

The payout provider must treat the idempotency key as unique. Repeating the same request must return the original transfer result rather than creating another transfer.

## Successful response

CourseHub accepts only `paid`, `success`, or `completed` as a completed status.

```json
{
  "status": "completed",
  "transaction_reference": "BANK-TRX-20260728-00184"
}
```

`transaction_reference` may also be returned as `reference`. It must contain 4 to 150 safe identifier characters.

## Pending or failed response

Examples:

```json
{
  "status": "pending",
  "reference": "PROVIDER-JOB-8821"
}
```

```json
{
  "status": "failed",
  "message": "Destination account was not verified"
}
```

CourseHub does not mark either response as paid. The approved payout stays in the Admin Withdrawals queue and the Instructor earnings remain reserved, preventing double withdrawal.

## Provider implementation requirements

The payout API should:

- require HTTPS;
- authenticate the bearer token;
- verify the optional HMAC signature before parsing business fields;
- enforce idempotency by `Idempotency-Key`;
- validate amount, currency and payout destination server-side;
- return a stable provider transaction reference;
- never expose provider credentials to the browser;
- log request reference, provider reference and state transitions;
- support reconciliation or status lookup for uncertain transfers;
- use provider-approved bank/wallet disbursement capability, not ordinary consumer wallet automation.

## Local testing

For local testing, keep real transfers disabled:

```dotenv
AUTO_PAYOUT_ENABLED=false
```

A verified Student payment will still:

- create lifetime enrollment;
- calculate commission;
- create Instructor earnings;
- reserve the net amount in an approved payout request;
- show the request to Admin for manual settlement.

Do not build a mock endpoint that returns `completed` in production. That would make CourseHub claim money was transferred when no provider moved it.
