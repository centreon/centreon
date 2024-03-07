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

declare(strict_types = 1);

namespace Core\Command\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Core\Command\Application\Repository\WriteCommandRepositoryInterface;
use Core\Command\Domain\Model\Argument;
use Core\Command\Domain\Model\NewCommand;
use Core\Command\Infrastructure\Model\CommandTypeConverter;
use Core\CommandMacro\Domain\Model\NewCommandMacro;
use Core\Common\Infrastructure\Repository\ApiRepositoryTrait;
use Core\Common\Infrastructure\Repository\RepositoryTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiWriteCommandRepository implements WriteCommandRepositoryInterface
{
    use LoggerTrait;
    use RepositoryTrait;
    use ApiRepositoryTrait;

    public function __construct(readonly private HttpClientInterface $httpClient)
    {
    }

    /**
     * @inheritDoc
     */
    public function add(NewCommand $command): int
    {
        $apiEndpoint = $this->url . '/api/latest/configuration/commands';
        $options = [
            'verify_peer' => true,
            'verify_host' => true,
            'timeout' => $this->timeout,
        ];
        if ($this->proxy !== null) {
            $options['proxy'] = $this->proxy;
            $this->debug('Adding command using proxy');
        }

        $options['headers'] = [
            'Content-Type: application/json',
            'X-AUTH-TOKEN: ' . $this->authenticationToken,
        ];
        $options['body'] = json_encode([
            'name' => $command->getName(),
            'type' => CommandTypeConverter::toInt($command->getType()),
            'command_line' => $command->getCommandLine(),
            'is_shell' => $command->isShellEnabled(),
            'argument_example' => $this->emptyStringAsNull($command->getArgumentExample()),
            'arguments' => array_map(
                fn(Argument $arg) => [
                    'name' => $arg->getName(),
                    'description' => $this->emptyStringAsNull($arg->getDescription()),
                ],
                $command->getArguments(),
            ),
            'macros' => array_map(
                fn(NewCommandMacro $cmd) => [
                    'name' => $cmd->getName(),
                    'type' => $cmd->getType(),
                    'description' => $this->emptyStringAsNull($cmd->getDescription()),
                ],
                $command->getMacros(),
            ),
            'connector_id' => $command->getConnectorId(),
            'graph_template_id' => $command->getGraphTemplateId(),
        ]);

        if ($options['body'] === false) {
            $this->debug('Error when encoding request body');

            throw new \Exception('Request error: unable to encode body');
        }

        $response = $this->httpClient->request('POST', $apiEndpoint, $options);

        if ($response->getStatusCode() !== 201) {
            /**
             * @var array{message:string} $content
             */
            $content = $response->toArray(false);
            $this->debug('API error', [
                'http_code' => $response->getStatusCode(),
                'message' => $content['message'],
            ]);

            throw new \Exception(sprintf('Request error: %s', $content['message']), $response->getStatusCode());
        }

        return (int) $response->toArray()['id'];
    }
}
