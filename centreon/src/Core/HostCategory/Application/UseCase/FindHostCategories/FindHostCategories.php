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
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\UseCase\FindHostCategories\FindHostCategoriesPresenterInterface;
use Core\HostCategory\Application\UseCase\FindHostCategories\FindHostCategoriesResponse;
use Core\HostCategory\Domain\Model\HostCategory;

class FindHostCategories
{
    use LoggerTrait;

    public function __construct(
        private ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private ContactInterface $contact
    ) {
    }

    public function __invoke(FindHostCategoriesPresenterInterface $presenter): void
    {
        // TODO : handle ACLs ?
        try {
            $hostCategories = $this->contact->isAdmin()
                ? $this->readHostCategoryRepository->findAll()
                : $this->readHostCategoryRepository->findAllByContactId($this->contact->getId());

            $hostCategoryIds = array_map(
                fn($hostCategory) => $hostCategory->getId(),
                $hostCategories
            );

            /**
             * TODO :
             *  model from HostCategory/Domain/Host or Host/Domain/Host
             *      quid of mandatory parameter that I don't need ?
             *  if from HostCategoryDomain :
             *      request from readHostRepository or readHostCategoryRepository ?
             *  same questions for hostTemplates
             */
            $hosts = $this->contact->isAdmin()
                ? $this->readHostCategoryRepository->findHostsByHostCategoryIds($hostCategoryIds)
                : $this->readHostCategoryRepository->findHostsByHostCategoryIdsAndContactId(
                    $hostCategoryIds,
                    $this->contact->getId()
                );
            foreach ($hostCategories as $hostCategory) {
                if (! empty($hosts[$hostCategory->getId()])) {
                    $hostCategory->setHosts($hosts[$hostCategory->getId()]);
                }
            }

            $hostTemplates = $this->readHostCategoryRepository->findHostTemplatesByHostCategoryIds($hostCategoryIds);
            foreach ($hostCategories as $hostCategory) {
                if (! empty($hostTemplates[$hostCategory->getId()])) {
                    $hostCategory->setHostTemplates($hostTemplates[$hostCategory->getId()]);
                }
            }
            $presenter->present($this->showResponse($hostCategories));
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
    private function showResponse(array $hostCategories): FindHostCategoriesResponse
    {
        $response = new FindHostCategoriesResponse();
        foreach ($hostCategories as $hostCategory) {
            $hostCategoryData = [
                'id' => $hostCategory->getId(),
                'name' => $hostCategory->getName(),
                'alias' => $hostCategory->getAlias(),
                'hosts' => [],
                'host_templates' => []
            ];
            // TODO : what about hostsToArray() ? in response as a static ?
            foreach ($hostCategory->getHosts() as $host) {
                $hostCategoryData['hosts'][] = [
                    'id' => $host->getId(),
                    'name' => $host->getName()
                ];
            }
            foreach ($hostCategory->getHostTemplates() as $hostTemplate) {
                $hostCategoryData['host_templates'][] = [
                    'id' => $hostTemplate->getId(),
                    'name' => $hostTemplate->getName()
                ];
            }
            $response->hostCategories[] = $hostCategoryData;
        }
        return $response;
    }
}
