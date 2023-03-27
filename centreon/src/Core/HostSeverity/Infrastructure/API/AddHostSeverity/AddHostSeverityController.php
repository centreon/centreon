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

namespace Core\HostSeverity\Infrastructure\API\AddHostSeverity;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\HostSeverity\Application\UseCase\AddHostSeverity\AddHostSeverity;
use Core\HostSeverity\Application\UseCase\AddHostSeverity\AddHostSeverityRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AddHostSeverityController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param AddHostSeverity $useCase
     * @param AddHostSeverityPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        AddHostSeverity $useCase,
        AddHostSeverityPresenter $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            /** @var array{
             *     name: string,
             *     alias: string,
             *     level: int,
             *     icon_id: positive-int,
             *     is_activated?: bool,
             *     comment?: string|null
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddHostSeveritySchema.json');
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));

            return $presenter->show();
        }

        $hostSeverityRequest = $this->createRequestDto($data);
        $useCase($hostSeverityRequest, $presenter);

        return $presenter->show();
    }

    /**
     * @param array{
     *     name: string,
     *     alias: string,
     *     level: int,
     *     icon_id: positive-int,
     *     is_activated?: bool,
     *     comment?: string|null
     * } $data
     *
     * @return AddHostSeverityRequest
     */
    private function createRequestDto(array $data): AddHostSeverityRequest
    {
        $hostSeverityRequest = new AddHostSeverityRequest();
        $hostSeverityRequest->name = $data['name'];
        $hostSeverityRequest->alias = $data['alias'];
        $hostSeverityRequest->level = $data['level'];
        $hostSeverityRequest->iconId = $data['icon_id'];
        $hostSeverityRequest->isActivated = $data['is_activated'] ?? true;
        $hostSeverityRequest->comment = $data['comment'] ?? null;

        return $hostSeverityRequest;
    }
}
