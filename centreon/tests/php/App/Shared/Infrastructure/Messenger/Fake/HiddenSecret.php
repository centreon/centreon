<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Unauthorized reproduction, copy and distribution are not allowed.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\App\Shared\Infrastructure\Messenger\Fake;

/**
 * Plain object with no Stringable/Enum/DateTime semantics. Used to pin that
 * sanitize() falls back to a class-name placeholder rather than leaking
 * private state when an unexpected object reaches the log payload.
 */
final readonly class HiddenSecret
{
    public function __construct(private string $secret)
    {
    }

    public function reveal(): string
    {
        return $this->secret;
    }
}
