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

namespace Core\AgentConfiguration\Infrastructure\API\UpdateAgentConfiguration;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\AgentConfiguration\Application\UseCase\UpdateAgentConfiguration\UpdateAgentConfiguration;
use Core\AgentConfiguration\Application\UseCase\UpdateAgentConfiguration\UpdateAgentConfigurationRequest;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UpdateAgentConfigurationController extends AbstractController
{
    use LoggerTrait;

    public function __invoke(
        int $id,
        Request $request,
        UpdateAgentConfiguration $useCase,
        DefaultPresenter $presenter,
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $updateAcRequest = $this->createRequest($id, $request);
            $useCase($updateAcRequest, $presenter);
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
     * @param int $id
     * @param Request $request
     *
     * @throws \InvalidArgumentException
     *
     * @return UpdateAgentConfigurationRequest
     */
    private function createRequest(int $id, Request $request): UpdateAgentConfigurationRequest
    {
        /**
         * @var array{
         *     name:string,
         *     type:string,
         *     poller_ids:int[],
         *     configuration:array<string,mixed>
         * } $data
         */
        $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/UpdateAgentConfigurationSchema.json');

        $schemaFile = match ($data['type']) {
            'telegraf' => 'TelegrafConfigurationSchema.json',
            default => throw new \InvalidArgumentException(sprintf("Unknown parameter type with value '%s'", $data['type']))
        };

        $this->validateDataSent($request, __DIR__ . "/../Schema/{$schemaFile}");

        $updateRequest = new UpdateAgentConfigurationRequest();
        $updateRequest->id = $id;
        $updateRequest->type = $data['type'];
        $updateRequest->name = $data['name'];
        $updateRequest->pollerIds = $data['poller_ids'];
        $updateRequest->configuration = $data['configuration'];

        return $updateRequest;
    }
}
