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

namespace Core\Dashboard\Infrastructure\API\PartialUpdateDashboard;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\PartialUpdateDashboard;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\PartialUpdateDashboardRequest;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\PanelRequestDto;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PartialUpdateDashboardController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param int $dashboardId
     * @param Request $request
     * @param PartialUpdateDashboard $useCase
     * @param PartialUpdateDashboardPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        int $dashboardId,
        Request $request,
        PartialUpdateDashboard $useCase,
        PartialUpdateDashboardPresenter $presenter,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $useCase($dashboardId, $this->getPartialUpdateDashboardRequest($request), $presenter);
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
     * @param Request $request
     *
     * @throws \InvalidArgumentException
     *
     * @return PartialUpdateDashboardRequest
     */
    private function getPartialUpdateDashboardRequest(Request $request): PartialUpdateDashboardRequest
    {
        /** @var array{
         *     name?: string,
         *     description?: ?string,
         *     panels?: array<array{
         *         id?: ?int,
         *         name: string,
         *         layout: array{
         *             x: int,
         *             y: int,
         *             width: int,
         *             height: int,
         *             min_width: int,
         *             min_height: int
         *         },
         *         widget_type: string,
         *         widget_settings: array<mixed>,
         *     }>
         * } $dataSent
         */
        $dataSent = $this->validateAndRetrieveDataSent($request, __DIR__ . '/PartialUpdateDashboardSchema.json');

        $dto = new PartialUpdateDashboardRequest();
        if (\array_key_exists('name', $dataSent)) {
            $dto->name = $dataSent['name'];
        }

        if (\array_key_exists('description', $dataSent)) {
            $dto->description = (string) $dataSent['description'];
        }

        if (\array_key_exists('panels', $dataSent)) {
            $dto->panels = [];
            foreach ($dataSent['panels'] as $panelArray) {
                $dtoPanel = new PanelRequestDto();

                $dtoPanel->id = $panelArray['id'] ?? null;
                $dtoPanel->name = $panelArray['name'];

                $dtoPanel->layout->posX = $panelArray['layout']['x'];
                $dtoPanel->layout->posY = $panelArray['layout']['y'];
                $dtoPanel->layout->width = $panelArray['layout']['width'];
                $dtoPanel->layout->height = $panelArray['layout']['height'];
                $dtoPanel->layout->minWidth = $panelArray['layout']['min_width'];
                $dtoPanel->layout->minHeight = $panelArray['layout']['min_height'];

                $dtoPanel->widgetType = $panelArray['widget_type'];
                $dtoPanel->widgetSettings = $panelArray['widget_settings'];

                $dto->panels[] = $dtoPanel;
            }
        }

        return $dto;
    }
}
