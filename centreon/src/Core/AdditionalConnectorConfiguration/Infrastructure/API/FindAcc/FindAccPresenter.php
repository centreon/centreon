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

namespace Core\AdditionalConnectorConfiguration\Infrastructure\API\FindAcc;

use Core\AdditionalConnectorConfiguration\Application\UseCase\FindAcc\FindAccPresenterInterface;
use Core\AdditionalConnectorConfiguration\Application\UseCase\FindAcc\FindAccResponse;
use Core\AdditionalConnectorConfiguration\Domain\Model\Poller;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class FindAccPresenter extends AbstractPresenter implements FindAccPresenterInterface
{
    use PresenterTrait;

    /**
     * @inheritDoc
     */
    public function presentResponse(FindAccResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present([
                'id' => $response->id,
                'name' => $response->name,
                'type' => $response->type->value,
                'description' => $response->description,
                'parameters' => $response->parameters,
                'pollers' => array_map(
                    static fn(Poller $poller): array => ['id' => $poller->id, 'name' => $poller->name],
                    $response->pollers
                ),
                'created_at' => $this->formatDateToIso8601($response->createdAt),
                'created_by' => $response->createdBy,
                'updated_at' => $this->formatDateToIso8601($response->updatedAt),
                'updated_by' => $response->updatedBy,
            ]);
        }
    }
}
