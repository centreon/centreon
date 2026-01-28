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

namespace Core\ServiceCategory\Application\UseCase\UpdateServiceCategory;

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
use Core\ServiceCategory\Application\Exception\ServiceCategoryException;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\Domain\Common\GeoCoords;

final class UpdateServiceCategory
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteServiceCategoryRepositoryInterface $writeServiceCategoryRepository,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param UpdateServiceCategoryRequest $dto
     * @param DefaultPresenter $presenter
     * @param int $serviceCategoryId
     */
    public function __invoke(
        UpdateServiceCategoryRequest $dto,
        PresenterInterface $presenter,
        int $serviceCategoryId,
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to edit service categories",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceCategoryException::editNotAllowed()->getMessage())
                );

                return;
            }

            $serviceCategory = null;

            if ($this->user->isAdmin()){
                $serviceCategory = $this->readServiceCategoryRepository->findById($serviceCategoryId);
            } else if($this->readServiceCategoryRepository->existsByAccessGroups(
                    $serviceCategoryId,
                    $this->readAccessGroupRepositoryInterface->findByContact($this->user)
                )){
                    $serviceCategory = $this->readServiceCategoryRepository->findById($serviceCategoryId);
                
            }

            if (! $serviceCategory) {
                $this->error(
                    'Service category not found',
                    ['service_category_id' => $serviceCategoryId]
                );
                $presenter->setResponseStatus(new NotFoundResponse('Service category'));

                return;
            }

            $this->validateNameOrFail($dto->name, $serviceCategory);

            $serviceCategory = new ServiceCategory(
                $serviceCategory->getId(),
                $dto->name,
                $dto->alias,
            );
            $serviceCategory->setActivated($dto->isActivated);
            

            $this->writeServiceCategoryRepository->update($serviceCategory);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (ServiceCategoryException $ex) {
            $presenter->setResponseStatus(
                match ($ex->getCode()) {
                    ServiceCategoryException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (AssertionFailedException $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(ServiceCategoryException::errorWhileUpdating())
            );
            $this->error((string) $ex);
        }
    }

    /**
     * @param string $name
     * @param ServiceCategory $serviceCategory
     *
     * @throws ServiceCategoryException
     */
    private function validateNameOrFail(string $name, ServiceCategory $serviceCategory): void
    {
        if (
            $name !== $serviceCategory->getName()
            && $this->readServiceCategoryRepository->existsByName((new TrimmedString($name)))
        ) {
            $this->error(
                'Service category name already exists',
                ['service_category_name' => $name]
            );

            throw ServiceCategoryException::nameAlreadyExists($name);
        }
    }
}