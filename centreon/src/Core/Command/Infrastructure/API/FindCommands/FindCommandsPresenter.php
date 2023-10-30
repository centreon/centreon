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

namespace Core\Command\Infrastructure\API\FindCommands;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Command\Application\UseCase\FindCommands\FindCommandsPresenterInterface;
use Core\Command\Application\UseCase\FindCommands\FindCommandsResponse;
use Core\Command\Infrastructure\Model\CommandTypeConverter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class FindCommandsPresenter extends AbstractPresenter implements FindCommandsPresenterInterface
{
    use PresenterTrait;

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(ResponseStatusInterface|FindCommandsResponse $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $result = [];

            foreach ($response->commands as $command) {
                $result[] = [
                    'id' => $command->id,
                    'name' => $command->name,
                    'type' => CommandTypeConverter::toInt($command->type),
                    'command_line' => $command->commandLine,
                    'is_shell' => $command->isShellEnabled,
                    'is_locked' => $command->isLocked,
                    'is_activated' => $command->isActivated,
                ];
            }
            $this->present([
                'result' => $result,
                'meta' => $this->requestParameters->toArray(),
            ]);
        }
    }
}
