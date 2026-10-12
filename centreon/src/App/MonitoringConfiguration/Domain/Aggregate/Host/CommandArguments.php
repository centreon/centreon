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

namespace App\MonitoringConfiguration\Domain\Aggregate\Host;

use Webmozart\Assert\Assert;

/**
 * The invariant of the arguments a command is called with, whether it is a check command or an event
 * handler.
 *
 * Storage bang-joins the arguments and encodes \n\t\r as #BR#/#T#/#R# (CommandArgumentsFormatter),
 * matching legacy. The legacy read path splits on '!' and decodes those tokens, so an argument
 * carrying the '!' delimiter or a literal #BR#/#T#/#R# would not round-trip; raw \n\t\r are fine
 * because the formatter encodes them.
 */
final readonly class CommandArguments
{
    private const RESERVED = ['!', '#BR#', '#T#', '#R#'];

    /**
     * @param array<mixed> $arguments
     * @param string $owner what the arguments belong to, named in the message (e.g. `CheckOptions::args`)
     */
    public static function assertValid(array $arguments, string $owner): void
    {
        Assert::allString($arguments);

        foreach (self::RESERVED as $reserved) {
            Assert::allNotContains($arguments, $reserved, $owner . ' must not contain the "!" delimiter or a #BR#/#T#/#R# escape token.');
        }
    }
}
