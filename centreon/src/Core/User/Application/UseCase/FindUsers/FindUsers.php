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

namespace Core\User\Application\UseCase\FindUsers;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\User\Application\Exception\UserException;
use Core\User\Application\Repository\ReadUserRepositoryInterface;
use Core\User\Domain\Model\User;

final class FindUsers
{
    use LoggerTrait;

    /** @var AccessGroup[] */
    private array $accessGroups = [];

    public function __construct(
        private readonly ReadUserRepositoryInterface $readUserRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $user,
        private readonly RequestParametersInterface $requestParameters,
        private readonly bool $isCloudPlatform,
    ) {
    }

    /**
     * @param FindUsersPresenterInterface $presenter
     */
    public function __invoke(FindUsersPresenterInterface $presenter): void
    {
        try {
            if ($this->hasAccessToAllUsers()) {
                $users = $this->readUserRepository->findAllByRequestParameters($this->requestParameters);
            } else {
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $accessGroupNames = array_map(
                    fn(AccessGroup $accessGroup): string => $accessGroup->getName(),
                    $this->accessGroups,
                );
                if (
                    ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_CONTACTS_READ)
                    && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_CONTACTS_READ_WRITE)
                    && ! ($this->isCloudPlatform && in_array('customer_editor_acl', $accessGroupNames, true))
                ) {
                    $this->error(
                        "User doesn't have sufficient rights to see users/contacts",
                        ['user_id' => $this->user->getId()]
                    );
                    $presenter->presentResponse(
                        new ForbiddenResponse(UserException::accessNotAllowed())
                    );

                    return;
                }
                $users = $this->readUserRepository->findByAccessGroupsAndRequestParameters(
                    $this->accessGroups,
                    $this->requestParameters
                );
            }

            $presenter->presentResponse($this->createResponse($users));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(UserException::errorWhileSearching($ex))
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param User[] $users
     *
     * @return FindUsersResponse
     */
    public function createResponse(array $users): FindUsersResponse
    {
        $response = new FindUsersResponse();

        foreach ($users as $user) {
            $dto = new UserDto();
            $dto->id = $user->getId();
            $dto->alias = $user->getAlias();
            $dto->name = $user->getName();
            $dto->email = $user->getEmail();
            $dto->isAdmin = $user->isAdmin();
            $response->users[] = $dto;
        }

        return $response;
    }

    /**
     * @throws \Throwable
     */
    private function hasAccessToAllUsers(): bool
    {
        if ($this->user->isAdmin()) {
            return true;
        }
        $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
        $accessGroupNames = array_map(
            fn(AccessGroup $accessGroup): string => $accessGroup->getName(),
            $this->accessGroups
        );

        return
            $this->user->hasTopologyRole(Contact::ROLE_HOME_DASHBOARD_ADMIN)
            || ($this->isCloudPlatform && in_array('customer_admin_acl', $accessGroupNames, true));
    }
}
