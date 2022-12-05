<?php

/*
* Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\HostCategory\Application\UseCase\FindHostCategories;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\UseCase\FindHostCategories\FindHostCategoriesResponse;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

class FindHostCategories
{
    use LoggerTrait;

    public function __construct(
        private ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private RequestParametersInterface $requestParameters,
        private ContactInterface $user
    ) {
    }

    public function __invoke(PresenterInterface $presenter): void
    {
        try {
            if ($this->user->isAdmin()) {
                $hostCategories = $this->readHostCategoryRepository->findAll($this->requestParameters);
            } else {
                if (
                    ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ)
                    && ! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE)
                ) {
                    $this->error('User doesn\'t have sufficient right to see host categories', [
                        'user_id' => $this->user->getId(),
                    ]);
                    $presenter->setResponseStatus(
                        new ForbiddenResponse('You are not allowed to access host categories')
                    );

                    return;
                }

                $this->debug('User is not admin, use ACLs to retrieve host groups', ['user' => $this->user->getName()]);
                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);
                $hostCategories = $this->readHostCategoryRepository->findAllByAccessGroups(
                    $accessGroups,
                    $this->requestParameters
                );
            }

            $presenter->present($this->createResponse($hostCategories));
        } catch (\Throwable $th) {
            $presenter->setResponseStatus(new ErrorResponse('Error while searching for host categories'));
            // TODO : translate error message
            $this->error($th->getMessage());
        }
    }

    /**
     * @param HostCategory[] $hostCategories
     * @return FindHostCategoriesResponse
     */
    private function createResponse(
        array $hostCategories,
    ): FindHostCategoriesResponse {
        $response = new FindHostCategoriesResponse();

        foreach ($hostCategories as $hostCategory) {
            $response->hostCategories[] = [
                'id' => $hostCategory->getId(),
                'name' => $hostCategory->getName(),
                'alias' => $hostCategory->getAlias(),
                'is_activated' => $hostCategory->isActivated()
            ];
        }

        return $response;
    }
}
