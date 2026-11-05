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

use App\MonitoringConfiguration\Domain\Aggregate\Media\MediaId;
use Webmozart\Assert\Assert;

final readonly class HostTemplateExtendedInformations
{
    public const MAX_ALT_ICON_LENGTH = 200;

    public function __construct(
        public ?string $noteUrl = null,
        public ?string $note = null,
        public ?string $actionUrl = null,
        public ?MediaId $iconId = null,
        public ?string $altIcon = null,
        public ?string $comment = null,
    ) {
        Assert::nullOrMaxLength($altIcon, self::MAX_ALT_ICON_LENGTH);
    }
}
