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

namespace Core\HostGroup\Infrastructure\API\UpdateHostGroup;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Infrastructure\FeatureFlags;
use Core\HostGroup\Application\UseCase\UpdateHostGroup\UpdateHostGroup;
use Core\HostGroup\Application\UseCase\UpdateHostGroup\UpdateHostGroupRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class UpdateHostGroupController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param UpdateHostGroup $useCase
     * @param FeatureFlags $flags
     * @param int $hostGroupId
     * @param UpdateHostGroupPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        int $hostGroupId,
        Request $request,
        UpdateHostGroup $useCase,
        UpdateHostGroupPresenter $presenter,
        FeatureFlags $flags,
    ): Response {
        $this->denyAccessUnlessGrantedForAPIConfiguration();

        if ($flags->isCloudPlatform()) {
            return $this->executeUseCaseSaas($hostGroupId, $useCase, $presenter, $request);
        }

        return $this->executeUseCaseOnPrem($hostGroupId, $useCase, $presenter, $request);
    }

    /**
     * @param UpdateHostGroup $useCase
     * @param UpdateHostGroupPresenter $presenter
     * @param Request $request
     * @param int $hostGroupId
     *
     * @return Response
     */
    private function executeUseCaseSaas(
        int $hostGroupId,
        UpdateHostGroup $useCase,
        UpdateHostGroupPresenter $presenter,
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
            $dataSent = $this->validateAndRetrieveDataSent($request, __DIR__ . '/UpdateHostGroupSchemaSaas.json');

            $dto = new UpdateHostGroupRequest();
            $dto->name = $dataSent['name'];
            $dto->alias = $dataSent['alias'] ?? '';
            $dto->iconId = $dataSent['icon_id'] ?? null;
            $dto->geoCoords = $dataSent['geo_coords'] ?? null;
            $dto->isActivated = $dataSent['is_activated'] ?? true;

            $useCase($hostGroupId, $dto, $presenter);
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
     * @param UpdateHostGroup $useCase
     * @param UpdateHostGroupPresenter $presenter
     * @param Request $request
     * @param int $hostGroupId
     *
     * @return Response
     */
    private function executeUseCaseOnPrem(
        int $hostGroupId,
        UpdateHostGroup $useCase,
        UpdateHostGroupPresenter $presenter,
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
            $dataSent = $this->validateAndRetrieveDataSent($request, __DIR__ . '/UpdateHostGroupSchemaOnPrem.json');

            $dto = new UpdateHostGroupRequest();
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

            $useCase($hostGroupId, $dto, $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse($ex));
        }

        return $presenter->show();
    }
}
