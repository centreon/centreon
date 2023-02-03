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

namespace Core\ServiceCategory\Infrastructure\API\AddServiceCategory;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\ServiceCategory\Application\UseCase\AddServiceCategory\AddServiceCategory;
use Core\ServiceCategory\Application\UseCase\AddServiceCategory\AddServiceCategoryRequest;
use Symfony\Component\HttpFoundation\Request;

final class AddServiceCategoryController extends AbstractController
{
    use LoggerTrait;

    public function __invoke(
        Request $request,
        AddServiceCategory $useCase,
        AddServiceCategoryPresenter $presenter
    ): object {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            /** @var array{name:string,alias:string,is_activated?:bool} $data */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddServiceCategorySchema.json');

            $serviceCategoryRequest = $this->createRequestDto($data);

            $useCase($serviceCategoryRequest, $presenter);
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
     * @param array{name:string,alias:string,is_activated?:bool} $data
     *
     * @return AddServiceCategoryRequest
     */
    private function createRequestDto(array $data): AddServiceCategoryRequest
    {
        $serviceCategoryRequest = new AddServiceCategoryRequest();
        $serviceCategoryRequest->name = $data['name'];
        $serviceCategoryRequest->alias = $data['alias'];
        $serviceCategoryRequest->isActivated = $data['is_activated'] ?? true;

        return $serviceCategoryRequest;
    }
}
