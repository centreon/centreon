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

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Dashboard\Domain\Model\Role\DashboardGlobalRole;

class DashboardRights
{
    private const ROLE_VIEWER = Contact::ROLE_HOME_DASHBOARD_VIEWER;
    private const ROLE_CREATOR = Contact::ROLE_HOME_DASHBOARD_CREATOR;
    private const ROLE_ADMIN = Contact::ROLE_HOME_DASHBOARD_ADMIN;

    public function __construct(private readonly ContactInterface $contact)
    {
    }

    public function canCreate(): bool
    {
        return $this->hasAdminRole() || $this->hasCreatorRole();
    }

    public function canAccess(): bool
    {
        return $this->hasViewerRole();
    }

    public function hasAdminRole(): bool
    {
        return $this->contact->hasTopologyRole(self::ROLE_ADMIN);
    }

    public function hasCreatorRole(): bool
    {
        return $this->contact->hasTopologyRole(self::ROLE_ADMIN)
            || $this->contact->hasTopologyRole(self::ROLE_CREATOR);
    }

    public function hasViewerRole(): bool
    {
        return $this->contact->hasTopologyRole(self::ROLE_ADMIN)
            || $this->contact->hasTopologyRole(self::ROLE_CREATOR)
            || $this->contact->hasTopologyRole(self::ROLE_VIEWER);
    }

    public function getGlobalRole(): ?DashboardGlobalRole
    {
        return match (true) {
            $this->hasAdminRole() => DashboardGlobalRole::Administrator,
            $this->hasCreatorRole() => DashboardGlobalRole::Creator,
            $this->hasViewerRole() => DashboardGlobalRole::Viewer,
            default => null,
        };
    }
}
