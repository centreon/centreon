<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\HostGroup\Application\UseCase\AddHostGroup;

final class AddHostGroupRequest
{
    public string $name = '';

    public string $alias = '';

    public string $notes = '';

    public string $notesUrl = '';

    public string $actionUrl = '';

    public ?int $iconId = null;

    public ?int $iconMapId = null;

    public ?int $rrdRetention = null;

    public ?string $geoCoords = null;

    public string $comment = '';

    public bool $isActivated = true;
}
