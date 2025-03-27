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

namespace Core\HostGroup\Application\UseCase\GetHostGroup;

use Core\Application\Common\UseCase\StandardResponseInterface;
use Core\Common\Domain\SimpleEntity;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Media\Domain\Model\Media;
use Core\ResourceAccess\Domain\Model\TinyRule;

final class GetHostGroupResponse implements StandardResponseInterface
{
    /**
     * @param HostGroup $hostgroup
     * @param SimpleEntity[] $hosts
     * @param TinyRule[] $rules
     * @param ?Media $icon
     */
    public function __construct(
        readonly public HostGroup $hostgroup,
        readonly public array $hosts = [],
        readonly public array $rules = [],
        readonly public ?Media $icon = null,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function getData(): mixed
    {
        return $this;
    }
}
