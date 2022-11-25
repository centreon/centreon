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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\ReadHostRepositoryInterface;
use Core\HostCategory\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostCategory\Application\UseCase\FindHostCategories\FindHostCategoriesResponse;
use Core\HostCategory\Domain\Model\Host;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostCategory\Domain\Model\HostTemplate;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

class FindHostCategories
{
    use LoggerTrait;

    public function __construct(
        private ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private ReadHostRepositoryInterface $readHostRepository,
        private ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private ContactInterface $user
    ) {
    }

    public function __invoke(PresenterInterface $presenter): void
    {
        try {
            if ($this->user->isAdmin()) {
                $hostCategories = $this->readHostCategoryRepository->findAll();
            } else {
                $this->debug('User is not admin, retrieve data via ACLs', ['user' => $this->user->getName()]);

                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->user);
                $hostCategories = $this->readHostCategoryRepository->findAllByAccessGroups($accessGroups);
            }

            $hostCategoryIds = array_map(
                fn($hostCategory) => $hostCategory->getId(),
                $hostCategories
            );

            $hosts = $this->readHostRepository->findHostsByHostCategoryIds($hostCategoryIds);
            $hostTemplates = $this->readHostTemplateRepository->findHostTemplatesByHostCategoryIds($hostCategoryIds);

            $presenter->present($this->createResponse($hostCategories, $hosts, $hostTemplates));
        } catch (\Throwable $th) {
            $presenter->setResponseStatus(new ErrorResponse('Error while searching for host categories'));
            // TODO : translate error message
            $this->error($th->getMessage());
        }
    }

    /**
     * @param HostCategory[] $hostCategories
     * @param array<int,Host[]> $hosts
     * @param array<int,HostTemplate[]> $hostTemplates
     * @return FindHostCategoriesResponse
     */
    private function createResponse(
        array $hostCategories,
        array $hosts,
        array $hostTemplates
    ): FindHostCategoriesResponse {
        $response = new FindHostCategoriesResponse();

        foreach ($hostCategories as $hostCategory) {
            $response->hostCategories[] = [
                'id' => $hostCategory->getId(),
                'name' => $hostCategory->getName(),
                'alias' => $hostCategory->getAlias()
            ];
        }
        foreach ($hosts as $hostCategoryId => $hostsArray) {
            $response->hosts[$hostCategoryId][] = array_map(
                fn($host) => ['id' => $host->getId(), 'name' => $host->getName()],
                $hostsArray
            );
        }
        foreach ($hostTemplates as $hostCategoryId => $hostTemplatesArray) {
            $response->hosts[$hostCategoryId][] = array_map(
                fn($hostTemplate) => ['id' => $hostTemplate->getId(), 'name' => $hostTemplate->getName()],
                $hostTemplatesArray
            );
        }

        return $response;
    }
}
