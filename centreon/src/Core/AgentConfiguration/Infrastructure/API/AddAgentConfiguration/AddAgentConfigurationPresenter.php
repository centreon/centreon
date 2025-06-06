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

namespace Core\AgentConfiguration\Infrastructure\API\AddAgentConfiguration;

use Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration\AddAgentConfigurationPresenterInterface;
use Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration\AddAgentConfigurationResponse;
use Core\AgentConfiguration\Domain\Model\ConnectionModeEnum;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class AddAgentConfigurationPresenter extends AbstractPresenter implements AddAgentConfigurationPresenterInterface
{
    use PresenterTrait;

    /**
     * @inheritDoc
     */
    public function presentResponse(AddAgentConfigurationResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present(
                new CreatedResponse(
                    $response->id,
                    [
                        'id' => $response->id,
                        'name' => $response->name,
                        'type' => $response->type->value,
                        'configuration' => $response->configuration,
                        'connection_mode' => $this->connectionModeToString($response->connectionMode),
                        'pollers' => array_map(fn(Poller $poller) => ['id' => $poller->id, 'name' => $poller->name], $response->pollers),
                    ]
                )
            );
            // NOT setting location as required route does not currently exist
        }
    }

    private function ConnectionModeToString(ConnectionModeEnum $connectionMode): string {
        return match ($connectionMode) {
            ConnectionModeEnum::SECURE => 'secure',
            ConnectionModeEnum::NO_TLS => 'no-tls',
            ConnectionModeEnum::INSECURE => 'insecure',
        };
    }
}
