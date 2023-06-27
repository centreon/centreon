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

namespace Core\Dashboard\Application\UseCase\AddDashboard;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Dashboard\Application\Model\DashboardSharingRoleConverter;
use Core\Dashboard\Application\UseCase\AddDashboard\Response\UserResponseDto;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Domain\Model\NewDashboard;

final class AddDashboardFactory
{
    /**
     * @param Dashboard $dashboard
     * @param ContactInterface $contact
     * @param DashboardSharingRole $ownRole
     *
     * @return AddDashboardResponse
     */
    public static function createResponse(
        Dashboard $dashboard,
        ContactInterface $contact,
        DashboardSharingRole $ownRole
    ): AddDashboardResponse {
        $response = new AddDashboardResponse();

        $response->id = $dashboard->getId();
        $response->name = $dashboard->getName();
        $response->description = $dashboard->getDescription();
        $response->createdAt = $dashboard->getCreatedAt();
        $response->updatedAt = $dashboard->getUpdatedAt();

        $response->createdBy = new UserResponseDto();
        $response->createdBy->id = $contact->getId();
        $response->createdBy->name = $contact->getName();

        $response->updatedBy = new UserResponseDto();
        $response->updatedBy->id = $contact->getId();
        $response->updatedBy->name = $contact->getName();

        $response->ownRole = $ownRole;

        return $response;
    }

    /**
     * @param AddDashboardRequest $request
     * @param ContactInterface $contact
     *
     * @throws AssertionFailedException
     *
     * @return NewDashboard
     */
    public static function createNewDashboard(AddDashboardRequest $request, ContactInterface $contact): NewDashboard
    {
        $dashboard = new NewDashboard(
            $request->name,
            $contact->getId()
        );
        $dashboard->setDescription($request->description);

        return $dashboard;
    }
}
