<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Application\UseCase\PartialUpdateDashboard;

use Core\Common\Application\Type\NoValue;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\PanelRequestDto;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\RefreshRequestDto;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\ThumbnailRequestDto;

final class PartialUpdateDashboardRequest
{
    /**
     * @param NoValue|string $name
     * @param NoValue|string $description
     * @param NoValue|array<PanelRequestDto> $panels
     * @param NoValue|RefreshRequestDto $refresh
     * @param NoValue|ThumbnailRequestDto $thumbnail
     */
    public function __construct(
        public NoValue|string $name = new NoValue(),
        public NoValue|string $description = new NoValue(),
        public NoValue|array $panels = new NoValue(),
        public NoValue|RefreshRequestDto $refresh = new NoValue(),
        public NoValue|ThumbnailRequestDto $thumbnail = new NoValue()
    ) {
    }
}
