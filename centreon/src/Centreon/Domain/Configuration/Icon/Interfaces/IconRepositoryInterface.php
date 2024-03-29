<?php

/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Domain\Configuration\Icon\Interfaces;

use Centreon\Domain\Configuration\Icon\Icon;

interface IconRepositoryInterface
{
    /**
     * Retrieve icons using request parameters (search, sort, pagination)
     *
     * @return Icon[]
     */
    public function getIconsWithRequestParameters(): array;

    /**
     * Retrieve icons without request parameters (no search, no sort, no pagination)
     *
     * @return Icon[]
     */
    public function getIconsWithoutRequestParameters(): array;

    /**
     * Retrieve an icon based on its id
     *
     * @param integer $id
     * @return Icon|null
     */
    public function getIcon(int $id): ?Icon;
}
