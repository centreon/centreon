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

namespace Tests\Core\AgentConfiguration\Application\UseCase\FindAgentConfiguration;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfiguration\FindAgentConfiguration;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfiguration\FindAgentConfigurationResponse;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfiguration\PollerDto;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\TelegrafConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;

beforeEach(function (): void {
    $this->useCase = new FindAgentConfiguration(
        user: $this->user = $this->createMock(ContactInterface::class),
        readRepository: $this->readRepository = $this->createMock(ReadAgentConfigurationRepositoryInterface::class),
    );
});

it('should present a Not Found Response when object does not exist', function () {
    $this->readRepository
        ->expects($this->once())
        ->method('find')
        ->willReturn(null);

    $response = ($this->useCase)(1);

    expect($response)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($response->getMessage())
        ->toBe('Agent Configuration not found');
});

it('should present an Error Response when an unexpected error occurs', function () {
    $this->readRepository
        ->expects($this->once())
        ->method('find')
        ->willThrowException(new \Exception());

    $response = ($this->useCase)(1);

    expect($response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($response->getMessage())
        ->toBe(AgentConfigurationException::errorWhileRetrievingObject()->getMessage());
});

it('should present a FindConfigurationResponse when everything is ok', function () {
    $configuration = new TelegrafConfigurationParameters([
        'otel_server_address' => '10.10.10.10',
        'otel_server_port' => 453,
        'otel_public_certificate' => 'public_certif',
        'otel_ca_certificate' => 'ca_certif',
        'otel_private_key' => 'otel-key',
        'conf_server_port' => 454,
        'conf_certificate' => 'conf-certif',
        'conf_private_key' => 'conf-key'
    ]);

    $pollers = [
        new Poller(1, 'pollerOne'),
        new Poller(2, 'pollerTwo')
    ];

    $this->readRepository
        ->expects($this->once())
        ->method('find')
        ->willReturn(new AgentConfiguration(1, 'acOne', Type::TELEGRAF, $configuration));

    $this->readRepository
        ->expects($this->once())
        ->method('findPollersByAcId')
        ->willReturn($pollers);

    $response = ($this->useCase)(1);

    expect($response)
        ->toBeInstanceOf(FindAgentConfigurationResponse::class)
        ->and($response->agentConfiguration->getId())->toBe(1)
        ->and($response->agentConfiguration->getName())->toBe('acOne')
        ->and($response->agentConfiguration->getType())->toBe(Type::TELEGRAF)
        ->and($response->agentConfiguration->getConfiguration()->getData())->toBe([
            'otel_server_address' => '10.10.10.10',
            'otel_server_port' => 453,
            'otel_public_certificate' => 'public_certif',
            'otel_ca_certificate' => 'ca_certif',
            'otel_private_key' => 'otel-key',
            'conf_server_port' => 454,
            'conf_certificate' => 'conf-certif',
            'conf_private_key' => 'conf-key'
        ])
        ->and($response->pollers[0]->getId())->toBe(1)
        ->and($response->pollers[0]->getName())->toBe('pollerOne')
        ->and($response->pollers[1]->getId())->toBe(2)
        ->and($response->pollers[1]->getName())->toBe('pollerTwo');
});
