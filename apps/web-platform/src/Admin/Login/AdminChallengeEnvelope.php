<?php

declare(strict_types=1);

final class AdminChallengeEnvelope
{
    public function __construct(
        public readonly string $identity,
        public readonly string $secret,
        public readonly string $entryCode,
    ) {
    }

    public static function seal(array $input): self
    {
        $identity = strtolower(trim((string) ($input['control_identity'] ?? '')));
        $secret = (string) ($input['control_secret'] ?? '');
        $entryCode = trim((string) ($input['control_entry_code'] ?? ''));

        if (!filter_var($identity, FILTER_VALIDATE_EMAIL) || strlen($identity) > 150) {
            throw new DomainException('Invalid control identity.');
        }
        if ($secret === '' || strlen($secret) > 200 || $entryCode === '' || strlen($entryCode) > 200) {
            throw new DomainException('The control-room challenge was not completed.');
        }

        return new self($identity, $secret, $entryCode);
    }
}
