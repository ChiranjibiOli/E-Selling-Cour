<?php

declare(strict_types=1);

final class StudioAccessRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {
    }

    public static function capture(array $input): self
    {
        $email = strtolower(trim((string) ($input['studio_email'] ?? '')));
        $password = (string) ($input['studio_password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            throw new DomainException('Enter a valid instructor email address.');
        }
        if ($password === '' || strlen($password) > 200) {
            throw new DomainException('Enter your studio password.');
        }

        return new self($email, $password);
    }
}
