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

namespace Core\HostGroup\Infrastructure\API\AddHostGroup;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\HostGroup\Application\UseCase\AddHostGroup\AddHostGroup;
use Core\HostGroup\Application\UseCase\AddHostGroup\AddHostGroupRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AddHostGroupController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param AddHostGroup $useCase
     * @param AddHostGroupPresenterSaas $saasPresenter
     * @param AddHostGroupPresenterOnPrem $onPremPresenter
     * @param bool $isCloudPlatform
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        AddHostGroup $useCase,
        AddHostGroupPresenterSaas $saasPresenter,
        AddHostGroupPresenterOnPrem $onPremPresenter,
        bool $isCloudPlatform,
    ): Response {
        $this->denyAccessUnlessGrantedForAPIConfiguration();

        if ($isCloudPlatform) {
            return $this->executeUseCaseSaas($useCase, $saasPresenter, $request);
        }

        return $this->executeUseCaseOnPrem($useCase, $onPremPresenter, $request);
    }

    /**
     * @param AddHostGroup $useCase
     * @param AddHostGroupPresenterSaas $saasPresenter
     * @param Request $request
     *
     * @return Response
     */
    private function executeUseCaseSaas(
        AddHostGroup $useCase,
        AddHostGroupPresenterSaas $saasPresenter,
        Request $request
    ): Response {
        try {
            /** @var array{
             *     name: string,
             *     alias?: ?string,
             *     icon_id?: ?positive-int,
             *     geo_coords?: ?string,
             *     is_activated?: bool
             * } $dataSent
             */
            $dataSent = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddHostGroupSchemaSaas.json');

            $dto = new AddHostGroupRequest();
            $dto->name = $dataSent['name'];
            $dto->alias = $dataSent['alias'] ?? '';
            $dto->iconId = $dataSent['icon_id'] ?? null;
            $dto->geoCoords = $dataSent['geo_coords'] ?? null;
            $dto->isActivated = $dataSent['is_activated'] ?? true;

            $useCase($dto, $saasPresenter);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $saasPresenter->setResponseStatus(new ErrorResponse($ex->getMessage()));
        }

        return $saasPresenter->show();
    }

    /**
     * @param AddHostGroup $useCase
     * @param AddHostGroupPresenterOnPrem $onPremPresenter
     * @param Request $request
     *
     * @return Response
     */
    private function executeUseCaseOnPrem(
        AddHostGroup $useCase,
        AddHostGroupPresenterOnPrem $onPremPresenter,
        Request $request
    ): Response {
        try {
            /** @var array{
             *     name: string,
             *     alias?: ?string,
             *     notes?: ?string,
             *     notes_url?: ?string,
             *     action_url?: ?string,
             *     icon_id?: ?positive-int,
             *     icon_map_id?: ?positive-int,
             *     rrd?: ?int,
             *     geo_coords?: ?string,
             *     comment?: ?string,
             *     is_activated?: bool
             * } $dataSent
             */
            $dataSent = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddHostGroupSchemaOnPrem.json');

            $dto = new AddHostGroupRequest();
            $dto->name = $dataSent['name'];
            $dto->alias = $dataSent['alias'] ?? '';
            $dto->notes = $dataSent['notes'] ?? '';
            $dto->notesUrl = $dataSent['notes_url'] ?? '';
            $dto->actionUrl = $dataSent['action_url'] ?? '';
            $dto->iconId = $dataSent['icon_id'] ?? null;
            $dto->iconMapId = $dataSent['icon_map_id'] ?? null;
            $dto->rrdRetention = $dataSent['rrd'] ?? null;
            $dto->geoCoords = $dataSent['geo_coords'] ?? null;
            $dto->comment = $dataSent['comment'] ?? '';
            $dto->isActivated = $dataSent['is_activated'] ?? true;

            $useCase($dto, $onPremPresenter);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $onPremPresenter->setResponseStatus(new ErrorResponse($ex->getMessage()));
        }

        return $onPremPresenter->show();
    }
}
