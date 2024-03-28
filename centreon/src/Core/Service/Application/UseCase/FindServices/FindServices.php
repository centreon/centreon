<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Service\Application\UseCase\FindServices;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Domain\Model\ServiceLight;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;

final class FindServices
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param FindServicesPresenterInterface $presenter
     */
    public function __invoke(FindServicesPresenterInterface $presenter): void
    {
        try {
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_READ)
                && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_WRITE)
            ) {
                $this->error(
                    "User doesn't have sufficient rights to see services",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(ServiceException::accessNotAllowed())
                );

                return;
            }

            if ($this->user->isAdmin()) {
                $services = $this->readServiceRepository->findByRequestParameter($this->requestParameters);
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $services = $this->readServiceRepository->findByRequestParameterAndAccessGroup($this->requestParameters, $accessGroups);
            }

            $presenter->presentResponse($this->createResponse($services));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(ServiceException::errorWhileSearching($ex))
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param ServiceLight[] $services
     *
     * @return FindServicesResponse
     */
    private function createResponse(array $services): FindServicesResponse
    {
        $categoryIds = [];
        $groupIds = [];
        $hostIds = [];
        foreach ($services as $service) {
            $categoryIds[] = $service->getCategoryIds();
            $hostIds[] = $service->getHostIds();
            $hostIds[] = ServiceGroupRelation::getHostIds($service->getGroups());
            $groupIds[] = ServiceGroupRelation::getServiceGroupIds($service->getGroups());
        }
        $categoryIds = array_merge(...$categoryIds);
        $hostIds = array_merge(...$hostIds);
        $groupIds = array_merge(...$groupIds);

        $categoryNames = $categoryIds ? $this->readServiceCategoryRepository->findNames($categoryIds) : null;
        $groupNames = $groupIds ? $this->readServiceGroupRepository->findNames($groupIds) : null;
        $hostNames = $hostIds ? $this->readHostRepository->findNames($hostIds) : null;

        $response = new FindServicesResponse();
        foreach ($services as $service) {
            $dto = new ServiceDto();
            $dto->id = $service->getId();
            $dto->name = $service->getName();
            $dto->normalCheckInterval = $service->getNormalCheckInterval();
            $dto->retryCheckInterval = $service->getRetryCheckInterval();
            $dto->isActivated = $service->isActivated();
            $dto->checkTimePeriod = $service->getCheckTimePeriod() !== null
                ? [
                    'id' => $service->getCheckTimePeriod()->getId(),
                    'name' => $service->getCheckTimePeriod()->getName() ?? '',
                ]
                : null;
            $dto->notificationTimePeriod = $service->getNotificationTimePeriod() !== null
                ? [
                    'id' => $service->getNotificationTimePeriod()->getId(),
                    'name' => $service->getNotificationTimePeriod()->getName() ?? '',
                ]
                : null;
            $dto->serviceTemplate = $service->getServiceTemplate() !== null
                ? [
                    'id' => $service->getServiceTemplate()->getId(),
                    'name' => $service->getServiceTemplate()->getName() ?? '',
                ]
                : null;
            $dto->severity = $service->getSeverity() !== null
                ? [
                    'id' => $service->getSeverity()->getId(),
                    'name' => $service->getSeverity()->getName() ?? '',
                ]
                : null;
            $dto->hosts = array_map(
                fn(int $hostId): array => ['id' => $hostId, 'name' => $hostNames?->getName($hostId) ?? ''],
                $service->getHostIds()
            );
            $dto->categories = $service->getCategoryIds() !== []
                ? array_map(
                    fn(int $categoryId): array => ['id' => $categoryId, 'name' => $categoryNames?->getName($categoryId) ?? ''],
                    $service->getCategoryIds()
                )
                : [];
            $dto->groups = $service->getGroups() !== []
                ? array_map(
                    fn(ServiceGroupRelation $sgRel): array => [
                        'id' => $sgRel->getServiceGroupId(),
                        'name' => $groupNames?->getName($sgRel->getServiceGroupId()) ?? '',
                        'hostId' => $sgRel->getHostId() ?? 0,
                        'hostName' => $hostNames?->getName($sgRel->getHostId() ?? 0) ?? '',
                    ],
                    $service->getGroups()
                )
                : [];

            $response->services[] = $dto;
        }

        return $response;
    }
}
