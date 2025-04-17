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

namespace Core\Dashboard\Application\UseCase\FindDashboardContactGroups;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindDashboardContactGroups\Response\ContactGroupsResponseDto;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardContactGroupRole;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindDashboardContactGroups
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly bool $isCloudPlatform
    ) {
    }

    /**
     * @return FindDashboardContactGroupsResponse|ResponseStatusInterface
     */
    public function __invoke(): FindDashboardContactGroupsResponse|ResponseStatusInterface
    {
        try {
            $this->info('Find dashboard contact groups', ['request' => $this->requestParameters->toArray()]);

            return $this->isUserAdmin()
                ? $this->createResponse($this->findContactGroupsAsAdmin())
                : $this->createResponse($this->findContactGroupsAsContact());
        } catch (RepositoryException $ex) {
            $this->error(
                $ex->getMessage(),
                [
                    'contact_id' => $this->contact->getId(),
                    'request_parameters' => $this->requestParameters->toArray(),
                    'exception' => [
                        'message' => $ex->getPrevious()?->getMessage(),
                        'trace' => $ex->getPrevious()?->getTraceAsString(),
                    ],
                ]
            );

            return new ErrorResponse(DashboardException::errorWhileSearchingSharableContactGroups());
        } catch (\Throwable $ex) {
            $this->error(
                "Error while retrieving contact groups allowed to receive a dashboard share : {$ex->getMessage()}",
                [
                    'contact_id' => $this->contact->getId(),
                    'request_parameters' => $this->requestParameters->toArray(),
                    'exception' => [
                        'message' => $ex->getMessage(),
                        'trace' => $ex->getTraceAsString(),
                    ],
                ]
            );

            return new ErrorResponse(DashboardException::errorWhileSearchingSharableContactGroups());
        }
    }

    /**
     * Cloud UseCase - ACL groups are not linked to contact groups.
     * Therefore, we need to retrieve all contact groups as admin.
     * Those contact groups will have as most permissive role 'Viewer'.
     * No checks will be done regarding Dashboard Rights when sharing to the contact groups selected
     * however it will be not possible for contacts belonging to these contact groups
     * to have more rights than configured through their ACLs as user dashboard rights will
     * be applied for the user that reaches Dashboard.
     *
     * OnPremise
     *
     * Retrieve contact groups having access to Dashboard.
     *
     * @throws \Throwable
     *
     * @return DashboardContactGroupRole[]
     */
    private function findContactGroupsAsAdmin(): array
    {
        return $this->isCloudPlatform
            ? $this->readDashboardShareRepository->findContactGroupsByRequestParameters($this->requestParameters)
            : $this->readDashboardShareRepository->findContactGroupsWithAccessRightByRequestParameters(
            $this->requestParameters
        );
    }

    /**
     * Cloud
     *
     * ACL groups are not linked to contact groups.
     * Therefore, we need to retrieve all contact groups to which belongs the current user.
     * Those contact groups will have as most permissive role 'Viewer'.
     * No checks will be done regarding Dashboard Rights when sharing to the contact groups selected
     * however it will be not possible for contacts belonging to these contact groups
     * to have more rights than configured through their ACLs as user dashboard rights will
     * be applied for the user that reaches Dashboard.
     *
     * OnPremise
     *
     * Retrieve contact groups to which belongs the current user and having access to Dashboard.
     *
     * @throws \Throwable
     *
     * @return DashboardContactGroupRole[]
     */
    private function findContactGroupsAsContact(): array
    {
        if ($this->isCloudPlatform === true) {
            return $this->readDashboardShareRepository->findContactGroupsByUserAndRequestParameters(
                $this->requestParameters,
                $this->contact->getId()
            );
        }

        return $this->readDashboardShareRepository->findContactGroupsWithAccessRightByUserAndRequestParameters(
            $this->requestParameters,
            $this->contact->getId()
        );
    }

    /**
     * @param DashboardContactGroupRole[] $groups
     */
    private function createResponse(array $groups): FindDashboardContactGroupsResponse
    {
        $response = new FindDashboardContactGroupsResponse();

        foreach ($groups as $group) {
            $response->contactGroups[] = new ContactGroupsResponseDto(
                $group->getContactGroupId(),
                $group->getContactGroupName(),
                $group->getMostPermissiverole()
            );
        }

        return $response;
    }

    /**
     * @throws \Throwable
     *
     * @return bool
     */
    private function isUserAdmin(): bool
    {
        if ($this->rights->hasAdminRole()) {
            return true;
        }

        $userAccessGroupNames = array_map(
            static fn (AccessGroup $accessGroup): string => $accessGroup->getName(),
            $this->readAccessGroupRepository->findByContact($this->contact)
        );

        return ! (empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS)))
            && $this->isCloudPlatform;
    }
}
