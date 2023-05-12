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

namespace Core\Dashboard\Infrastructure\API\UpdateDashboard;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Dashboard\Application\UseCase\UpdateDashboard\UpdateDashboard;
use Core\Dashboard\Application\UseCase\UpdateDashboard\UpdateDashboardRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class UpdateDashboardController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param UpdateDashboard $useCase
     * @param FeatureFlags $flags
     * @param int $dashboardId
     * @param UpdateDashboardPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        int $dashboardId,
        Request $request,
        UpdateDashboard $useCase,
        UpdateDashboardPresenter $presenter,
        FeatureFlags $flags,
    ): Response {
        $this->denyAccessUnlessGrantedForAPIConfiguration();

        try {
            /** @var array{
             *     name: string,
             *     description?: ?string
             * } $dataSent
             */
            $dataSent = $this->validateAndRetrieveDataSent($request, __DIR__ . '/UpdateDashboardSchema.json');

            $dto = new UpdateDashboardRequest();
            $dto->name = $dataSent['name'];
            $dto->description = $dataSent['description'] ?? '';

            $useCase($dashboardId, $dto, $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse($ex));
        }

        return $presenter->show();
    }
}