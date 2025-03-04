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

namespace Core\Dashboard\Application\UseCase\FindDashboardContacts;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindDashboardContacts\Response\ContactsResponseDto;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardContactRole;
use Core\Dashboard\Domain\Model\Role\DashboardGlobalRole;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindDashboardContacts
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /**
     * @param RequestParametersInterface $requestParameters
     * @param DashboardRights $rights
     * @param ContactInterface $contact
     * @param ReadDashboardShareRepositoryInterface $readDashboardShareRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadContactRepositoryInterface $readContactRepository
     * @param bool $isCloudPlatform
     * @param ReadContactGroupRepositoryInterface $readContactGroupRepository
     */
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        private readonly DashboardRights $rights,
        private readonly ContactInterface $contact,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadContactRepositoryInterface $readContactRepository,
        private readonly ReadContactGroupRepositoryInterface $readContactGroupRepository,
        private readonly bool $isCloudPlatform
    ) {
    }

    /**
     * @return FindDashboardContactsResponse|ResponseStatusInterface
     */
    public function __invoke(): FindDashboardContactsResponse|ResponseStatusInterface
    {
        try {
            return $this->isUserAdmin()
                ? $this->createResponse($this->findContactAsAdmin())
                : $this->createResponse($this->findContactsAsNonAdmin());
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

            return new ErrorResponse(DashboardException::errorWhileSearchingSharableContacts());
        } catch (\Throwable $ex) {
            $this->error(
                "Error while retrieving contacts allowed to receive a dashboard share : {$ex->getMessage()}",
                [
                    'contact_id' => $this->contact->getId(),
                    'request_parameters' => $this->requestParameters->toArray(),
                    'exception' => [
                        'message' => $ex->getMessage(),
                        'trace' => $ex->getTraceAsString(),
                    ],
                ]
            );

            return new ErrorResponse(DashboardException::errorWhileSearchingSharableContacts());
        }
    }

    /**
     * @param DashboardContactRole[] $users
     */
    private function createResponse(array $users): FindDashboardContactsResponse
    {
        $response = new FindDashboardContactsResponse();

        foreach ($users as $user) {
            $response->contacts[] = new ContactsResponseDto(
                $user->getContactId(),
                $user->getContactName(),
                $user->getContactEmail(),
                $user->getMostPermissiveRole()
            );
        }

        return $response;
    }

    /**
     * Find contacts with their Dashboards roles.
     * Cloud - Return all users with Dashboard access rights (including cloud administrators linked to customer_admin_acl).
     * OnPremise - Return all users with Dashboard access rights and administrators (not link with ACL Groups onPremise).
     *
     * @throws \Throwable
     *
     * @return DashboardContactRole[]
     */
    private function findContactAsAdmin(): array
    {
        $users = $this->readDashboardShareRepository->findContactsWithAccessRightByRequestParameters(
            $this->requestParameters
        );

        if ($this->isCloudPlatform === false ) {
            $total = $this->requestParameters->getTotal();
            $admins = $this->readContactRepository->findAdminWithRequestParameters(
                $this->requestParameters
            );
            $this->requestParameters->setTotal($total + $this->requestParameters->getTotal());
            $adminContactRoles = [];
            foreach ($admins as $admin) {
                $adminContactRoles[] = new DashboardContactRole(
                    $admin->getId(),
                    $admin->getName(),
                    $admin->getEmail(),
                    [DashboardGlobalRole::Administrator]
                );
            }

            return [...$users, ...$adminContactRoles];
        }

        return $users;
    }

    /**
     * Find contacts with their Dashboards roles.
     * Cloud - Return users that are part of the same contact groups as the current user.
     * OnPrem - Return users that are part of the same access groups as the current user.
     *
     * @throws \Throwable
     *
     * @return DashboardContactRole[]
     */
    private function findContactsAsNonAdmin(): array
    {
        if ($this->isCloudPlatform === true) {
            $contactGroups = $this->readContactGroupRepository->findAllByUserId($this->contact->getId());

            return $this->readDashboardShareRepository->findContactsWithAccessRightsByContactGroupsAndRequestParameters(
                $contactGroups,
                $this->requestParameters
            );
        }

        $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $accessGroupIds = array_map(static fn (AccessGroup $accessGroup): int => $accessGroup->getId(), $accessGroups);

        return $this->readDashboardShareRepository->findContactsWithAccessRightByACLGroupsAndRequestParameters(
            $this->requestParameters,
            $accessGroupIds
        );
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
