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

use Webmozart\Assert\Assert;

/**
 * Resource facts to explain, as displayed to the user in the details panel.
 * The data comes from the caller (it already passed the user's ACL): this
 * feature never reads monitoring data on its own.
 */
final readonly class ExplainResourceCommand
{
    public function __construct(
        public string $name,
        public string $resourceType,
        public string $status,
        public ?string $information = null,
        public ?string $commandLine = null,
        public ?string $performanceData = null,
        public ?string $duration = null,
        public ?string $tries = null,
        public ?string $parentName = null,
    ) {
        Assert::lengthBetween($name, 1, 255);
        Assert::lengthBetween($resourceType, 1, 50);
        Assert::lengthBetween($status, 1, 50);
        if ($information !== null) {
            Assert::maxLength($information, 10000);
        }
        if ($commandLine !== null) {
            Assert::maxLength($commandLine, 10000);
        }
        if ($performanceData !== null) {
            Assert::maxLength($performanceData, 10000);
        }
        if ($tries !== null) {
            Assert::maxLength($tries, 50);
        }
    }
}
