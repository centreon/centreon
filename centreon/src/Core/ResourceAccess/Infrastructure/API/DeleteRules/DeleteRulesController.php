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

namespace Core\ResourceAccess\Infrastructure\API\DeleteRules;

use Centreon\Application\Controller\AbstractController;
use Core\ResourceAccess\Application\UseCase\DeleteRules\DeleteRules;
use Core\ResourceAccess\Application\UseCase\DeleteRules\DeleteRulesRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DeleteRulesController extends AbstractController
{
    /**
     * @param Request $request
     * @param DeleteRules $useCase
     * @param DeleteRulesPresenter $presenter
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        DeleteRules $useCase,
        DeleteRulesPresenter $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        /**
         * @var array{
         *  ids: int[]
         * } $data
         */
        $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/DeleteRulesSchema.json');
        $useCase($this->createDtoFromData($data), $presenter);

        return $presenter->show();
    }

    /**
     * @param array{
     *  ids: int[]
     * } $data
     *
     * @return DeleteRulesRequest
     */
    private function createDtoFromData(array $data): DeleteRulesRequest
    {
        $dto = new DeleteRulesRequest();
        $dto->ids = $data['ids'];

        return $dto;
    }
}

