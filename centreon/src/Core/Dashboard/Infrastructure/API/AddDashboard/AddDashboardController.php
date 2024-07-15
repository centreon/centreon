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

namespace Core\Dashboard\Infrastructure\API\AddDashboard;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Dashboard\Application\UseCase\AddDashboard\AddDashboard;
use Core\Dashboard\Application\UseCase\AddDashboard\AddDashboardRequest;
use Core\Dashboard\Application\UseCase\AddDashboard\LayoutRequest;
use Core\Dashboard\Application\UseCase\AddDashboard\PanelRequest;
use Core\Dashboard\Infrastructure\Model\RefreshTypeConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AddDashboardController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param AddDashboard $useCase
     * @param AddDashboardPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        AddDashboard $useCase,
        AddDashboardPresenter $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $addDashboardRequest = $this->createAddDashboardRequest($request);
            $useCase($addDashboardRequest, $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new ErrorResponse($ex));
        }

        return $presenter->show();
    }

    private function createAddDashboardRequest(Request $request): AddDashboardRequest
    {
        /** @var array{
         *     name: string,
         *     description: ?string,
         *     panels: array<array{
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
         *     }>,
         *     refresh: array{
         *         type: string,
         *         interval: int|null
         *     }
         * } $dataSent
         */
        $dataSent = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddDashboardSchema.json');

        $addDashboardRequest = new AddDashboardRequest();
        $addDashboardRequest->name = $dataSent['name'];
        $addDashboardRequest->description = $dataSent['description'];
        $addDashboardRequest->panels = [];
        foreach ($dataSent['panels'] as $panelArray) {
            $layout = new LayoutRequest();
            $layout->xAxis = $panelArray['layout']['x'];
            $layout->yAxis = $panelArray['layout']['y'];
            $layout->width = $panelArray['layout']['width'];
            $layout->height = $panelArray['layout']['height'];
            $layout->minWidth = $panelArray['layout']['min_width'];
            $layout->minHeight = $panelArray['layout']['min_height'];

            $addDashboardRequestPanel = new PanelRequest($layout);
            $addDashboardRequestPanel->name = $panelArray['name'];
            $addDashboardRequestPanel->widgetType = $panelArray['widget_type'];
            $addDashboardRequestPanel->widgetSettings = $panelArray['widget_settings'];
            $addDashboardRequest->panels[] = $addDashboardRequestPanel;
        }
        $addDashboardRequest->refresh = [
            'type' => RefreshTypeConverter::fromString($dataSent['refresh']['type']),
            'interval' => $dataSent['refresh']['interval'],
        ];

        return $addDashboardRequest;
    }
}
