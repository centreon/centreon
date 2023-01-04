<?php

/*
* Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\HostCategory\Infrastructure\API\AddHostCategory;

use Centreon\Application\Controller\AbstractController;
use Core\HostCategory\Application\UseCase\AddHostCategory\AddHostCategory;
use Core\HostCategory\Application\UseCase\AddHostCategory\AddHostCategoryRequest;
use Core\HostCategory\Infrastructure\API\AddHostCategory\AddHostCategoryPresenter;
use Symfony\Component\HttpFoundation\Request;

final class AddHostCategoryController extends AbstractController
{
    /**
     * @param Request $request
     * @param AddHostCategory $useCase
     * @param AddHostCategoryPresenter $presenter
     *
     * @return object
     */
    public function __invoke(
        Request $request,
        AddHostCategory $useCase,
        AddHostCategoryPresenter $presenter
    ): object {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        /** @var array{name:string,alias:string,comments:string|null} $data */
        $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddHostCategorySchema.json');

        $hostCategoryRequest = $this->createRequestDto($data);

        $useCase($hostCategoryRequest, $presenter);

        return $presenter->show();
    }

    /**
     * @param array{name:string,alias:string,comments:string|null} $data
     *
     * @return AddHostCategoryRequest
     */
    private function createRequestDto(array $data): AddHostCategoryRequest
    {
        $hostCategoryRequest = new AddHostCategoryRequest();
        $hostCategoryRequest->name = $data['name'];
        $hostCategoryRequest->alias = $data['alias'];
        $hostCategoryRequest->comment = $data['comments'] ?? null;

        return $hostCategoryRequest;
    }
}
