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

namespace Core\ResourceAccess\Application\UseCase\AddRule;

final class AddRuleRequest
{
    public string $name = '';

    public string $description = '';

    public bool $isEnabled = true;

    public bool $applyToAllContacts = false;

    public bool $applyToAllContactGroups = false;

    /** @var int[] */
    public array $contactIds = [];

    /** @var int[] */
    public array $contactGroupIds = [];

    /** @var array{
     *      array{
     *          type:string,
     *          resources: list<int>,
     *          ...
     *      }
     *  }|array{} $datasetFilters
     */
    public array $datasetFilters = [];
}
