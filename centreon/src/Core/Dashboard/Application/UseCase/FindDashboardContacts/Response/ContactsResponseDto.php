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

namespace Core\Dashboard\Application\UseCase\FindDashboardContacts\Response;

use Core\Dashboard\Domain\Model\Role\DashboardGlobalRole;

final class ContactsResponseDto
{
    /**
     * @param int $id
     * @param string $name
     * @param string $email
     * @param DashboardGlobalRole $mostPermissiveRole
     */
    public function __construct(
        public int $id = 0,
        public string $name = '',
        public string $email = '',
        public DashboardGlobalRole $mostPermissiveRole = DashboardGlobalRole::Viewer
    ) {
    }
}
