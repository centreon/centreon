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

declare(strict_types = 1);

namespace Core\Host\Application\UseCase\FindHosts;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Domain\Model\SmallHost;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

final class FindHosts
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /** @var list<AccessGroup> */
    private array $accessGroups = [];

    /**
     * @param RequestParametersInterface $requestParameters
     * @param ContactInterface $user
     * @param ReadHostRepositoryInterface $hostRepository
     * @param ReadAccessGroupRepositoryInterface $accessGroupRepository
     * @param ReadHostCategoryRepositoryInterface $categoryRepository
     * @param ReadHostTemplateRepositoryInterface $hostTemplateRepository
     * @param ReadHostGroupRepositoryInterface $groupRepository
     * @param bool $isCloudPlatform
     */
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        private readonly ContactInterface $user,
        private readonly ReadHostRepositoryInterface $hostRepository,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly ReadHostCategoryRepositoryInterface $categoryRepository,
        private readonly ReadHostTemplateRepositoryInterface $hostTemplateRepository,
        private readonly ReadHostGroupRepositoryInterface $groupRepository,
        private readonly bool $isCloudPlatform
    ) {
    }

    public function __invoke(FindHostsPresenterInterface $presenter): void
    {
        try {
            $this->info('Find host', ['user' => $this->user->getId()]);
            if (! $this->canAccessToListing()) {
                $this->error(
                    "User doesn't have sufficient rights to list hosts",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(HostException::listingNotAllowed())
                );

                return;
            }
            $hosts = [];
            if ($this->isUserAdmin()) {
                $this->info('Find hosts as admin');
                $this->debug('Find host as admin', ['request_parameter' => $this->requestParameters]);
                $hosts = $this->hostRepository->findByRequestParameters($this->requestParameters);
            } else {
                $this->info('Find hosts as non-admin');
                if ($this->accessGroups !== []) {
                    $this->debug('Find hosts as non-admin', [
                        'request_parameter' => $this->requestParameters,
                        'access_groups' => $this->accessGroups,
                    ]);
                    $hosts = $this->hostRepository->findByRequestParametersAndAccessGroups(
                        $this->requestParameters,
                        $this->accessGroups
                    );
                } else {
                    $this->debug('No access groups for non-admin user');
                }
            }
            $presenter->presentResponse($this->createResponse($hosts));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(HostException::errorWhileSearchingForHosts($ex)));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    private function canAccessToListing(): bool
    {
        if ($this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_READ)) {
            return true;
        }
        return $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_WRITE);
    }

    private function setUsersAccessGroups(): void
    {
        $this->accessGroups = $this->accessGroupRepository->findByContact($this->user);
    }

    /**
     * Indicates if the current user is admin or not (cloud + onPremise context).
     *
     * @return bool
     */
    private function isUserAdmin(): bool
    {
        if ($this->user->isAdmin()) {
            return true;
        }

        $this->setUsersAccessGroups();

        $userAccessGroupNames = array_map(
            static fn (AccessGroup $accessGroup): string => $accessGroup->getName(),
            $this->accessGroups
        );

        return ! empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS))
            && $this->isCloudPlatform;
    }

    /**
     * @param SmallHost[] $hosts
     *
     * @throws \Throwable
     *
     * @return FindHostsResponse
     */
    private function createResponse(array $hosts): FindHostsResponse
    {
        $response = new FindHostsResponse();
        foreach ($hosts as $host) {
            $dto = new HostDto(
                $host->getId(),
                (string) $host->getName(),
                $host->getAlias()?->value,
                (string) $host->getIpAddress(),
                new SimpleDto(
                    $host->getMonitoringServer()->getId(),
                    $host->getMonitoringServer()->getName() ?? '',
                ),
                $host->getNormalCheckInterval(),
                $host->getRetryCheckInterval(),
                $host->isActivated(),
            );
            if ($host->getSeverity() !== null) {
                $dto->severity = new SimpleDto(
                    $host->getSeverity()->getId(),
                    $host->getSeverity()->getName() ?? '',
                );
            }
            if ($host->getNotificationTimePeriod() !== null) {
                $dto->notificationTimeperiod = new SimpleDto(
                    $host->getNotificationTimePeriod()->getId(),
                    $host->getNotificationTimePeriod()->getName() ?? '',
                );
            }
            if ($host->getCheckTimePeriod() !== null) {
                $dto->checkTimeperiod = new SimpleDto(
                    $host->getCheckTimePeriod()->getId(),
                    $host->getCheckTimePeriod()->getName() ?? '',
                );
            }
            $hostTemplates = $this->hostTemplateRepository->findByIds(...$host->getTemplateIds());

            foreach ($hostTemplates as $template) {
                $dto->templateParents[] = new SimpleDto($template->getId(), $template->getName());
            }

            $groups = $this->groupRepository->findByIds(...$host->getGroupIds());
            foreach ($groups as $group) {
                $dto->groups[] = new SimpleDto($group->getId(), $group->getName());
            }

            $categories = $this->categoryRepository->findByIds(...$host->getCategoryIds());
            foreach ($categories as $category) {
                $dto->categories[] = new SimpleDto($category->getId(), $category->getName());
            }
            $response->hostDto[] = $dto;
        }

        return $response;
    }
}
