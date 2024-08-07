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

namespace Core\Broker\Infrastructure\API\AddBrokerInputOutput;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Broker\Application\UseCase\AddBrokerInputOutput\AddBrokerInputOutputPresenterInterface;
use Core\Broker\Application\UseCase\AddBrokerInputOutput\AddBrokerInputOutputResponse;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class AddBrokerInputOutputPresenter extends AbstractPresenter implements AddBrokerInputOutputPresenterInterface
{
    use PresenterTrait;

    /**
     * @inheritDoc
     */
    public function presentResponse(AddBrokerInputOutputResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present(
                new CreatedResponse(
                    $response->id,
                    [
                        'id' => $response->id,
                        'broker_id' => $response->brokerId,
                        'name' => $response->name,
                        'type' => [
                            'id' => $response->type->id,
                            'name' => $response->type->name,
                        ],
                        'parameters' => (object) $response->parameters,
                    ]
                )
            );

            // NOT setting location as required route does not currently exist
        }
    }
}
