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

namespace Core\Command\Infrastructure\API\AddCommand;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Command\Application\UseCase\AddCommand\AddCommandPresenterInterface;
use Core\Command\Application\UseCase\AddCommand\AddCommandResponse;
use Core\Command\Infrastructure\Model\CommandTypeConverter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class AddCommandPresenter extends AbstractPresenter implements AddCommandPresenterInterface
{
    use PresenterTrait;

    public function __construct(PresenterFormatterInterface $presenterFormatter)
    {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(ResponseStatusInterface|AddCommandResponse $response): void
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
                        'type' => CommandTypeConverter::toInt($response->type),
                        'command_line' => $response->commandLine,
                        'is_shell' => $response->isShellEnabled,
                        'is_activated' => $response->isActivated,
                        'is_locked' => $response->isLocked,
                        'argument_example' => $this->emptyStringAsNull($response->argumentExample),
                        'arguments' => array_map(fn(array $argument): array => [
                            'name' => $argument['name'],
                            'description' => $this->emptyStringAsNull($argument['description']),
                        ], $response->arguments),
                        'macros' => array_map(fn(array $macro): array => [
                            'name' => $macro['name'],
                            'type' => $macro['type']->value,
                            'description' => $this->emptyStringAsNull($macro['description']),
                        ], $response->macros),
                        'connector' => $response->connector
                            ? ['id' => $response->connector['id'], 'name' => $response->connector['name']]
                            : null,
                        'grap_template' => $response->graphTemplate
                            ? ['id' => $response->graphTemplate['id'], 'name' => $response->graphTemplate['name']]
                            : null,
                    ]
                )
            );
        }
    }
}
