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
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\HostCategory\Application\UseCase\AddHostCategory\AddHostCategory;
use Core\HostCategory\Application\UseCase\AddHostCategory\AddHostCategoryRequest;
use Symfony\Component\HttpFoundation\Request;

final class AddHostCategoryController extends AbstractController
{
    use LoggerTrait;

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

        try {
            /** @var array{name:string,alias:string,is_activated?:bool,comment?:string|null} $data */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddHostCategorySchema.json');

            $hostCategoryRequest = $this->createRequestDto($data);

            $useCase($hostCategoryRequest, $presenter);

        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse($ex));
        }

        return $presenter->show();
    }

    /**
     * @param array{name:string,alias:string,is_activated?:bool,comment?:string|null} $data
     *
     * @return AddHostCategoryRequest
     */
    private function createRequestDto(array $data): AddHostCategoryRequest
    {
        $hostCategoryRequest = new AddHostCategoryRequest();
        $hostCategoryRequest->name = $data['name'];
        $hostCategoryRequest->alias = $data['alias'];
        $hostCategoryRequest->isActivated = $data['is_activated'] ?? true;
        $hostCategoryRequest->comment = $data['comment'] ?? null;

        return $hostCategoryRequest;
    }
}
