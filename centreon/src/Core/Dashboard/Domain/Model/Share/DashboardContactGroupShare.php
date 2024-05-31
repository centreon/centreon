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

namespace Core\Dashboard\Domain\Model\Share;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;

class DashboardContactGroupShare
{
    /**
     * @param Dashboard $dashboard
     * @param int $contactGroupId
     * @param string $contactGroupName
     * @param DashboardSharingRole $role
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly Dashboard $dashboard,
        private readonly int $contactGroupId,
        private readonly string $contactGroupName,
        private readonly DashboardSharingRole $role,
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();

        Assertion::positiveInt($contactGroupId, $shortName . '::contactGroupId');
        Assertion::notEmptyString($contactGroupName, $shortName . '::contactGroupName');
    }

    public function getDashboard(): Dashboard
    {
        return $this->dashboard;
    }

    public function getContactGroupId(): int
    {
        return $this->contactGroupId;
    }

    public function getContactGroupName(): string
    {
        return $this->contactGroupName;
    }

    public function getRole(): DashboardSharingRole
    {
        return $this->role;
    }
}
