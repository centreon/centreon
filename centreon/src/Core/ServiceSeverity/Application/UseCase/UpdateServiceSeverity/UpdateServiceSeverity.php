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

namespace Core\ServiceSeverity\Application\UseCase\UpdateServiceSeverity;

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
use Core\ServiceSeverity\Application\Exception\ServiceSeverityException;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\WriteServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Domain\Model\ServiceSeverity;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

final class UpdateServiceSeverity
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteServiceSeverityRepositoryInterface $writeServiceSeverityRepository,
        private readonly ReadServiceSeverityRepositoryInterface $readServiceSeverityRepository,
        private readonly ReadViewImgRepositoryInterface $readViewImgRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param UpdateServiceSeverityRequest $dto
     * @param DefaultPresenter $presenter
     * @param int $severityId
     */
    public function __invoke(
        UpdateServiceSeverityRequest $dto,
        PresenterInterface $presenter,
        int $severityId,
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to edit service severities",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceSeverityException::editNotAllowed()->getMessage())
                );

                return;
            }

            if (! $this->user->isAdmin()
                && ! $this->readServiceSeverityRepository->existsByAccessGroups(
                        $severityId,
                        $this->readAccessGroupRepositoryInterface->findByContact($this->user)
                    )
            ) {
                $this->error(
                    'Service severity not found',
                    ['severity_id' => $severityId]
                );
                $presenter->setResponseStatus(new NotFoundResponse('Service severity'));

                return;
            }

            if (null === ($severity = $this->readServiceSeverityRepository->findById($severityId))) {
                $this->error(
                    'Service severity not found',
                    ['severity_id' => $severityId]
                );
                $presenter->setResponseStatus(new NotFoundResponse('Service severity'));

                return;
            }

            $this->validateNameOrFail($dto->name, $severity);
            $this->validateIconOrFail($dto->iconId);

            $severity->setName($dto->name);
            $severity->setAlias($dto->alias);
            $severity->setLevel($dto->level);
            $severity->setIconId($dto->iconId);
            $severity->setActivated($dto->isActivated);

            $this->writeServiceSeverityRepository->update($severity);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (ServiceSeverityException $ex) {
            $presenter->setResponseStatus(
                match ($ex->getCode()) {
                    ServiceSeverityException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (AssertionFailedException $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(ServiceSeverityException::editServiceSeverity($ex))
            );
            $this->error((string) $ex);
        }
    }

    /**
     * @param string $name
     * @param ServiceSeverity $severity
     *
     * @throws ServiceSeverityException
     */
    private function validateNameOrFail(string $name, ServiceSeverity $severity): void
    {
        if (
            $name !== $severity->getName()
            && $this->readServiceSeverityRepository->existsByName(new TrimmedString($name))
        ) {
            $this->error(
                'Service severity name already exists',
                ['severity_name' => $name]
            );

            throw ServiceSeverityException::serviceNameAlreadyExists();
        }
    }

    /**
     * @param int $iconId
     *
     * @throws ServiceSeverityException
     */
    private function validateIconOrFail(int $iconId): void
    {
        if (! $this->readViewImgRepository->existsOne($iconId)) {
            $this->error(
                'Service severity icon does not exist',
                ['icon_id' => $iconId]
            );

            throw ServiceSeverityException::iconDoesNotExist($iconId);
        }
    }
}
