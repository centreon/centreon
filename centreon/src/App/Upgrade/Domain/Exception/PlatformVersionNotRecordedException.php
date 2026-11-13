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

namespace App\Upgrade\Domain\Exception;

/**
 * Every Centreon Web migration ran: only recording the platform version remains to be done.
 */
final class PlatformVersionNotRecordedException extends \RuntimeException
{
    /**
     * @param list<string> $warnings what the migrations warned of
     */
    public function __construct(\Throwable $previous, array $warnings = [])
    {
        parent::__construct(sprintf(
            'Every Centreon Web migration ran, but recording the platform version failed: %s. Fix the cause, then run centreon:migrations:migrate again: it only records the platform version.%s',
            $previous->getMessage(),
            $warnings === [] ? '' : sprintf(' Warnings: %s.', implode('; ', $warnings)),
        ), 0, $previous);
    }
}
