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

namespace Core\ServiceSeverity\Infrastructure\API\AddServiceSeverity;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\ServiceSeverity\Application\UseCase\AddServiceSeverity\AddServiceSeverity;
use Core\ServiceSeverity\Application\UseCase\AddServiceSeverity\AddServiceSeverityRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AddServiceSeverityController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param AddServiceSeverity $useCase
     * @param AddServiceSeverityPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        AddServiceSeverity $useCase,
        AddServiceSeverityPresenter $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            /** @var array{
             *     name: string,
             *     alias: string,
             *     level: int,
             *     icon_id: positive-int,
             *     is_activated?: bool
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddServiceSeveritySchema.json');
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));

            return $presenter->show();
        }

        $serviceSeverityRequest = $this->createRequestDto($data);
        $useCase($serviceSeverityRequest, $presenter);

        return $presenter->show();
    }

    /**
     * @param array{
     *     name: string,
     *     alias: string,
     *     level: int,
     *     icon_id: positive-int,
     *     is_activated?: bool
     * } $data
     *
     * @return AddServiceSeverityRequest
     */
    private function createRequestDto(array $data): AddServiceSeverityRequest
    {
        $serviceSeverityRequest = new AddServiceSeverityRequest();
        $serviceSeverityRequest->name = $data['name'];
        $serviceSeverityRequest->alias = $data['alias'];
        $serviceSeverityRequest->level = $data['level'];
        $serviceSeverityRequest->iconId = $data['icon_id'];
        $serviceSeverityRequest->isActivated = $data['is_activated'] ?? true;

        return $serviceSeverityRequest;
    }
}
