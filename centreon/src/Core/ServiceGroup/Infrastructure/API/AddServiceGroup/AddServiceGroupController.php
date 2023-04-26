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

namespace Core\ServiceGroup\Infrastructure\API\AddServiceGroup;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\ServiceGroup\Application\UseCase\AddServiceGroup\AddServiceGroup;
use Core\ServiceGroup\Application\UseCase\AddServiceGroup\AddServiceGroupRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AddServiceGroupController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param AddServiceGroup $useCase
     * @param AddServiceGroupPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        AddServiceGroup $useCase,
        AddServiceGroupPresenter $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForAPIConfiguration();

        try {
            /** @var array{
             *     name: string,
             *     alias: string,
             *     geo_coords?: ?string,
             *     comment?: ?string,
             *     is_activated?: bool
             * } $dataSent
             */
            $dataSent = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddServiceGroupSchema.json');

            $dto = new AddServiceGroupRequest();
            $dto->name = $dataSent['name'];
            $dto->alias = $dataSent['alias'];
            $dto->geoCoords = $dataSent['geo_coords'] ?? null;
            $dto->comment = $dataSent['comment'] ?? '';
            $dto->isActivated = $dataSent['is_activated'] ?? true;

            $useCase($dto, $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        }

        return $presenter->show();
    }
}
