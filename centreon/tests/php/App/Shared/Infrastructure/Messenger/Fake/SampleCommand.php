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

final readonly class SampleCommand
{
    public function __construct(
        public string $username,
        public string $password,
        public string $description = 'some description',
    ) {
    }
}
