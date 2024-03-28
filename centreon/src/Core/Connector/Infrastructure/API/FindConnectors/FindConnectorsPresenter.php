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

namespace Core\Connector\Infrastructure\API\FindConnectors;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Command\Infrastructure\Model\CommandTypeConverter;
use Core\Connector\Application\UseCase\FindConnectors\FindConnectorsPresenterInterface;
use Core\Connector\Application\UseCase\FindConnectors\FindConnectorsResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class FindConnectorsPresenter extends AbstractPresenter implements FindConnectorsPresenterInterface
{
    use PresenterTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(ResponseStatusInterface|FindConnectorsResponse $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $result = [];

            foreach ($response->connectors as $connector) {
                $result[] = [
                    'id' => $connector->id,
                    'name' => $connector->name,
                    'command_line' => $connector->commandLine,
                    'description' => $this->emptyStringAsNull($connector->description),
                    'commands' => array_map(
                        fn(array $command) => [
                            'id' => $command['id'],
                            'type' => CommandTypeConverter::toInt($command['type']),
                            'name' => $command['name'],
                        ],
                        $connector->commands
                    ),
                    'is_activated' => $connector->isActivated,
                ];
            }
            $this->present([
                'result' => $result,
                'meta' => $this->requestParameters->toArray(),
            ]);
        }
    }
}
