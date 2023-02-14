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

namespace Core\ServiceCategory\Application\UseCase\FindServiceCategories;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceCategory\Application\Exception\ServiceCategoryException;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategory;

final class FindServiceCategories
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private readonly RequestParametersInterface $requestParameters,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     */
    public function __invoke(PresenterInterface $presenter): void
    {
        try {
            if ($this->user->isAdmin()) {
                $serviceCategories = $this->readServiceCategoryRepository->findByRequestParameter($this->requestParameters);
                $presenter->present($this->createResponse($serviceCategories));
            } elseif (
                $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ)
                || $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE)
            ) {
                $this->debug('User is not admin, use ACLs to retrieve service categories', ['user' => $this->user->getName()]);
                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);
                $serviceCategories = $this->readServiceCategoryRepository->findByRequestParameterAndAccessGroups(
                    $accessGroups,
                    $this->requestParameters
                );
                $presenter->present($this->createResponse($serviceCategories));
            } else {
                $this->error('User doesn\'t have sufficient rights to see service categories', [
                    'user_id' => $this->user->getId(),
                ]);
                $presenter->setResponseStatus(
                    new ForbiddenResponse(ServiceCategoryException::accessNotAllowed())
                );
            }
        } catch (\Throwable $ex) {
            $this->error((string) $ex);
            $presenter->setResponseStatus(
                new ErrorResponse(ServiceCategoryException::findServiceCategories($ex))
            );
        }
    }

    /**
     * @param ServiceCategory[] $serviceCategories
     *
     * @return FindServiceCategoriesResponse
     */
    private function createResponse(
        array $serviceCategories,
    ): FindServiceCategoriesResponse {
        $response = new FindServiceCategoriesResponse();

        foreach ($serviceCategories as $serviceCategory) {
            $response->serviceCategories[] = [
                'id' => $serviceCategory->getId(),
                'name' => $serviceCategory->getName(),
                'alias' => $serviceCategory->getAlias(),
                'is_activated' => $serviceCategory->isActivated(),
            ];
        }

        return $response;
    }
}
