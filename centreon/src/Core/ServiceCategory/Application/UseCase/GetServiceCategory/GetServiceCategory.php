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

namespace Core\ServiceCategory\Application\UseCase\GetServiceCategory;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceCategory\Application\Exception\ServiceCategoryException;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Application\Common\UseCase\PresenterInterface;

final class GetServiceCategory
{
    use LoggerTrait;

     /** @var AccessGroup[] */
    private array $accessGroups;

    public function __construct(
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     * @param int $serviceCategoryId
     */
    public function __invoke(PresenterInterface $presenter, int $serviceCategoryId): void
    {
        try {
            if (
                ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ)
                && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE)
            ) {
                $this->error(
                    "User doesn't have sufficient rights to see services categories",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceCategoryException::accessNotAllowed())
                );

                return;
            }

            $serviceCategory = null;
            if ($this->user->isAdmin()) {
                $serviceCategory = $this->readServiceCategoryRepository->findById($serviceCategoryId);

            } else {
                
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                if($this->readServiceCategoryRepository->existsByAccessGroups(
                    $serviceCategoryId,
                    $this->accessGroups
                )) {
                    $serviceCategory = $this->readServiceCategoryRepository->findById($serviceCategoryId);
                }
            }

            if (! $serviceCategory) {
                 $this->error(
                    'ServiceCategory not found',
                    ['service_category_id' => $serviceCategoryId]
                );
                $presenter->setResponseStatus(new NotFoundResponse('ServiceCategory'));

                return;
            }
        
            $presenter->present($this->createResponse($serviceCategory));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->setResponseStatus(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(
                new ErrorResponse(ServiceCategoryException::errorWhileRetrieving())
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param ServiceCategory $serviceCategory
     *
     * @throws \Throwable
     *
     *
     * @return GetServiceCategoryResponse
     */
    private function createResponse(ServiceCategory $serviceCategory): GetServiceCategoryResponse
    {
        

        $response = new GetServiceCategoryResponse();
        $response->id = $serviceCategory->getId();
        $response->name = $serviceCategory->getName();
        $response->alias = $serviceCategory->getAlias();
        $response->isActivated = $serviceCategory->isActivated();
       

        return $response;
    }
}