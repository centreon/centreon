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

namespace Core\ServiceGroup\Infrastructure\API\UpdateServiceGroup;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\ServiceGroup\Application\UseCase\UpdateServiceGroup\UpdateServiceGroup;
use Core\ServiceGroup\Application\UseCase\UpdateServiceGroup\UpdateServiceGroupRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class UpdateServiceGroupController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param UpdateServiceGroup $useCase
     * @param DefaultPresenter $presenter
     * @param int $serviceGroupId
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        UpdateServiceGroup $useCase,
        DefaultPresenter $presenter,
        int $serviceGroupId,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            /** @var array{
             *     name: string,
             *     alias: string,
             *     geo_coords?: ?string,
             *     comment?: ?string,
             *     is_activated?: bool
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/UpdateServiceGroupSchema.json');
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));

            return $presenter->show();
        }

        $serviceSeverityRequest = $this->createRequestDto($data);
        $useCase($serviceSeverityRequest, $presenter, $serviceGroupId);

        return $presenter->show();
    }

    /**
     * @var array{
     *     name: string,
     *     alias: string,
     *     geo_coords?: ?string,
     *     comment?: ?string,
     *     is_activated?: bool
     * } $data
     *
     *
     * @return UpdateServiceGroupRequest
     */
    private function createRequestDto(array $data): UpdateServiceGroupRequest
    {
        $serviceGroupRequest = new UpdateServiceGroupRequest();
        $serviceGroupRequest->name = $data['name'];
        $serviceGroupRequest->alias = $data['alias'];
        $serviceGroupRequest->comment = $data['comment'];
        $serviceGroupRequest->geoCoords = $data['geo_coords'];
        $serviceGroupRequest->isActivated = $data['is_activated'] ?? true;

        return $serviceGroupRequest;
    }
}
