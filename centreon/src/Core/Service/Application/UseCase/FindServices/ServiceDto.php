<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Service\Application\UseCase\FindServices;

final class ServiceDto
{
    public int $id = 0;

    public string $name = '';

    public bool $isActivated = false;

    public int|null $normalCheckInterval = null;

    public int|null $retryCheckInterval = null;

    /** @var null|array{id:int,name:string} */
    public null|array $serviceTemplate = null;

    /** @var null|array{id:int,name:string}> */
    public null|array $checkTimePeriod = null;

    /** @var null|array{id:int,name:string} */
    public null|array $notificationTimePeriod = null;

    /** @var null|array{id:int,name:string} */
    public null|array $severity = null;

    /** @var array<array{id:int,name:string}> */
    public array $hosts = [];

    /** @var array<array{id:int,name:string}> */
    public array $categories = [];

    /** @var array<array{id:int,name:string,hostId:int,hostName:string}> */
    public array $groups = [];
}
