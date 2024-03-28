<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Connector\Application\UseCase\FindConnectors;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Domain\Model\Command;
use Core\Command\Domain\Model\CommandType;
use Core\Connector\Application\Exception\ConnectorException;
use Core\Connector\Application\Repository\ReadConnectorRepositoryInterface;
use Core\Connector\Domain\Model\Connector;

final class FindConnectors
{
    use LoggerTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadConnectorRepositoryInterface $readConnectorRepository,
        private readonly ReadCommandRepositoryInterface $readCommandRepository,
        private readonly ContactInterface $contact,
    ) {
    }

    /**
     * @param FindConnectorsPresenterInterface $presenter
     */
    public function __invoke(FindConnectorsPresenterInterface $presenter): void
    {
        try {
            if (
                ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_CONNECTORS_RW)
                && ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_CONNECTORS_R)
            ) {
                $this->error(
                    "User doesn't have sufficient rights to see connectors",
                    ['user_id' => $this->contact->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(ConnectorException::accessNotAllowed())
                );

                return;
            }

            $connectors = $this->readConnectorRepository->findByRequestParametersAndCommandTypes(
                $this->requestParameters,
                $this->retrieveConnectorTypesBasedOnContactRights(),
            );

            $commandIds = [];
            foreach ($connectors as $connector)
            {
                array_push($commandIds, ...$connector->getCommandIds());
            }

            $commands = [];
            if ($commandIds !== []) {
                $commands = $this->readCommandRepository->findByIds($commandIds);
            }

            $presenter->presentResponse($this->createResponse($connectors, $commands));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(ConnectorException::errorWhileSearching($ex)));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param Connector[] $connectors
     * @param Command[] $commands
     *
     * @return FindConnectorsResponse
     */
    private function createResponse(array $connectors, array $commands): FindConnectorsResponse
    {
        $response = new FindConnectorsResponse();

        foreach ($connectors as $connector) {
            $connectorDto = new ConnectorDto();
            $connectorDto->id = $connector->getId();
            $connectorDto->name = $connector->getName();
            $connectorDto->commandLine = $connector->getCommandLine();
            $connectorDto->description = $connector->getDescription();
            $connectorDto->isActivated = $connector->isActivated();
            $connectorDto->commands = array_map(
                fn(int $commandId) => [
                    'id' => $commands[$commandId]->getId(),
                    'name' => $commands[$commandId]->getName(),
                    'type' => $commands[$commandId]->getType(),
                ],
                $connector->getCommandIds()
            );
            $response->connectors[] = $connectorDto;
        }

        return $response;
    }

    /**
     * @return CommandType[]
     */
    private function retrieveConnectorTypesBasedOnContactRights(): array
    {
        if ($this->contact->isAdmin()) {
            return [
                CommandType::Notification,
                CommandType::Check,
                CommandType::Miscellaneous,
                CommandType::Discovery,
            ];
        }
        $commandsTypes = [];

        if (
            $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_R)
            || $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_CHECKS_RW)
        ) {
            $commandsTypes[] = CommandType::Check;
        }

        if (
            $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_R)
            || $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_NOTIFICATIONS_RW)
        ) {
            $commandsTypes[] = CommandType::Notification;
        }

        if (
            $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_R)
            || $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_MISCELLANEOUS_RW)
        ) {
            $commandsTypes[] = CommandType::Miscellaneous;
        }

        if (
            $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_R)
            || $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_COMMANDS_DISCOVERY_RW)
        ) {
            $commandsTypes[] = CommandType::Discovery;
        }

        return $commandsTypes;
    }
}
