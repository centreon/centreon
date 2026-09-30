<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace App\MonitoringConfiguration\Domain\Exception;

final class ServiceDuplicationFailedException extends \RuntimeException
{
    /**
     * @param bool $expected true when the copy legitimately cannot carry services (e.g. a
     *                       token-authenticated request has no legacy session) rather than a genuine
     *                       failure an operator must act on — lets the caller log it accordingly
     */
    private function __construct(string $message, public readonly bool $expected)
    {
        parent::__construct($message);
    }

    public static function missingLegacySession(): self
    {
        return new self(
            'Host services can only be duplicated within a legacy session; none is available '
            . '(for instance on a token-authenticated request).',
            expected: true,
        );
    }

    public static function legacyFunctionsUnavailable(string $reason): self
    {
        return new self($reason, expected: false);
    }
}
