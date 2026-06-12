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
 * Current state and recent timeline of a resource, as displayed to the user.
 * The data comes from the caller (it already passed the user's ACL): this
 * feature never reads monitoring data on its own.
 */
final readonly class SummarizeIncidentCommand
{
    public const MAX_EVENTS = 100;

    /**
     * @param list<IncidentEvent> $events oldest first
     * @param string|null $since start of the event window, as displayed
     * @param bool $isTruncated true when more events existed in the window than provided
     */
    public function __construct(
        public string $name,
        public string $resourceType,
        public string $status,
        public array $events,
        public ?string $information = null,
        public ?string $duration = null,
        public ?string $parentName = null,
        public ?string $since = null,
        public bool $isTruncated = false,
    ) {
        Assert::lengthBetween($name, 1, 255);
        Assert::lengthBetween($resourceType, 1, 50);
        Assert::lengthBetween($status, 1, 50);
        Assert::notEmpty($events);
        Assert::maxCount($events, self::MAX_EVENTS);
        Assert::allIsInstanceOf($events, IncidentEvent::class);
        if ($information !== null) {
            Assert::maxLength($information, 10000);
        }
        if ($since !== null) {
            Assert::maxLength($since, 64);
        }
    }
}
