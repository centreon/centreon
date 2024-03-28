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

namespace Core\ServiceGroup\Infrastructure\API\AddServiceGroup;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Infrastructure\Common\Presenter\PresenterTrait;
use Core\ServiceGroup\Application\UseCase\AddServiceGroup\AddServiceGroupResponse;

class AddServiceGroupPresenter extends AbstractPresenter
{
    use PresenterTrait;
    use LoggerTrait;

    /**
     * {@inheritDoc}
     */
    public function present(mixed $data): void
    {
        if (
            $data instanceof CreatedResponse
            && ($payload = $data->getPayload()) instanceof AddServiceGroupResponse
        ) {
            parent::present($this->presentCreatedPayload($data, $payload));
        } else {
            parent::present($data);
        }
    }

    /**
     * @param CreatedResponse<int, AddServiceGroupResponse> $createdResponse
     * @param AddServiceGroupResponse $addServiceGroupResponse
     *
     * @return CreatedResponse<int, array{
     *     id: int,
     *     name: string,
     *     alias: string|null,
     *     geo_coords: string|null,
     *     comment: string|null,
     *     is_activated: bool,
     * }>
     */
    private function presentCreatedPayload(
        CreatedResponse $createdResponse,
        AddServiceGroupResponse $addServiceGroupResponse
    ): CreatedResponse {
        return $createdResponse->withPayload(
            [
                'id' => $addServiceGroupResponse->id,
                'name' => $addServiceGroupResponse->name,
                'alias' => $this->emptyStringAsNull($addServiceGroupResponse->alias),
                'geo_coords' => $addServiceGroupResponse->geoCoords,
                'comment' => $this->emptyStringAsNull($addServiceGroupResponse->comment),
                'is_activated' => $addServiceGroupResponse->isActivated,
            ]
        );

        // 👉️ We SHOULD send a valid header 'Location: <url>'.
        // But the GET api is not available at the time this UseCase was written.
        // This is nonsense to send something not usable.
    }
}
