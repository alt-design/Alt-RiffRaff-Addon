<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Connect;

final class PollResult
{
    private function __construct(
        public readonly PollStatus $status,
        public readonly ?int $interval = null,
        #[\SensitiveParameter] public readonly ?string $key = null,
        public readonly ?string $site = null,
    ) {}

    public static function of(PollStatus $status): self
    {
        return new self($status);
    }

    public static function slowDown(int $interval): self
    {
        return new self(PollStatus::SlowDown, interval: $interval);
    }

    public static function approved(#[\SensitiveParameter] string $key, string $site): self
    {
        return new self(PollStatus::Approved, key: $key, site: $site);
    }
}
