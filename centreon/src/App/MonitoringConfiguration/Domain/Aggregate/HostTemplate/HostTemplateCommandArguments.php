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

namespace App\MonitoringConfiguration\Domain\Aggregate\HostTemplate;

use Webmozart\Assert\Assert;

/**
 * The invariant of the arguments a command is called with: storage bang-joins them and encodes
 * \n\t\r as #BR#/#T#/#R#, so an argument holding the delimiter or a literal token cannot be stored.
 */
final readonly class HostTemplateCommandArguments
{
    private const RESERVED = ['!', '#BR#', '#T#', '#R#'];

    /**
     * @param array<mixed> $arguments
     * @param string $owner what the arguments belong to, named in the message
     */
    public static function assertValid(array $arguments, string $owner): void
    {
        Assert::allString($arguments);

        foreach (self::RESERVED as $reserved) {
            Assert::allNotContains($arguments, $reserved, $owner . ' must not contain the "!" delimiter or a #BR#/#T#/#R# escape token.');
        }
    }
}
