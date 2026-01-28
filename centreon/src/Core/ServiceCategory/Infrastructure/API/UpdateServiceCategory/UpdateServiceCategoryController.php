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

namespace Core\ServiceCategory\Infrastructure\API\UpdateServiceCategory;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\ServiceCategory\Application\UseCase\UpdateServiceCategory\UpdateServiceCategory;
use Core\ServiceCategory\Application\UseCase\UpdateServiceCategory\UpdateServiceCategoryRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class UpdateServiceCategoryController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param UpdateServiceCategory $useCase
     * @param DefaultPresenter $presenter
     * @param int $serviceCategoryId
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        UpdateServiceCategory $useCase,
        DefaultPresenter $presenter,
        int $serviceCategoryId,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            /** @var array{
             *     name: string,
             *     alias: string,
             *     is_activated?: bool
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/UpdateServiceCategorySchema.json');
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));

            return $presenter->show();
        }

        $serviceCategoryRequest = $this->createRequestDto($data);
        $useCase($serviceCategoryRequest, $presenter, $serviceCategoryId);

        return $presenter->show();
    }

    /**
     * @param array{
     *     name: string,
     *     alias: string,
     *     is_activated?: bool
     * } $data
     *
     *
     * @return UpdateServiceCategoryRequest
     */
    private function createRequestDto(array $data): UpdateServiceCategoryRequest
    {
        $serviceCategoryRequest = new UpdateServiceCategoryRequest();
        $serviceCategoryRequest->name = $data['name'];
        $serviceCategoryRequest->alias = $data['alias'];
        $serviceCategoryRequest->isActivated = $data['is_activated'] ?? true;
        
        return $serviceCategoryRequest;
    }
}