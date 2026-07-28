-- Allow an earning to be attached to a later withdrawal request after an earlier request was rejected.
-- Active duplicate requests are prevented transactionally by earning_status and request-status checks.
ALTER TABLE withdrawal_request_earnings
    ADD KEY withdrawal_earning_lookup_index (earning_id),
    DROP INDEX withdrawal_earning_single_request_unique;
