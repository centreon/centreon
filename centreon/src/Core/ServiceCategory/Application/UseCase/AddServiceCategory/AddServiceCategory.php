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

namespace Core\ServiceCategory\Application\UseCase\AddServiceCategory;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Common\Domain\TrimmedString;
use Core\ServiceCategory\Application\Exception\ServiceCategoryException;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\NewServiceCategory;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\ServiceCategory\Infrastructure\API\AddServiceCategory\AddServiceCategoryPresenter;

final class AddServiceCategory
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteServiceCategoryRepositoryInterface $writeServiceCategoryRepository,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param AddServiceCategoryRequest $request
     * @param AddServiceCategoryPresenter $presenter
     */
    public function __invoke(AddServiceCategoryRequest $request, PresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE)) {
                $this->error('User doesn\'t have sufficient rights to see service categories', [
                    'user_id' => $this->user->getId(),
                ]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceCategoryException::addNotAllowed())
                );
            } elseif ($this->readServiceCategoryRepository->existsByName(new TrimmedString($request->name))) {
                $this->error('Service category name already exists', [
                    'servicecategory_name' => $request->name,
                ]);
                $presenter->setResponseStatus(
                    new ConflictResponse(ServiceCategoryException::serviceNameAlreadyExists())
                );
            } else {
                $newServiceCategory = new NewServiceCategory($request->name, $request->alias);
                $newServiceCategory->setActivated($request->isActivated);

                $serviceCategoryId = $this->writeServiceCategoryRepository->add($newServiceCategory);
                $serviceCategory = $this->readServiceCategoryRepository->findById($serviceCategoryId);
                if (! $serviceCategory) {
                    $presenter->setResponseStatus(
                        new ErrorResponse(ServiceCategoryException::errorWhileRetrievingJustCreated())
                    );

                    return;
                }

                $presenter->present(
                    new CreatedResponse($serviceCategoryId, $this->createResponse($serviceCategory))
                );
            }
        } catch (\Assert\AssertionFailedException $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(ServiceCategoryException::addServiceCategory($ex))
            );
            $this->error((string) $ex);
        }
    }

    /**
     * @param ServiceCategory|null $serviceCategory
     *
     * @return AddServiceCategoryResponse
     */
    private function createResponse(?ServiceCategory $serviceCategory): AddServiceCategoryResponse
    {
        $response = new AddServiceCategoryResponse();
        if ($serviceCategory !== null) {
            $response->id = $serviceCategory->getId();
            $response->name = $serviceCategory->getName();
            $response->alias = $serviceCategory->getAlias();
            $response->isActivated = $serviceCategory->isActivated();
        }

        return $response;
    }
}
