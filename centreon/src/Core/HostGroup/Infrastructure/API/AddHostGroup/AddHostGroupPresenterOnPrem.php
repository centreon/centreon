<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Core\HostGroup\Infrastructure\API\AddHostGroup;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\HostGroup\Application\UseCase\AddHostGroup\AddHostGroupResponse;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

class AddHostGroupPresenterOnPrem extends AbstractPresenter
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
            && ($payload = $data->getPayload()) instanceof AddHostGroupResponse
        ) {
            $this->presentCreatedPayload($data, $payload);
        }

        parent::present($data);
    }

    /**
     * @param CreatedResponse<AddHostGroupResponse> $createdResponse
     * @param AddHostGroupResponse $addHostGroupResponse
     */
    private function presentCreatedPayload(
        CreatedResponse $createdResponse,
        AddHostGroupResponse $addHostGroupResponse
    ): void {
        $createdResponse->setPayload(
            [
                'id' => $addHostGroupResponse->id,
                'name' => $addHostGroupResponse->name,
                'alias' => $this->emptyStringAsNull($addHostGroupResponse->alias),
                'notes' => $this->emptyStringAsNull($addHostGroupResponse->notes),
                'notes_url' => $this->emptyStringAsNull($addHostGroupResponse->notesUrl),
                'action_url' => $this->emptyStringAsNull($addHostGroupResponse->actionUrl),
                'icon_id' => $addHostGroupResponse->iconId,
                'icon_map_id' => $addHostGroupResponse->iconMapId,
                'rrd' => $addHostGroupResponse->rrdRetention,
                'geo_coords' => $addHostGroupResponse->geoCoords,
                'comment' => $this->emptyStringAsNull($addHostGroupResponse->comment),
                'is_activated' => $addHostGroupResponse->isActivated,
            ]
        );

        // 👉️ We SHOULD send a valid header 'Location: <url>'.
        // But the GET api is not available at the time this UseCase was written.
        // This is nonsense to send something not usable.
    }
}
