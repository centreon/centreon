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

namespace Core\Dashboard\Domain\Model;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;

class DashboardSharingRoles
{
    /**
     * @param Dashboard $dashboard
     * @param ?DashboardContactShare $contactShare
     * @param array<DashboardContactGroupShare> $contactGroupShares
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        private readonly Dashboard $dashboard,
        private readonly ?DashboardContactShare $contactShare,
        private readonly array $contactGroupShares,
    ) {
        $shortName = (new \ReflectionClass($this))->getShortName();

        foreach ($contactGroupShares as $contactGroupShare) {
            Assertion::isInstanceOf(
                $contactGroupShare,
                DashboardContactGroupShare::class,
                "{$shortName}::contactGroupShares"
            );
        }
    }

    public function getTheMostPermissiveRole(): ?DashboardSharingRole
    {
        $role = $this->contactShare?->getRole();

        foreach ($this->contactGroupShares as $contactGroupShare) {
            $role = $contactGroupShare->getRole()->getTheMostPermissiveOfBoth($role);
        }

        return $role;
    }

    public function getDashboard(): Dashboard
    {
        return $this->dashboard;
    }

    public function getContactShare(): ?DashboardContactShare
    {
        return $this->contactShare;
    }

    /**
     * @return array<DashboardContactGroupShare>
     */
    public function getContactGroupShares(): array
    {
        return $this->contactGroupShares;
    }
}
