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

namespace Core\ServiceGroup\Application\UseCase\UpdateServiceGroup;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Domain\TrimmedString;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceGroup\Application\Exception\ServiceGroupException;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\Repository\WriteServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\Domain\Common\GeoCoords;

final class UpdateServiceGroup
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteServiceGroupRepositoryInterface $writeServiceGroupRepository,
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param UpdateServiceGroupRequest $dto
     * @param DefaultPresenter $presenter
     * @param int $serviceGroupId
     */
    public function __invoke(
        UpdateServiceGroupRequest $dto,
        PresenterInterface $presenter,
        int $serviceGroupId,
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to edit service groups",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceGroupException::editNotAllowed()->getMessage())
                );

                return;
            }

            $serviceGroup = null;

            if ($this->user->isAdmin()){
                $serviceGroup = $this->readServiceGroupRepository->findOne($serviceGroupId);
            } else {
                $serviceGroup = $this->readServiceGroupRepository->findOneByAccessGroups(
                    $serviceGroupId,
                    $this->readAccessGroupRepositoryInterface->findByContact($this->user)
                );
            }

            if (! $serviceGroup) {
                $this->error(
                    'Service group not found',
                    ['service_group_id' => $serviceGroupId]
                );
                $presenter->setResponseStatus(new NotFoundResponse('Service group'));

                return;
            }

            $this->validateNameOrFail($dto->name, $serviceGroup);

            $serviceGroup = new ServiceGroup(
                $serviceGroup->getId(),
                $dto->name,
                $dto->alias,
                match ($dto->geoCoords) {
                    null, '' => null,
                    default => GeoCoords::fromString($dto->geoCoords),
                },
                $dto->comment,
                $dto->isActivated,
            );
            

            $this->writeServiceGroupRepository->update($serviceGroup);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (ServiceGroupException $ex) {
            $presenter->setResponseStatus(
                match ($ex->getCode()) {
                    ServiceGroupException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (AssertionFailedException $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(ServiceGroupException::errorWhileUpdating())
            );
            $this->error((string) $ex);
        }
    }

    /**
     * @param string $name
     * @param ServiceGroup $serviceGroup
     *
     * @throws ServiceGroupException
     */
    private function validateNameOrFail(string $name, ServiceGroup $serviceGroup): void
    {
        if (
            $name !== $serviceGroup->getName()
            && $this->readServiceGroupRepository->nameAlreadyExists((new TrimmedString($name))->value)
        ) {
            $this->error(
                'Service group name already exists',
                ['service_group_name' => $name]
            );

            throw ServiceGroupException::nameAlreadyExists($name);
        }
    }
}
