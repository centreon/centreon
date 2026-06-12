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
 * One timeline event of a monitored resource, as displayed to the user.
 */
final readonly class IncidentEvent
{
    public function __construct(
        public string $date,
        public string $type,
        public ?string $content = null,
        public ?string $author = null,
        public ?string $status = null,
    ) {
        Assert::lengthBetween($date, 1, 64);
        Assert::lengthBetween($type, 1, 64);
        if ($content !== null) {
            Assert::maxLength($content, 2000);
        }
        if ($author !== null) {
            Assert::maxLength($author, 255);
        }
        if ($status !== null) {
            Assert::maxLength($status, 50);
        }
    }
}
