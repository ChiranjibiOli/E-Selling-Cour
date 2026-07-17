<?php

declare(strict_types=1);

final class StudentCredentialPacket
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {
    }

    public static function from(array $input): self
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            throw new DomainException('Enter a valid learner email address.');
        }
        if ($password === '' || strlen($password) > 200) {
            throw new DomainException('Enter your password.');
        }

        return new self($email, $password);
    }
}
