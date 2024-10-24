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

namespace Core\AgentConfiguration\Infrastructure\API\FindAgentConfiguration;

use Centreon\Application\Controller\AbstractController;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfiguration\FindAgentConfiguration;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Api\StandardPresenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(
    'read_agent_configuration',
    null,
    'You are not allowed to access poller/agent configurations',
    Response::HTTP_FORBIDDEN
)]
#[IsGranted(
    'read_agent_configuration_pollers',
    'id',
    'poller/agent configuration could not be found',
    Response::HTTP_NOT_FOUND
)]
final class FindAgentConfigurationController extends AbstractController
{
    public function __invoke(
        int $id,
        FindAgentConfiguration $useCase,
        StandardPresenter $presenter
    ): Response {
        $response = $useCase($id);

        if ($response instanceof ResponseStatusInterface) {
            return $this->createResponse($response);
        }

        return JsonResponse::fromJsonString(
            $presenter->present($response, ['groups' => ['AgentConfiguration:Read']])
        );
    }
}
