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

namespace Core\ServiceCategory\Infrastructure\API\GetServiceCategory;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\ServiceCategory\Application\UseCase\GetServiceCategory\GetServiceCategory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


final class GetServiceCategoryController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param GetServiceCategory $useCase
     * @param GetServiceCategoryPresenter $presenter
     * @param int $serviceCategoryId
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        GetServiceCategory $useCase,
        GetServiceCategoryPresenter $presenter,
         int $serviceCategoryId,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        $useCase($presenter, $serviceCategoryId);

        return $presenter->show();
    }
}