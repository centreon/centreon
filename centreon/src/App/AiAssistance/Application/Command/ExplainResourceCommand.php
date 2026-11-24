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

namespace App\AiAssistance\Application\Command;

use App\Shared\Application\Command\NonTransactionalCommand;
use App\Shared\Domain\Logging\Attribute\Sensitive;
use Webmozart\Assert\Assert;

/**
 * Resource facts to explain, as displayed to the user in the details panel.
 * The data comes from the caller (it already passed the user's ACL): this
 * feature never reads monitoring data on its own.
 *
 * The free texts of the AI assistance commands are masked in the bus logs:
 * they may carry secrets (a command line, a plugin output).
 */
final readonly class ExplainResourceCommand implements NonTransactionalCommand
{
    public function __construct(
        public string $name,
        public string $resourceType,
        public string $status,
        #[Sensitive]
        public ?string $information = null,
        #[Sensitive]
        public ?string $commandLine = null,
        #[Sensitive]
        public ?string $performanceData = null,
        public ?string $duration = null,
        public ?string $tries = null,
        public ?string $parentName = null,
    ) {
        Assert::lengthBetween($name, 1, 255);
        Assert::lengthBetween($resourceType, 1, 50);
        Assert::lengthBetween($status, 1, 50);
        if ($information !== null) {
            Assert::maxLength($information, 10000, 'The information is too long.');
        }
        if ($commandLine !== null) {
            Assert::maxLength($commandLine, 10000, 'The command line is too long.');
        }
        if ($performanceData !== null) {
            Assert::maxLength($performanceData, 10000, 'The performance data is too long.');
        }
        if ($tries !== null) {
            Assert::maxLength($tries, 50);
        }
    }
}
